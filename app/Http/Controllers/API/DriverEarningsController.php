<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Api\V1\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DriverPlatformEarning;
use App\Models\DriverTruvAccount;
use App\Services\TruvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DriverEarningsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TruvService $truvService)
    {
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $driverTruvAccount = DriverTruvAccount::query()
                ->where('user_id', $user->id)
                ->first();

            if (! $driverTruvAccount || empty($driverTruvAccount->link_id)) {
                return $this->error('Driver is not connected with Truv yet', null, 422);
            }

            $reportResponse = $this->truvService->getIncomeReport($driverTruvAccount->link_id);
            $platformTotals = $this->extractPlatformTotals($reportResponse);
            $currency = $this->extractCurrency($reportResponse);
            $syncedAt = now();

            DB::transaction(function () use ($user, $platformTotals, $currency, $syncedAt): void {
                foreach ($platformTotals as $platformName => $totalEarnings) {
                    DriverPlatformEarning::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'platform_name' => $platformName,
                        ],
                        [
                            'total_earnings' => $totalEarnings,
                            'currency' => $currency,
                            'last_synced_at' => $syncedAt,
                        ]
                    );
                }
            });

            $driverTruvAccount->update([
                'last_report' => $reportResponse,
            ]);

            Log::info('Driver earnings refresh succeeded', [
                'user_id' => $user->id,
                'platform_count' => count($platformTotals),
            ]);

            return $this->success('Driver earnings refreshed', [
                'platforms_synced' => count($platformTotals),
                'currency' => $currency,
                'last_synced_at' => $syncedAt,
            ]);
        } catch (Throwable $exception) {
            Log::error('Driver earnings refresh failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to refresh driver earnings', null, 422);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $platformRows = DriverPlatformEarning::query()
                ->where('user_id', $user->id)
                ->get();

            $driverTruvAccount = DriverTruvAccount::query()
                ->where('user_id', $user->id)
                ->first();

            $report = is_array($driverTruvAccount?->last_report) ? $driverTruvAccount->last_report : [];

            $monthlyTotal = $this->extractPeriodTotal($report, [
                'monthly_total',
                'month_total',
                'monthly_earnings',
                'month_to_date',
            ]);

            $weeklyTotal = $this->extractPeriodTotal($report, [
                'weekly_total',
                'week_total',
                'weekly_earnings',
                'week_to_date',
            ]);

            $lastSyncedAt = $platformRows->max('last_synced_at');
            $currency = $platformRows->first()?->currency ?? $this->extractCurrency($report);

            return $this->success('Driver earnings summary', [
                'total_all_platforms' => (float) $platformRows->sum('total_earnings'),
                'monthly_total' => $monthlyTotal,
                'weekly_total' => $weeklyTotal,
                'currency' => $currency,
                'last_synced_at' => $lastSyncedAt,
            ]);
        } catch (Throwable $exception) {
            Log::error('Driver earnings summary failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to fetch driver earnings summary', null, 422);
        }
    }

    public function platforms(Request $request): JsonResponse
    {
        try {
            $platforms = DriverPlatformEarning::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('total_earnings')
                ->get([
                    'platform_name',
                    'total_earnings',
                    'currency',
                    'last_synced_at',
                ]);

            return $this->success('Driver platform earnings', [
                'platforms' => $platforms,
            ]);
        } catch (Throwable $exception) {
            Log::error('Driver platform earnings fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error('Unable to fetch driver platform earnings', null, 422);
        }
    }

    /**
     * @return array<string, float>
     */
    private function extractPlatformTotals(array $report): array
    {
        $employments = Arr::wrap(
            data_get($report, 'employments', data_get($report, 'report.employments', []))
        );

        $totals = [];

        foreach ($employments as $employment) {
            if (! is_array($employment)) {
                continue;
            }

            $platformName = $this->extractPlatformName($employment);
            $platformEarnings = $this->extractEmploymentEarnings($employment);

            if ($platformEarnings <= 0) {
                continue;
            }

            $totals[$platformName] = round(($totals[$platformName] ?? 0) + $platformEarnings, 2);
        }

        return $totals;
    }

    private function extractPlatformName(array $employment): string
    {
        $candidates = [
            data_get($employment, 'legal_name'),
            data_get($employment, 'company_name'),
            data_get($employment, 'employer_name'),
            data_get($employment, 'name'),
            data_get($employment, 'workplace.name'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'Unknown Platform';
    }

    private function extractEmploymentEarnings(array $employment): float
    {
        $directKeys = [
            'total_earnings',
            'total_income',
            'ytd_gross_pay',
            'gross_pay',
            'net_pay',
            'amount',
        ];

        foreach ($directKeys as $key) {
            $value = data_get($employment, $key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        $paychecks = Arr::wrap(data_get($employment, 'paychecks', []));
        $sum = 0.0;

        foreach ($paychecks as $paycheck) {
            if (! is_array($paycheck)) {
                continue;
            }

            $payValue = data_get($paycheck, 'net_pay', data_get($paycheck, 'gross_pay', data_get($paycheck, 'amount')));

            if (is_numeric($payValue)) {
                $sum += (float) $payValue;
            }
        }

        return round($sum, 2);
    }

    private function extractCurrency(array $report): string
    {
        $currency = data_get($report, 'currency')
            ?? data_get($report, 'report.currency')
            ?? data_get($report, 'employments.0.currency')
            ?? data_get($report, 'report.employments.0.currency');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : 'USD';
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function extractPeriodTotal(array $report, array $keys): float
    {
        foreach ($keys as $key) {
            $value = data_get($report, $key);

            if (is_numeric($value)) {
                return (float) $value;
            }

            $value = data_get($report, 'report.'.$key);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return 0.0;
    }
}

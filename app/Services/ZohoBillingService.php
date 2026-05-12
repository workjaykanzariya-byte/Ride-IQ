<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ZohoBillingService
{
    public function getAccessToken(): string
    {
        $this->validateConfig();
        return Cache::remember('zoho_access_token', now()->addMinutes(50), function (): string {
            try {
                $response = Http::asForm()->post($this->getAccountsTokenUrl(), [
                    'refresh_token' => config('zoho.refresh_token'),
                    'client_id' => config('zoho.client_id'),
                    'client_secret' => config('zoho.client_secret'),
                    'grant_type' => 'refresh_token',
                    'redirect_uri' => config('zoho.redirect_uri'),
                ]);

                Log::info('Zoho token response received', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'token_url' => $this->getAccountsTokenUrl(),
                ]);

                if (! $response->successful() || ! Arr::get($response->json(), 'access_token')) {
                    throw new RuntimeException('Zoho token generation failed: '.$response->body());
                }

                return (string) Arr::get($response->json(), 'access_token');
            } catch (\Throwable $exception) {
                Log::error('Zoho token generation exception', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
                throw $exception;
            }
        });
    }

    protected function getAccountsTokenUrl(): string
    {
        if (config('zoho.accounts_url')) {
            return (string) config('zoho.accounts_url');
        }

        $region = strtolower((string) config('zoho.region', 'com'));
        $suffix = match ($region) {
            'in' => 'in', 'eu' => 'eu', 'au' => 'com.au', 'jp' => 'jp', 'ca' => 'ca',
            default => 'com',
        };

        return "https://accounts.zoho.{$suffix}/oauth/v2/token";
    }

    protected function client(): PendingRequest
    {
        $this->validateConfig();
        return Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$this->getAccessToken(),
            'X-com-zoho-subscriptions-organizationid' => (string) config('zoho.org_id'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->baseUrl(rtrim((string) config('zoho.base_url'), '/'));
    }

    public function getPlans(): array
    {
        return Arr::get($this->safeZohoCall('getPlans', fn () => $this->client()->get('/plans')->throw()->json()), 'plans', []);
    }

    public function getPlanByCode(string $planCode): ?array
    {
        foreach ($this->getPlans() as $plan) {
            if ((string) Arr::get($plan, 'plan_code') === $planCode) {
                return $plan;
            }
        }

        return null;
    }

    public function createCustomer(array $payload): array { return $this->safeZohoCall('createCustomer', fn () => $this->client()->post('/customers', $payload)->throw()->json(), $payload); }
    public function createHostedCheckoutPage(array $payload): array { return $this->safeZohoCall('createHostedCheckoutPage', fn () => $this->client()->post('/hostedpages/newsubscription', $payload)->throw()->json(), $payload); }
    public function getSubscription(string $id): array { return $this->safeZohoCall('getSubscription', fn () => $this->client()->get("/subscriptions/{$id}")->throw()->json()); }
    public function cancelSubscription(string $id): array { return $this->safeZohoCall('cancelSubscription', fn () => $this->client()->post("/subscriptions/{$id}/cancel")->throw()->json()); }
    public function getInvoice(string $id): array { return $this->safeZohoCall('getInvoice', fn () => $this->client()->get("/invoices/{$id}")->throw()->json()); }
    public function getCustomer(string $id): array { return $this->safeZohoCall('getCustomer', fn () => $this->client()->get("/customers/{$id}")->throw()->json()); }
    public function verifyWebhook(?string $token): bool { return hash_equals((string) config('zoho.webhook_token'), (string) $token); }



    private function validateConfig(): void
    {
        $required = [
            'zoho.client_id' => config('zoho.client_id'),
            'zoho.client_secret' => config('zoho.client_secret'),
            'zoho.refresh_token' => config('zoho.refresh_token'),
            'zoho.org_id' => config('zoho.org_id'),
            'zoho.base_url' => config('zoho.base_url'),
        ];

        foreach ($required as $key => $value) {
            if (blank($value)) {
                throw new RuntimeException("Missing required Zoho configuration: {$key}");
            }
        }
    }

    private function safeZohoCall(string $action, callable $callback, array $payload = []): array
    {
        try {
            $result = $callback();
            Log::info("Zoho API success: {$action}", ['payload' => $payload, 'response' => $result, 'base_url' => config('zoho.base_url')]);

            return is_array($result) ? $result : [];
        } catch (RequestException $exception) {
            $response = $exception->response;
            Log::error("Zoho API request failed: {$action}", ['payload' => $payload, 'status' => $response?->status(), 'url' => (string)($response?->effectiveUri() ?? ''), 'headers' => ['Authorization' => 'Zoho-oauthtoken '.Str::mask((string)$this->getAccessToken(), '*', 6), 'X-com-zoho-subscriptions-organizationid' => config('zoho.org_id')], 'body' => $response?->body(), 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
            throw new RuntimeException("Zoho API {$action} failed: ".($response?->body() ?: $exception->getMessage()));
        } catch (\Throwable $exception) {
            Log::error("Zoho API exception: {$action}", ['payload' => $payload, 'base_url' => config('zoho.base_url'), 'headers' => ['Authorization' => 'Zoho-oauthtoken '.Str::mask((string) Cache::get('zoho_access_token', ''), '*', 6), 'X-com-zoho-subscriptions-organizationid' => config('zoho.org_id')], 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine(), 'trace' => $exception->getTraceAsString()]);
            throw $exception;
        }
    }

    public function syncPlansFromZoho(): int
    {
        $count = 0;
        foreach ($this->getPlans() as $plan) {
            $price = $this->extractPlanPrice($plan);
            $intervalCount = (int) Arr::get($plan, 'interval', Arr::get($plan, 'recurring_interval', 1));
            if ($intervalCount < 1) {
                $intervalCount = 1;
            }

            $intervalUnit = (string) Arr::get($plan, 'interval_unit', Arr::get($plan, 'recurring_interval_unit', 'months'));
            $currencyCode = (string) Arr::get($plan, 'currency_code', Arr::get($plan, 'price_brackets.0.currency_code', 'USD'));

            Log::info('Syncing Zoho membership plan', [
                'plan_code' => Arr::get($plan, 'plan_code'),
                'recurring_price' => Arr::get($plan, 'recurring_price'),
                'price_bracket_price' => Arr::get($plan, 'price_brackets.0.price'),
                'resolved_price' => $price,
                'interval_count' => $intervalCount,
                'interval_unit' => $intervalUnit,
                'currency_code' => $currencyCode,
            ]);

            MembershipPlan::updateOrCreate(['zoho_plan_code' => Arr::get($plan, 'plan_code')], [
                'name' => Arr::get($plan, 'name'),
                'description' => Arr::get($plan, 'description'),
                'price' => $price,
                'interval_count' => $intervalCount,
                'interval_unit' => $intervalUnit,
                'currency_code' => $currencyCode,
                'status' => Arr::get($plan, 'status', 'active'),
                'raw_response_json' => $plan,
            ]);
            $count++;
        }

        return $count;
    }

    private function extractPlanPrice(array $plan): float
    {
        $recurringPrice = Arr::get($plan, 'recurring_price');
        if (is_numeric($recurringPrice)) {
            return (float) $recurringPrice;
        }

        $priceBracketPrice = Arr::get($plan, 'price_brackets.0.price');
        if (is_numeric($priceBracketPrice)) {
            return (float) $priceBracketPrice;
        }

        $basePrice = Arr::get($plan, 'price');
        if (is_numeric($basePrice)) {
            return (float) $basePrice;
        }

        return 0.0;
    }
}

<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoBillingService
{
    public function getAccessToken(): string
    {
        return Cache::remember('zoho_access_token', 3300, function (): string {
            $response = Http::asForm()->post(config('zoho.accounts_url'), [
                'refresh_token' => config('zoho.refresh_token'), 'client_id' => config('zoho.client_id'), 'client_secret' => config('zoho.client_secret'), 'grant_type' => 'refresh_token', 'redirect_uri' => config('zoho.redirect_uri'),
            ]);
            if (! $response->successful()) {
                Log::error('Zoho token generation failed', ['body' => $response->body()]);
                throw new RuntimeException('Unable to generate Zoho access token.');
            }
            return (string) Arr::get($response->json(), 'access_token');
        });
    }

    protected function client() { return Http::withToken($this->getAccessToken())->acceptJson()->baseUrl(rtrim((string) config('zoho.base_url'), '/'))->withHeaders(['X-com-zoho-subscriptions-organizationid' => config('zoho.org_id')]); }
    public function getPlans(): array { return Arr::get($this->client()->get('/plans')->throw()->json(), 'plans', []); }
    public function getPlanByCode(string $planCode): ?array { foreach ($this->getPlans() as $plan) { if ((string) Arr::get($plan, 'plan_code') === $planCode) return $plan; } return null; }
    public function createCustomer(array $payload): array { return $this->client()->post('/customers', $payload)->throw()->json(); }
    public function createHostedCheckoutPage(array $payload): array { Log::info('Creating Zoho hosted checkout', ['payload' => Arr::except($payload, ['card'])]); return $this->client()->post('/hostedpages/newsubscription', $payload)->throw()->json(); }
    public function getSubscription(string $id): array { return $this->client()->get("/subscriptions/{$id}")->throw()->json(); }
    public function cancelSubscription(string $id): array { return $this->client()->post("/subscriptions/{$id}/cancel")->throw()->json(); }
    public function getInvoice(string $id): array { return $this->client()->get("/invoices/{$id}")->throw()->json(); }
    public function getCustomer(string $id): array { return $this->client()->get("/customers/{$id}")->throw()->json(); }
    public function verifyWebhook(?string $token): bool { return hash_equals((string) config('zoho.webhook_token'), (string) $token); }

    public function syncPlansFromZoho(): int
    {
        $count = 0;
        foreach ($this->getPlans() as $plan) {
            MembershipPlan::updateOrCreate(['zoho_plan_code' => Arr::get($plan, 'plan_code')], [
                'name' => Arr::get($plan, 'name'), 'description' => Arr::get($plan, 'description'), 'price' => Arr::get($plan, 'price', 0), 'interval_count' => Arr::get($plan, 'interval', 1), 'interval_unit' => Arr::get($plan, 'interval_unit', 'months'), 'currency_code' => Arr::get($plan, 'currency_code', 'INR'), 'status' => Arr::get($plan, 'status', 'active'), 'raw_response_json' => $plan,
            ]); $count++;
        }
        return $count;
    }
}

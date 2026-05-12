<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\UserSubscription;
use App\Services\ZohoBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    public function __construct(private ZohoBillingService $zoho) {}

    public function plans(): JsonResponse
    {
        try {
            if (! MembershipPlan::exists()) {
                $this->zoho->syncPlansFromZoho();
            }

            return response()->json(['success' => true, 'message' => 'Plans fetched', 'data' => ['plans' => MembershipPlan::where('status', 'active')->get()]]);
        } catch (\Throwable $exception) {
            Log::error('Membership plans fetch failed', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'errors' => []], 500);
        }
    }

    public function syncPlans(): JsonResponse
    {
        try {
            $count = $this->zoho->syncPlansFromZoho();

            return response()->json(['success' => true, 'message' => 'Plans synced', 'data' => ['count' => $count]]);
        } catch (\Throwable $exception) {
            Log::error('Membership plans sync failed', ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'errors' => []], 500);
        }
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate(['plan_code' => ['required', 'string']]);
        $user = $request->user();
        $plan = MembershipPlan::where('zoho_plan_code', $validated['plan_code'])->where('status', 'active')->first();
        if (! $plan) return response()->json(['success'=>false,'message'=>'Invalid plan code','errors'=>['plan_code']],422);
        if ($user->hasActiveMembership()) return response()->json(['success'=>false,'message'=>'User already has active subscription','errors'=>[]],422);

        $hosted = $this->zoho->createHostedCheckoutPage(['plan' => ['plan_code' => $plan->zoho_plan_code], 'customer' => ['email' => $user->email, 'display_name' => $user->name ?: $user->first_name], 'redirect_url' => config('app.url')]);
        $hp = Arr::get($hosted, 'hostedpage', []);
        UserSubscription::create(['user_id'=>$user->id,'membership_plan_id'=>$plan->id,'zoho_hostedpage_id'=>Arr::get($hp,'hostedpage_id'),'status'=>'pending','amount'=>$plan->price,'currency_code'=>$plan->currency_code,'raw_response_json'=>$hosted]);
        Log::info('Checkout created', ['user_id' => $user->id, 'hostedpage_id' => Arr::get($hp, 'hostedpage_id')]);
        return response()->json(['success'=>true,'message'=>'Checkout created','data'=>['checkout_url'=>Arr::get($hp,'url'),'hostedpage_id'=>Arr::get($hp,'hostedpage_id')]]);
    }

    public function status(Request $request): JsonResponse { $user=$request->user(); return response()->json(['success'=>true,'message'=>'Subscription status fetched','data'=>['has_active_subscription'=>$user->hasActiveMembership(),'subscription'=>$user->activeSubscription()]]); }
    public function history(Request $request): JsonResponse { $subs=UserSubscription::with('payments','plan')->where('user_id',$request->user()->id)->latest()->get(); return response()->json(['success'=>true,'message'=>'Subscription history fetched','data'=>['subscriptions'=>$subs]]); }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription();
        if (! $subscription || ! $subscription->zoho_subscription_id) return response()->json(['success'=>false,'message'=>'No active subscription found','errors'=>[]],422);
        $this->zoho->cancelSubscription($subscription->zoho_subscription_id);
        $subscription->update(['status'=>'cancelled','cancelled_at'=>now()]);
        return response()->json(['success'=>true,'message'=>'Subscription cancelled','data'=>[]]);
    }
}

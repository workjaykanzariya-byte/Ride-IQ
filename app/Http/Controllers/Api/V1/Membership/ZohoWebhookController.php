<?php
namespace App\Http\Controllers\Api\V1\Membership;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\UserSubscription;
use App\Models\ZohoWebhookLog;
use App\Services\ZohoBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ZohoWebhookController extends Controller
{
    public function __construct(private ZohoBillingService $zoho) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->zoho->verifyWebhook($request->header('X-Zoho-Webhook-Token'))) return response()->json(['success'=>false,'message'=>'Invalid webhook token','errors'=>[]],401);
        $payload = $request->all(); $event=(string)Arr::get($payload,'event_type','unknown');
        ZohoWebhookLog::create(['event_type'=>$event,'webhook_data'=>$payload,'status'=>'received','created_at'=>now()]);
        $subId = Arr::get($payload,'subscription.subscription_id');
        $sub = UserSubscription::where('zoho_subscription_id',$subId)->orWhere('zoho_hostedpage_id',Arr::get($payload,'hosted_page.hostedpage_id'))->latest()->first();
        if ($sub) {
            $statusMap=['subscription_activated'=>'active','subscription_cancelled'=>'cancelled','payment_failed'=>'payment_failed','subscription_renewed'=>'active'];
            if (isset($statusMap[$event])) $sub->update(['status'=>$statusMap[$event],'end_date'=>Arr::get($payload,'subscription.next_billing_at',$sub->end_date),'next_billing_at'=>Arr::get($payload,'subscription.next_billing_at',$sub->next_billing_at),'raw_response_json'=>$payload,'zoho_subscription_id'=>$subId ?: $sub->zoho_subscription_id]);
            if (in_array($event,['payment_success','payment_failed','refund_created'], true)) {
                PaymentTransaction::updateOrCreate(['transaction_reference'=>Arr::get($payload,'payment.payment_id',Arr::get($payload,'invoice.invoice_id'))],['user_id'=>$sub->user_id,'subscription_id'=>$sub->id,'payment_gateway'=>'zoho','payment_status'=>$event,'amount'=>Arr::get($payload,'payment.amount',0),'currency_code'=>Arr::get($payload,'payment.currency_code','INR'),'payment_method'=>Arr::get($payload,'payment.payment_method'),'paid_at'=>now(),'raw_response_json'=>$payload]);
            }
        }
        Log::info('Zoho webhook processed', ['event' => $event, 'subscription_id' => $subId]);
        return response()->json(['success'=>true,'message'=>'Webhook processed','data'=>[]]);
    }
}

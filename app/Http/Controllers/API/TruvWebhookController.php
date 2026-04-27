<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TruvWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = (string) ($payload['event'] ?? $payload['event_type'] ?? 'unknown');
        $linkId = (string) ($payload['link_id'] ?? data_get($payload, 'data.link_id', ''));

        Log::info('Truv webhook received', [
            'event' => $eventType,
            'link_id' => $linkId,
            'payload' => $payload,
        ]);

        if ($linkId !== '') {
            $linkedAccount = LinkedAccount::query()->firstWhere([
                'provider' => LinkedAccount::PROVIDER_TRUV,
                'link_id' => $linkId,
            ]);

            if ($linkedAccount) {
                $status = match ($eventType) {
                    'task.completed', 'link.updated' => LinkedAccount::STATUS_CONNECTED,
                    'task.failed' => LinkedAccount::STATUS_FAILED,
                    'link.deleted' => LinkedAccount::STATUS_DISCONNECTED,
                    default => null,
                };

                if ($status !== null) {
                    $linkedAccount->update([
                        'status' => $status,
                        'is_connected' => $status === LinkedAccount::STATUS_CONNECTED,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed',
            'data' => [],
        ]);
    }
}

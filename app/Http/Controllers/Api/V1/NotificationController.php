<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return $this->success('Notifications list', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_ids' => ['required', 'array'],
            'notification_ids.*' => ['integer'],
        ]);

        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['notification_ids'])
            ->update(['is_read' => true]);

        return $this->success('Notifications marked as read');
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $limit = (int) ($request->query('limit', 10));

            $notifications = $request->user()
                ->notifications()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'type' => class_basename($notification->type),
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ]);

            return ResponseHelper::jsonResponse(true, 'Notifications Retrieved Successfully', $notifications, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function unreadCount(Request $request)
    {
        try {
            $count = $request->user()->unreadNotifications()->count();

            return ResponseHelper::jsonResponse(true, 'Unread Notification Count Retrieved Successfully', ['count' => $count], 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function markAsRead(Request $request, string $id)
    {
        try {
            $notification = $request->user()->notifications()->findOrFail($id);
            $notification->markAsRead();

            return ResponseHelper::jsonResponse(true, 'Notification Marked As Read', null, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function markAllAsRead(Request $request)
    {
        try {
            $request->user()->unreadNotifications->markAsRead();

            return ResponseHelper::jsonResponse(true, 'All Notifications Marked As Read', null, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}

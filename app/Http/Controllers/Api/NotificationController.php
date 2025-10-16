<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends BaseApiController
{
    /**
     * Display a listing of notifications
     */
    public function index(Request $request)
    {
        $query = Notification::with(['user', 'admin']);

        // Filter by user if specified
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        // Filter by type
        if ($request->type) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();
        
        return $this->sendResponse($notifications, 'Notifications berhasil diambil');
    }

    /**
     * Store a newly created notification
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'admin_id' => 'nullable|exists:admins,id',
            'title' => 'required|string|max:100',
            'message' => 'required|string',
            'type' => 'required|in:appointment,payment,ambulance,room_booking,general',
            'is_read' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $notification = Notification::create($request->all());

        return $this->sendResponse($notification->load(['user', 'admin']), 'Notification berhasil dibuat', 201);
    }

    /**
     * Display the specified notification
     */
    public function show($id)
    {
        $notification = Notification::with(['user', 'admin'])->find($id);

        if (!$notification) {
            return $this->sendError('Notification tidak ditemukan', [], 404);
        }

        return $this->sendResponse($notification, 'Notification berhasil diambil');
    }

    /**
     * Update the specified notification
     */
    public function update(Request $request, $id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return $this->sendError('Notification tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,id',
            'admin_id' => 'sometimes|nullable|exists:admins,id',
            'title' => 'sometimes|string|max:100',
            'message' => 'sometimes|string',
            'type' => 'sometimes|in:appointment,payment,ambulance,room_booking,general',
            'is_read' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $notification->update($request->all());

        return $this->sendResponse($notification->fresh()->load(['user', 'admin']), 'Notification berhasil diperbarui');
    }

    /**
     * Remove the specified notification
     */
    public function destroy($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return $this->sendError('Notification tidak ditemukan', [], 404);
        }

        $notification->delete();

        return $this->sendResponse([], 'Notification berhasil dihapus');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return $this->sendError('Notification tidak ditemukan', [], 404);
        }

        $notification->update(['is_read' => true]);

        return $this->sendResponse($notification->fresh(), 'Notification marked as read');
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId)
    {
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return $this->sendError('User tidak ditemukan', [], 404);
        }

        $updated = Notification::where('user_id', $userId)
                               ->where('is_read', false)
                               ->update(['is_read' => true]);

        return $this->sendResponse(
            ['updated_count' => $updated], 
            "All notifications marked as read for user"
        );
    }

    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $count = Notification::where('user_id', $request->user_id)
                            ->where('is_read', false)
                            ->count();

        return $this->sendResponse(['unread_count' => $count], 'Unread count berhasil diambil');
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:100',
            'message' => 'required|string',
            'type' => 'required|in:appointment,payment,ambulance,room_booking,general',
            'admin_id' => 'nullable|exists:admins,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $notifications = [];
        foreach ($request->user_ids as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'admin_id' => $request->admin_id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Notification::insert($notifications);

        return $this->sendResponse(
            ['sent_count' => count($notifications)], 
            'Bulk notifications sent successfully'
        );
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Notification::where('user_id', $user->id);

        // Filter by type if provided
        if ($request->has('type') && in_array($request->type, [
            Notification::TYPE_INFO,
            Notification::TYPE_SUCCESS,
            Notification::TYPE_WARNING,
            Notification::TYPE_ERROR
        ])) {
            $query->byType($request->type);
        }

        // Filter by read status
        if ($request->has('read_status')) {
            if ($request->read_status === 'unread') {
                $query->unread();
            } elseif ($request->read_status === 'read') {
                $query->read();
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'message' => 'Notifications retrieved successfully'
        ]);
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount(): JsonResponse
    {
        $user = Auth::user();
        
        $unreadCount = Notification::where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $unreadCount],
            'message' => 'Unread count retrieved successfully'
        ]);
    }

    /**
     * Get recent notifications (last 10)
     */
    public function getRecent(): JsonResponse
    {
        $user = Auth::user();
        
        $recentNotifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recentNotifications,
            'message' => 'Recent notifications retrieved successfully'
        ]);
    }

    /**
     * Get a specific notification
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        // Mark as read when viewed
        if ($notification->isUnread()) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => $notification,
            'message' => 'Notification retrieved successfully'
        ]);
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead($id): JsonResponse
    {
        $user = Auth::user();
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'data' => $notification,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark a specific notification as unread
     */
    public function markAsUnread($id): JsonResponse
    {
        $user = Auth::user();
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsUnread();

        return response()->json([
            'success' => true,
            'data' => $notification,
            'message' => 'Notification marked as unread'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        
        $updatedCount = Notification::where('user_id', $user->id)
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'data' => ['updated_count' => $updatedCount],
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'integer|exists:notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        $updatedCount = Notification::whereIn('id', $request->notification_ids)
            ->where('user_id', $user->id)
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'data' => ['updated_count' => $updatedCount],
            'message' => 'Selected notifications marked as read'
        ]);
    }

    /**
     * Delete a specific notification
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Delete multiple notifications
     */
    public function deleteMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'integer|exists:notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        $deletedCount = Notification::whereIn('id', $request->notification_ids)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['deleted_count' => $deletedCount],
            'message' => 'Selected notifications deleted successfully'
        ]);
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead(): JsonResponse
    {
        $user = Auth::user();
        
        $deletedCount = Notification::where('user_id', $user->id)
            ->read()
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['deleted_count' => $deletedCount],
            'message' => 'All read notifications deleted successfully'
        ]);
    }

    /**
     * Get notification statistics
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        
        $stats = [
            'total' => Notification::where('user_id', $user->id)->count(),
            'unread' => Notification::where('user_id', $user->id)->unread()->count(),
            'read' => Notification::where('user_id', $user->id)->read()->count(),
            'by_type' => [
                'info' => Notification::where('user_id', $user->id)->byType(Notification::TYPE_INFO)->count(),
                'success' => Notification::where('user_id', $user->id)->byType(Notification::TYPE_SUCCESS)->count(),
                'warning' => Notification::where('user_id', $user->id)->byType(Notification::TYPE_WARNING)->count(),
                'error' => Notification::where('user_id', $user->id)->byType(Notification::TYPE_ERROR)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Notification statistics retrieved successfully'
        ]);
    }

    /**
     * Create a new notification (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can create notifications.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:' . implode(',', [
                Notification::TYPE_INFO,
                Notification::TYPE_SUCCESS,
                Notification::TYPE_WARNING,
                Notification::TYPE_ERROR
            ]),
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $notification = Notification::createForUser(
            $request->user_id,
            $request->title,
            $request->message,
            $request->type,
            $request->data
        );

        return response()->json([
            'success' => true,
            'data' => $notification,
            'message' => 'Notification created successfully'
        ], 201);
    }

    /**
     * Send bulk notifications (Admin only)
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can send bulk notifications.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:' . implode(',', [
                Notification::TYPE_INFO,
                Notification::TYPE_SUCCESS,
                Notification::TYPE_WARNING,
                Notification::TYPE_ERROR
            ]),
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $notifications = [];
            foreach ($request->user_ids as $userId) {
                $notifications[] = Notification::createForUser(
                    $userId,
                    $request->title,
                    $request->message,
                    $request->type,
                    $request->data
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'sent_count' => count($notifications),
                    'notifications' => $notifications
                ],
                'message' => 'Bulk notifications sent successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications'
            ], 500);
        }
    }

    /**
     * Send notification to all users by role (Admin only)
     */
    public function sendToRole(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can send role-based notifications.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|in:' . implode(',', [
                User::ROLE_USER,
                User::ROLE_LANDLORD,
                User::ROLE_AGENT,
                User::ROLE_ADMIN
            ]),
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:' . implode(',', [
                Notification::TYPE_INFO,
                Notification::TYPE_SUCCESS,
                Notification::TYPE_WARNING,
                Notification::TYPE_ERROR
            ]),
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get all users with the specified role
        $users = User::byRole($request->role)->active()->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active users found with the specified role'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $notifications = [];
            foreach ($users as $targetUser) {
                $notifications[] = Notification::createForUser(
                    $targetUser->id,
                    $request->title,
                    $request->message,
                    $request->type,
                    $request->data
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'sent_count' => count($notifications),
                    'target_role' => $request->role
                ],
                'message' => "Notifications sent to all {$request->role}s successfully"
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to send role-based notifications'
            ], 500);
        }
    }
}
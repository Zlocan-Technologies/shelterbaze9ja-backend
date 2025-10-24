<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $factory = (new Factory)->withServiceAccount(storage_path('app/firebase/service-account.json'));
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase Messaging', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send push notification to a single device token
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('Push notification sent successfully', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send push notification to multiple device tokens
     */
    public function sendToMultipleTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            $notification = Notification::create($title, $body);

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification($notification)
                        ->withData($data);

                    $this->messaging->send($message);
                    $results['success']++;

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'token' => substr($token, 0, 20) . '...',
                        'error' => $e->getMessage()
                    ];

                    Log::error('Failed to send push notification to token', [
                        'token' => substr($token, 0, 20) . '...',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Bulk push notifications completed', [
                'total' => count($tokens),
                'success' => $results['success'],
                'failed' => $results['failed']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send bulk push notifications', [
                'error' => $e->getMessage()
            ]);

            $results['failed'] = count($tokens);
            $results['errors'][] = ['general_error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Send push notification to a user by user ID
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                Log::error('User not found for push notification', ['user_id' => $userId]);
                return false;
            }

            if (empty($user->fcm_token)) {
                Log::warning('User has no FCM token', ['user_id' => $userId]);
                return false;
            }

            return $this->sendToToken($user->fcm_token, $title, $body, $data);

        } catch (\Exception $e) {
            Log::error('Failed to send push notification to user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send push notification to multiple users by user IDs
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $results = [
            'total_users' => count($userIds),
            'users_with_tokens' => 0,
            'users_without_tokens' => 0,
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];

        try {
            $users = User::whereIn('id', $userIds)
                ->whereNotNull('fcm_token')
                ->get();

            $results['users_with_tokens'] = $users->count();
            $results['users_without_tokens'] = count($userIds) - $users->count();

            if ($users->isEmpty()) {
                Log::warning('No users with FCM tokens found', ['user_ids' => $userIds]);
                return $results;
            }

            $tokens = $users->pluck('fcm_token')->toArray();
            $sendResults = $this->sendToMultipleTokens($tokens, $title, $body, $data);

            $results['sent'] = $sendResults['success'];
            $results['failed'] = $sendResults['failed'];
            $results['details'] = $sendResults['errors'];

            Log::info('Push notifications sent to multiple users', [
                'total_users' => count($userIds),
                'users_with_tokens' => $results['users_with_tokens'],
                'sent' => $results['sent'],
                'failed' => $results['failed']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send push notifications to users', [
                'user_ids' => $userIds,
                'error' => $e->getMessage()
            ]);

            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Send push notification to all users with a specific role
     */
    public function sendToUsersByRole(string $role, string $title, string $body, array $data = []): array
    {
        try {
            $users = User::byRole($role)
                ->active()
                ->whereNotNull('fcm_token')
                ->get();

            if ($users->isEmpty()) {
                Log::info('No active users with FCM tokens found for role', ['role' => $role]);
                return [
                    'total_users' => 0,
                    'sent' => 0,
                    'failed' => 0
                ];
            }

            $userIds = $users->pluck('id')->toArray();
            return $this->sendToUsers($userIds, $title, $body, $data);

        } catch (\Exception $e) {
            Log::error('Failed to send push notifications to users by role', [
                'role' => $role,
                'error' => $e->getMessage()
            ]);

            return [
                'total_users' => 0,
                'sent' => 0,
                'failed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send push notification to a topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('Push notification sent to topic', [
                'topic' => $topic,
                'title' => $title
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send push notification to topic', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Subscribe tokens to a topic
     */
    public function subscribeToTopic(array $tokens, string $topic): array
    {
        try {
            $result = $this->messaging->subscribeToTopic($topic, $tokens);

            Log::info('Tokens subscribed to topic', [
                'topic' => $topic,
                'token_count' => count($tokens)
            ]);

            return [
                'success' => true,
                'topic' => $topic,
                'token_count' => count($tokens)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to subscribe tokens to topic', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Unsubscribe tokens from a topic
     */
    public function unsubscribeFromTopic(array $tokens, string $topic): array
    {
        try {
            $result = $this->messaging->unsubscribeFromTopic($topic, $tokens);

            Log::info('Tokens unsubscribed from topic', [
                'topic' => $topic,
                'token_count' => count($tokens)
            ]);

            return [
                'success' => true,
                'topic' => $topic,
                'token_count' => count($tokens)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe tokens from topic', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

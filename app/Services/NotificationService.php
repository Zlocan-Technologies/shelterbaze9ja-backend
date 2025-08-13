<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendEmailNotification;
use App\Jobs\SendSMSNotification;

class NotificationService
{
    /**
     * Create in-app notification
     */
    public function createInAppNotification(int $userId, string $title, string $message, string $type = 'info', array $data = null): ?Notification
    {
        try {
            $notification = Notification::createForUser($userId, $title, $message, $type, $data);

            Log::info('In-app notification created', [
                'user_id' => $userId,
                'title' => $title,
                'type' => $type,
                'notification_id' => $notification->id
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Failed to create in-app notification', [
                'user_id' => $userId,
                'title' => $title,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create multiple in-app notifications
     */
    public function createBulkInAppNotifications(array $userIds, string $title, string $message, string $type = 'info', array $data = null): array
    {
        $created = [];
        $failed = [];

        foreach ($userIds as $userId) {
            $notification = $this->createInAppNotification($userId, $title, $message, $type, $data);
            
            if ($notification) {
                $created[] = $notification;
            } else {
                $failed[] = $userId;
            }
        }

        Log::info('Bulk in-app notifications processed', [
            'total_users' => count($userIds),
            'created' => count($created),
            'failed' => count($failed)
        ]);

        return [
            'created' => $created,
            'failed' => $failed,
            'stats' => [
                'total' => count($userIds),
                'success' => count($created),
                'failed' => count($failed)
            ]
        ];
    }

    /**
     * Send email notification
     */
    public function sendEmail(string $to, string $subject, string $message, array $data = [], bool $queued = true): bool
    {
        try {
            $emailData = [
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'data' => $data,
                'sent_at' => now()
            ];

            if ($queued) {
                // Send via queue for better performance
                Queue::push(new SendEmailNotification($emailData));
                Log::info('Email notification queued', ['to' => $to, 'subject' => $subject]);
            } else {
                // Send immediately
                Mail::send('emails.notification', $emailData, function ($mail) use ($to, $subject) {
                    $mail->to($to)->subject($subject);
                });
                Log::info('Email notification sent immediately', ['to' => $to, 'subject' => $subject]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send email to user by ID
     */
    public function sendEmailToUser(int $userId, string $subject, string $message, array $data = [], bool $queued = true): bool
    {
        try {
            $user = User::find($userId);
            
            if (!$user) {
                Log::error('User not found for email notification', ['user_id' => $userId]);
                return false;
            }

            $data['user'] = $user->toArray();
            return $this->sendEmail($user->email, $subject, $message, $data, $queued);

        } catch (\Exception $e) {
            Log::error('Failed to send email to user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send SMS notification
     */
    public function sendSMS(string $phoneNumber, string $message, bool $queued = true): bool
    {
        try {
            // Ensure phone number is in correct format
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);

            $smsData = [
                'phone' => $phoneNumber,
                'message' => $message,
                'sent_at' => now()
            ];

            if ($queued) {
                Queue::push(new SendSMSNotification($smsData));
                Log::info('SMS notification queued', ['phone' => $phoneNumber]);
            } else {
                // Send immediately using your SMS provider
                $this->sendSMSImmediate($phoneNumber, $message);
                Log::info('SMS notification sent immediately', ['phone' => $phoneNumber]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send SMS notification', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send SMS to user by ID
     */
    public function sendSMSToUser(int $userId, string $message, bool $queued = true): bool
    {
        try {
            $user = User::find($userId);
            
            if (!$user || !$user->phone_number) {
                Log::error('User not found or no phone number for SMS', ['user_id' => $userId]);
                return false;
            }

            return $this->sendSMS($user->phone_number, $message, $queued);

        } catch (\Exception $e) {
            Log::error('Failed to send SMS to user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send complete notification (in-app + email + SMS)
     */
    public function sendCompleteNotification(int $userId, string $title, string $message, array $options = []): array
    {
        $results = [];

        // Default options
        $options = array_merge([
            'in_app' => true,
            'email' => false,
            'sms' => false,
            'type' => 'info',
            'data' => null,
            'email_subject' => $title,
            'queued' => true
        ], $options);

        // Create in-app notification
        if ($options['in_app']) {
            $inAppResult = $this->createInAppNotification($userId, $title, $message, $options['type'], $options['data']);
            $results['in_app'] = $inAppResult ? 'success' : 'failed';
        }

        // Send email
        if ($options['email']) {
            $emailResult = $this->sendEmailToUser($userId, $options['email_subject'], $message, $options['data'] ?? [], $options['queued']);
            $results['email'] = $emailResult ? 'success' : 'failed';
        }

        // Send SMS
        if ($options['sms']) {
            $smsResult = $this->sendSMSToUser($userId, $message, $options['queued']);
            $results['sms'] = $smsResult ? 'success' : 'failed';
        }

        Log::info('Complete notification sent', [
            'user_id' => $userId,
            'title' => $title,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Send notification to users by role
     */
    public function sendToUsersByRole(string $role, string $title, string $message, array $options = []): array
    {
        try {
            $users = User::byRole($role)->active()->get();

            if ($users->isEmpty()) {
                Log::info('No active users found for role', ['role' => $role]);
                return [
                    'total' => 0,
                    'sent' => 0,
                    'failed' => 0
                ];
            }

            $results = [
                'total' => $users->count(),
                'sent' => 0,
                'failed' => 0,
                'details' => []
            ];

            foreach ($users as $user) {
                $userResults = $this->sendCompleteNotification($user->id, $title, $message, $options);
                $results['details'][$user->id] = $userResults;

                // Check if at least one notification method succeeded
                $hasSuccess = in_array('success', array_values($userResults));
                if ($hasSuccess) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
            }

            Log::info('Role-based notifications sent', [
                'role' => $role,
                'results' => $results
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to send role-based notifications', [
                'role' => $role,
                'error' => $e->getMessage()
            ]);

            return [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send property-related notification
     */
    public function sendPropertyNotification(int $propertyId, string $title, string $message, array $recipients = [], array $options = []): array
    {
        try {
            // Get property details for context
            $property = \App\Models\Property::with(['landlord', 'agent'])->find($propertyId);
            
            if (!$property) {
                throw new \Exception('Property not found');
            }

            // Add property data to notification
            $options['data'] = array_merge($options['data'] ?? [], [
                'property_id' => $propertyId,
                'property_title' => $property->title,
                'property_address' => $property->location_address
            ]);

            $results = [];

            // Send to specific recipients if provided
            if (!empty($recipients)) {
                foreach ($recipients as $userId) {
                    $results[$userId] = $this->sendCompleteNotification($userId, $title, $message, $options);
                }
            } else {
                // Send to property landlord and agent by default
                if ($property->landlord) {
                    $results[$property->landlord->id] = $this->sendCompleteNotification($property->landlord->id, $title, $message, $options);
                }

                if ($property->agent) {
                    $results[$property->agent->id] = $this->sendCompleteNotification($property->agent->id, $title, $message, $options);
                }
            }

            Log::info('Property notification sent', [
                'property_id' => $propertyId,
                'recipients_count' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to send property notification', [
                'property_id' => $propertyId,
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format phone number for SMS
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Add country code if not present (assuming Nigeria +234)
        if (strlen($phoneNumber) === 11 && substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '234' . substr($phoneNumber, 1);
        } elseif (strlen($phoneNumber) === 10) {
            $phoneNumber = '234' . $phoneNumber;
        }

        return '+' . $phoneNumber;
    }

    /**
     * Send SMS immediately (implement with your SMS provider)
     */
    private function sendSMSImmediate(string $phoneNumber, string $message): bool
    {
        try {
            // Implement your SMS provider logic here
            // Example using Twilio, Nexmo, or local Nigerian SMS providers
            
            // For now, we'll just log it
            Log::info('SMS would be sent', [
                'phone' => $phoneNumber,
                'message' => $message
            ]);

            // Return true when actual SMS implementation is added
            return true;

        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get notification templates
     */
    public function getTemplate(string $templateName, array $data = []): array
    {
        $templates = [
            'welcome' => [
                'title' => 'Welcome to Shelterbaze',
                'message' => 'Welcome to Shelterbaze, {name}! We\'re excited to help you find your perfect home.',
            ],
            'profile_completed' => [
                'title' => 'Profile Completed',
                'message' => 'Your profile has been completed and is under review. You\'ll be notified once verified.',
            ],
            'profile_verified' => [
                'title' => 'Profile Verified',
                'message' => 'Congratulations! Your profile has been verified. You can now access all features.',
            ],
            'payment_received' => [
                'title' => 'Payment Received',
                'message' => 'Your payment of ₦{amount} for {property} has been received and is being processed.',
            ],
            'new_message' => [
                'title' => 'New Message',
                'message' => 'You have a new message from {sender} about {property}.',
            ]
        ];

        $template = $templates[$templateName] ?? null;

        if ($template && !empty($data)) {
            // Replace placeholders with actual data
            foreach ($data as $key => $value) {
                $template['title'] = str_replace('{' . $key . '}', $value, $template['title']);
                $template['message'] = str_replace('{' . $key . '}', $value, $template['message']);
            }
        }

        return $template;
    }
}
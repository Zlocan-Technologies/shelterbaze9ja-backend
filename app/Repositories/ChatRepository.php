<?php

namespace App\Repositories;

use App\Http\Requests\Chat\SearchConversationRequest;
use App\Http\Requests\Chat\StartConversationRequest;
use App\Http\Requests\Chat\UpdateConversationRequest;
use App\Models\ChatConversation;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use App\Util\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatRepository
{
    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations(Request $request)
    {
        $user = $request->user();
        $conversations = [];

        // Get conversations based on user role
        switch ($user->role) {
            case User::ROLE_USER:
                $conversations = ChatConversation::where('user_id', $user->id)
                    ->with(['property:id,title,rent_amount', 'landlord:id,first_name,last_name', 'agent:id,first_name,last_name'])
                    ->orderBy('last_message_at', 'desc')
                    ->get();
                break;

            case User::ROLE_LANDLORD:
                $conversations = ChatConversation::where('landlord_id', $user->id)
                    ->with(['property:id,title,rent_amount', 'user:id,first_name,last_name', 'agent:id,first_name,last_name'])
                    ->orderBy('last_message_at', 'desc')
                    ->get();
                break;

            case User::ROLE_AGENT:
                $conversations = ChatConversation::where('agent_id', $user->id)
                    ->with(['property:id,title,rent_amount', 'user:id,first_name,last_name', 'landlord:id,first_name,last_name'])
                    ->orderBy('last_message_at', 'desc')
                    ->get();
                break;
        }

        return ApiResponse::respond(
            message: 'Conversations retrieved successfully',
            data: $conversations
        );
    }


    /**
     * Get a specific conversation with its participants
     */
    public function getConversation(Request $request, $conversationId)
    {
        $user = $request->user();

        // Verify user has access to this conversation
        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->with([
                'property:id,title,rent_amount,location_address',
                'user:id,first_name,last_name,role',
                'landlord:id,first_name,last_name,role',
                'agent:id,first_name,last_name,role'
            ])
            ->first();

        if (!$conversation) {
            return ApiResponse::respond(
                message: 'Conversation not found or access denied',
                status: false,
                statusCode: 404
            );
        }

        return ApiResponse::respond(
            message: 'Conversation retrieved successfully',
            data: [
                'conversation' => $conversation,
                'participants' => $conversation->getParticipants()
            ]
        );
    }


    public function startConversation(StartConversationRequest $request)
    {
        $user = $request->user();
        $property = Property::find($request->property_id);

        // Check if user has paid engagement fee
        if (!$property->hasUserPaidEngagementFee($user->id)) {
            return ApiResponse::respond(
                message: 'You must pay the engagement fee before starting a conversation',
                status: false,
                statusCode: 403
            );
        }

        // Check if conversation already exists
        $existingConversation = ChatConversation::where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingConversation) {
            return ApiResponse::respond(
                message: 'Conversation already exists for this property',
                status: false,
                statusCode: 409,
                data: ['conversation_id' => $existingConversation->id]
            );
        }

        DB::beginTransaction();
        try {
            // Create conversation
            $conversation = ChatConversation::create([
                'property_id' => $property->id,
                'user_id' => $user->id,
                'landlord_id' => $property->landlord_id,
                'agent_id' => $property->agent_id,
                'status' => ChatConversation::STATUS_ACTIVE,
                'last_message_at' => now()
            ]);

            // Send notifications to landlord and agent
            $this->sendNewConversationNotifications($conversation, $user, $request->message);

            DB::commit();

            return ApiResponse::respond(
                message: 'Conversation started successfully',
                status: true,
                statusCode: 201,
                data: $conversation->load([
                    'property:id,title,rent_amount',
                    'landlord:id,first_name,last_name',
                    'agent:id,first_name,last_name'
                ])
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateConversation(UpdateConversationRequest $request, $conversationId)
    {
        $user = $request->user();

        // Verify user has access to this conversation
        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return ApiResponse::respond(
                message: 'Conversation not found or access denied',
                status: false,
                statusCode: 404
            );
        }

        // Update conversation
        $updateData = [];
        if ($request->has('status')) {
            $updateData['status'] = $request->status;
        }

        if (!empty($updateData)) {
            $conversation->update($updateData);
        }

        // Send notifications if status changed
        if ($request->has('status')) {
            $this->sendStatusChangeNotifications($conversation, $user, $request->status);
        }

        return ApiResponse::respond(
            message: 'Conversation updated successfully',
            data: $conversation->fresh()
        );
    }

    public function closeConversation(Request $request, $conversationId)
    {
        $user = $request->user();

        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                // Only landlord, agent, or admin can close conversations
                $query->where('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return ApiResponse::respond(
                message: 'Conversation not found or access denied',
                status: false,
                statusCode: 404
            );
        }

        $conversation->close();

        // Notify participants
        $participants = $conversation->getParticipants();
        foreach ($participants as $participant) {
            if ($participant->id !== $user->id) {
                Notification::createForUser(
                    $participant->id,
                    'Conversation Closed',
                    'A conversation for ' . $conversation->property->title . ' has been closed.',
                    Notification::TYPE_INFO,
                    ['conversation_id' => $conversation->id]
                );
            }
        }

        return ApiResponse::respond(
            message: 'Conversation closed successfully',
            data: $conversation->fresh()
        );
    }

    public function reopenConversation(Request $request, $conversationId)
    {
        $user = $request->user();

        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return ApiResponse::respond(
                message: 'Conversation not found or access denied',
                status: false,
                statusCode: 404
            );
        }

        $conversation->reopen();

        return ApiResponse::respond(
            message: 'Conversation reopened successfully',
            data: $conversation->fresh()
        );
    }


    public function getConversationsStats(Request $request)
    {
        $user = $request->user();

        $query = ChatConversation::query();

        switch ($user->role) {
            case User::ROLE_USER:
                $query->where('user_id', $user->id);
                break;
            case User::ROLE_LANDLORD:
                $query->where('landlord_id', $user->id);
                break;
            case User::ROLE_AGENT:
                $query->where('agent_id', $user->id);
                break;
        }

        $stats = [
            'total' => $query->count(),
            'active' => (clone $query)->active()->count(),
            'closed' => (clone $query)->closed()->count(),
        ];

        return ApiResponse::respond(
            message: 'Conversation stats retrieved successfully',
            data: $stats
        );
    }

    public function searchConversations(SearchConversationRequest $request)
    {
        $user = $request->user();
        $searchTerm = $request->search;

        $query = ChatConversation::query();

        // Filter by user role
        switch ($user->role) {
            case User::ROLE_USER:
                $query->where('user_id', $user->id);
                break;
            case User::ROLE_LANDLORD:
                $query->where('landlord_id', $user->id);
                break;
            case User::ROLE_AGENT:
                $query->where('agent_id', $user->id);
                break;
        }

        // Search in property titles and participant names
        $conversations = $query->whereHas('property', function ($q) use ($searchTerm) {
            $q->where('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('location_address', 'LIKE', "%{$searchTerm}%");
        })
            ->orWhereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            })
            ->orWhereHas('landlord', function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            })
            ->orWhereHas('agent', function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            })
            ->with(['property:id,title,rent_amount', 'user:id,first_name,last_name', 'landlord:id,first_name,last_name', 'agent:id,first_name,last_name'])
            ->orderBy('last_message_at', 'desc')
            ->get();

    
        return ApiResponse::respond(
            data: $conversations,
            message: 'Search results retrieved successfully'
        );
    }

    /**
     * Send notifications when a new conversation is started
     */
    private function sendNewConversationNotifications(ChatConversation $conversation, User $sender, string $initialMessage): void
    {
        $property = $conversation->property;

        // Notify landlord
        if ($conversation->landlord_id && $conversation->landlord_id !== $sender->id) {
            Notification::createForUser(
                $conversation->landlord_id,
                'New Conversation Started',
                "{$sender->full_name} is interested in your property: {$property->title}",
                Notification::TYPE_INFO,
                [
                    'conversation_id' => $conversation->id,
                    'property_id' => $property->id,
                    'initial_message' => substr($initialMessage, 0, 100) . (strlen($initialMessage) > 100 ? '...' : '')
                ]
            );
        }

        // Notify agent if assigned
        if ($conversation->agent_id && $conversation->agent_id !== $sender->id) {
            Notification::createForUser(
                $conversation->agent_id,
                'New Conversation Started',
                "{$sender->full_name} started a conversation about {$property->title}",
                Notification::TYPE_INFO,
                [
                    'conversation_id' => $conversation->id,
                    'property_id' => $property->id,
                    'initial_message' => substr($initialMessage, 0, 100) . (strlen($initialMessage) > 100 ? '...' : '')
                ]
            );
        }
    }

    /**
     * Send notifications when conversation status changes
     */
    private function sendStatusChangeNotifications(ChatConversation $conversation, User $updatedBy, string $newStatus): void
    {
        $participants = $conversation->getParticipants();
        $property = $conversation->property;

        $statusMessage = $newStatus === ChatConversation::STATUS_CLOSED ? 'closed' : 'reopened';

        foreach ($participants as $participant) {
            if ($participant->id !== $updatedBy->id) {
                Notification::createForUser(
                    $participant->id,
                    'Conversation ' . ucfirst($statusMessage),
                    "The conversation for {$property->title} has been {$statusMessage} by {$updatedBy->full_name}.",
                    Notification::TYPE_INFO,
                    ['conversation_id' => $conversation->id, 'property_id' => $property->id]
                );
            }
        }
    }
}

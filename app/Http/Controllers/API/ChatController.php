<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\Property;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations(): JsonResponse
    {
        $user = Auth::user();
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

        return response()->json([
            'success' => true,
            'data' => $conversations,
            'message' => 'Conversations retrieved successfully'
        ]);
    }

    /**
     * Get a specific conversation with its participants
     */
    public function getConversation(Request $request, $conversationId): JsonResponse
    {
        $user = Auth::user();
        
        // Verify user has access to this conversation
        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->with([
                'property:id,title,rent_amount,location_address,primary_image',
                'user:id,first_name,last_name,role',
                'landlord:id,first_name,last_name,role',
                'agent:id,first_name,last_name,role'
            ])
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or access denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'participants' => $conversation->getParticipants()
            ],
            'message' => 'Conversation retrieved successfully'
        ]);
    }

    /**
     * Start a new conversation (usually initiated by user after paying engagement fee)
     */
    public function startConversation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'message' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $property = Property::find($request->property_id);

        // Check if user has paid engagement fee
        if (!$property->hasUserPaidEngagementFee($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You must pay the engagement fee before starting a conversation'
            ], 403);
        }

        // Check if conversation already exists
        $existingConversation = ChatConversation::where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingConversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation already exists for this property',
                'data' => ['conversation_id' => $existingConversation->id]
            ], 409);
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

            return response()->json([
                'success' => true,
                'data' => $conversation->load([
                    'property:id,title,rent_amount',
                    'landlord:id,first_name,last_name',
                    'agent:id,first_name,last_name'
                ]),
                'message' => 'Conversation started successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to start conversation'
            ], 500);
        }
    }

    /**
     * Update conversation status or add notes
     */
    public function updateConversation(Request $request, $conversationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:' . ChatConversation::STATUS_ACTIVE . ',' . ChatConversation::STATUS_CLOSED,
            'notes' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Verify user has access to this conversation
        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or access denied'
            ], 404);
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

        return response()->json([
            'success' => true,
            'data' => $conversation,
            'message' => 'Conversation updated successfully'
        ]);
    }

    /**
     * Close a conversation
     */
    public function closeConversation(Request $request, $conversationId): JsonResponse
    {
        $user = Auth::user();

        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                // Only landlord, agent, or admin can close conversations
                $query->where('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or you do not have permission to close it'
            ], 404);
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

        return response()->json([
            'success' => true,
            'message' => 'Conversation closed successfully'
        ]);
    }

    /**
     * Reopen a closed conversation
     */
    public function reopenConversation(Request $request, $conversationId): JsonResponse
    {
        $user = Auth::user();

        $conversation = ChatConversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('landlord_id', $user->id)
                    ->orWhere('agent_id', $user->id);
            })
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found or you do not have permission to reopen it'
            ], 404);
        }

        $conversation->reopen();

        return response()->json([
            'success' => true,
            'message' => 'Conversation reopened successfully'
        ]);
    }

    /**
     * Get conversations count by status
     */
    public function getConversationsStats(): JsonResponse
    {
        $user = Auth::user();
        
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

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Conversation stats retrieved successfully'
        ]);
    }

    /**
     * Search conversations
     */
    public function searchConversations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
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

        return response()->json([
            'success' => true,
            'data' => $conversations,
            'message' => 'Search results retrieved successfully'
        ]);
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
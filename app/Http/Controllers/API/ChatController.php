<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SearchConversationRequest;
use App\Http\Requests\Chat\StartConversationRequest;
use App\Http\Requests\Chat\UpdateConversationRequest;
use App\Models\ChatConversation;
use App\Models\Property;
use App\Models\User;
use App\Models\Notification;
use App\Repositories\ChatRepository;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct(
        private ChatRepository $chatRepository
    ) {}

    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations(Request $request): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->getConversations($request));
    }

    /**
     * Get a specific conversation with its participants
     */
    public function getConversation(Request $request, $conversationId): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->getConversation($request, $conversationId));
    }

    /**
     * Start a new conversation (usually initiated by user after paying engagement fee)
     */
    public function startConversation(StartConversationRequest $request): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->startConversation($request));
    }

    /**
     * Update conversation status or add notes
     */
    public function updateConversation(UpdateConversationRequest $request, $conversationId): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->updateConversation($request, $conversationId));
    }

    /**
     * Close a conversation
     */
    public function closeConversation(Request $request, $conversationId): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->closeConversation($request, $conversationId));
    }

    /**
     * Reopen a closed conversation
     */
    public function reopenConversation(Request $request, $conversationId): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->reopenConversation($request, $conversationId));
    }

    /**
     * Get conversations count by status
     */
    public function getConversationsStats(Request $request): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->getConversationsStats($request));
    }

    /**
     * Search conversations
     */
    public function searchConversations(SearchConversationRequest $request): JsonResponse
    {
        return (new ResponseHandler())->execute(fn() => $this->chatRepository->searchConversations($request));
    }

}
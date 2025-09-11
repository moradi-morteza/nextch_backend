<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($conversationId)
    {
        $currentUser = Auth::user();
        $conversation = Conversation::with(['messages', 'starter', 'recipient'])
            ->where('id', $conversationId)
            ->where(function($query) use ($currentUser) {
                $query->where('starter_id', $currentUser->id)
                      ->orWhere('recipient_id', $currentUser->id);
            })
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json([
            'success' => true,
            'conversation' => $conversation
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Conversation $conversation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Conversation $conversation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation)
    {
        //
    }

    /**
     * Store draft message and conversation
     */
    public function storeDraftMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|string',
            'starter_id' => 'required|exists:users,id',
            'recipient_id' => 'required|exists:users,id',
            'recipient_data' => 'required|array',
            'message' => 'required|array',
            'message.type' => 'required|string',
            'message.body' => 'required|string',
            'message.sender_id' => 'required|exists:users,id',
        ]);

        // Check if conversation exists, if not create it
        $conversation = Conversation::find($validated['conversation_id']);
        
        if (!$conversation) {
            $conversation = Conversation::create([
                'id' => $validated['conversation_id'],
                'starter_id' => $validated['starter_id'],
                'recipient_id' => $validated['recipient_id'],
                'status' => 'draft'
            ]);
        }

        // Create the message
        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'sender_id' => $validated['message']['sender_id'],
            'type' => $validated['message']['type'],
            'body' => $validated['message']['body'],
            'status' => 'draft'
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'message' => $message
        ], 201);
    }

    /**
     * Send conversation (change status from draft to pending)
     */
    public function sendConversation(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id'
        ]);

        $conversation = Conversation::find($validated['conversation_id']);
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Update conversation status to pending
        $conversation->update(['status' => 'pending']);

        // Update all draft messages to pending
        $conversation->messages()->where('status', 'draft')->update(['status' => 'pending']);

        return response()->json([
            'success' => true,
            'conversation' => $conversation->fresh()
        ]);
    }

    /**
     * Get conversation persons (grouped by person) with pagination
     */
    public function getConversationPersons(Request $request)
    {
        $request->validate([
            'type' => 'required|in:received,sent',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ]);

        $currentUser = Auth::user();
        $type = $request->type;
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        // Build query based on type
        $conversationsQuery = Conversation::with(['messages' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }, 'starter', 'recipient'])
            ->when($type === 'received', function($query) use ($currentUser) {
                return $query->where('recipient_id', $currentUser->id)
                            ->whereIn('status', ['pending', 'answered', 'closed']); // Exclude drafts for recipients
            })
            ->when($type === 'sent', function($query) use ($currentUser) {
                return $query->where('starter_id', $currentUser->id);
            });

        // Group conversations by person and get statistics
        $persons = [];
        $conversations = $conversationsQuery->get();

        foreach ($conversations as $conversation) {
            $personId = $type === 'received' ? $conversation->starter_id : $conversation->recipient_id;
            $person = $type === 'received' ? $conversation->starter : $conversation->recipient;

            if (!$person) continue;

            if (!isset($persons[$personId])) {
                $persons[$personId] = [
                    'id' => $person->id,
                    'name' => $person->full_name,
                    'username' => $person->username,
                    'avatar' => $person->avatar,
                    'conversation_counts' => [
                        'draft' => 0,
                        'pending' => 0,
                        'answered' => 0,
                        'closed' => 0
                    ],
                    'last_message' => null,
                    'last_message_time' => null,
                    'total_conversations' => 0
                ];
            }

            // Count conversations by status
            $persons[$personId]['conversation_counts'][$conversation->status]++;
            $persons[$personId]['total_conversations']++;

            // Update last message info
            $lastMessage = $conversation->messages->first();
            if ($lastMessage && (
                !$persons[$personId]['last_message_time'] || 
                $lastMessage->created_at > $persons[$personId]['last_message_time']
            )) {
                $persons[$personId]['last_message'] = $lastMessage->body;
                $persons[$personId]['last_message_time'] = $lastMessage->created_at;
            }
        }

        // Sort by last message time and paginate
        $personsArray = array_values($persons);
        usort($personsArray, function($a, $b) {
            return $b['last_message_time'] <=> $a['last_message_time'];
        });

        $total = count($personsArray);
        $offset = ($page - 1) * $perPage;
        $paginatedPersons = array_slice($personsArray, $offset, $perPage);

        return response()->json([
            'success' => true,
            'data' => $paginatedPersons,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ]);
    }

    /**
     * Get conversations with specific person
     */
    public function getPersonConversations(Request $request, $personId)
    {
        $request->validate([
            'type' => 'required|in:received,sent',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ]);

        $currentUser = Auth::user();
        $type = $request->type;
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        // Get person data
        $person = User::find($personId);
        if (!$person) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        // Build conversations query
        $conversationsQuery = Conversation::with(['messages' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }, 'starter', 'recipient'])
            ->when($type === 'received', function($query) use ($currentUser, $personId) {
                return $query->where('recipient_id', $currentUser->id)
                            ->where('starter_id', $personId)
                            ->whereIn('status', ['pending', 'answered', 'closed']); // Exclude drafts for recipients
            })
            ->when($type === 'sent', function($query) use ($currentUser, $personId) {
                return $query->where('starter_id', $currentUser->id)
                            ->where('recipient_id', $personId);
            })
            ->orderBy('updated_at', 'desc');

        $conversations = $conversationsQuery->paginate($perPage, ['*'], 'page', $page);

        // Add additional data to conversations
        $conversations->getCollection()->transform(function ($conversation) {
            $lastMessage = $conversation->messages->first();
            $conversation->last_message = $lastMessage ? $lastMessage->body : null;
            $conversation->message_count = $conversation->messages()->count();
            return $conversation;
        });

        return response()->json([
            'success' => true,
            'person' => [
                'id' => $person->id,
                'name' => $person->full_name,
                'username' => $person->username,
                'avatar' => $person->avatar
            ],
            'conversations' => $conversations
        ]);
    }

    /**
     * Answer a conversation (recipient sends reply)
     */
    public function answerConversation(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|array',
            'message.type' => 'required|string',
            'message.body' => 'required|string'
        ]);

        $currentUser = Auth::user();
        $conversation = Conversation::find($validated['conversation_id']);
        
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Only recipient can answer
        if ($conversation->recipient_id !== $currentUser->id) {
            return response()->json(['error' => 'Only recipient can answer this conversation'], 403);
        }

        // Only pending conversations can be answered
        if ($conversation->status !== 'pending') {
            return response()->json(['error' => 'This conversation cannot be answered'], 400);
        }

        // Create the answer message
        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'sender_id' => $currentUser->id,
            'type' => $validated['message']['type'],
            'body' => $validated['message']['body'],
            'status' => 'sent'
        ]);

        // Update conversation status to answered
        $conversation->update(['status' => 'answered']);

        return response()->json([
            'success' => true,
            'conversation' => $conversation->fresh(),
            'message' => $message
        ], 201);
    }
}

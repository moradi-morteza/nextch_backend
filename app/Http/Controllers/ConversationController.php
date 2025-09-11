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
    public function show(Conversation $conversation)
    {
        //
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
}

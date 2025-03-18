<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UUser;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Events\NewMessage;
use Exception;

class ConversationController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations(Request $request)
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            
            $conversations = Conversation::forUser($user->id, $userType)
                ->with(['lastMessage'])
                ->withCount(['messages as unread_count' => function($query) use ($user, $userType) {
                    $query->where('is_read', false)
                          ->where('sender_id', '!=', $user->id)
                          ->where('sender_type', '!=', $userType);
                }])
                ->orderBy('last_message_at', 'desc')
                ->get();
            
            // Add user info to conversations
            $conversations->map(function($conversation) use ($user, $userType) {
                if ($conversation->initiator_id == $user->id && $conversation->initiator_type == $userType) {
                    $otherUser = $this->getUser($conversation->receiver_id, $conversation->receiver_type);
                    $conversation->other_user = $otherUser;
                } else {
                    $otherUser = $this->getUser($conversation->initiator_id, $conversation->initiator_type);
                    $conversation->other_user = $otherUser;
                }
                
                return $conversation;
            });
            
            return response()->json([
                'status' => 'success',
                'data' => $conversations
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get or create a conversation with another user
     */
    public function getOrCreateConversation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'user_type' => 'required|in:user,superadmin',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $currentUser = $request->user();
            $currentUserType = $this->getUserType($currentUser);
            $otherUserId = $request->user_id;
            $otherUserType = $request->user_type;

            // Check if other user exists
            $otherUser = $this->getUser($otherUserId, $otherUserType);
            if (!$otherUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            // Try to find existing conversation
            $conversation = Conversation::where(function($query) use ($currentUser, $currentUserType, $otherUserId, $otherUserType) {
                $query->where('initiator_id', $currentUser->id)
                      ->where('initiator_type', $currentUserType)
                      ->where('receiver_id', $otherUserId)
                      ->where('receiver_type', $otherUserType);
            })->orWhere(function($query) use ($currentUser, $currentUserType, $otherUserId, $otherUserType) {
                $query->where('initiator_id', $otherUserId)
                      ->where('initiator_type', $otherUserType)
                      ->where('receiver_id', $currentUser->id)
                      ->where('receiver_type', $currentUserType);
            })->first();

            // If no conversation exists, create one
            if (!$conversation) {
                $conversation = Conversation::create([
                    'initiator_id' => $currentUser->id,
                    'initiator_type' => $currentUserType,
                    'receiver_id' => $otherUserId,
                    'receiver_type' => $otherUserType,
                    'last_message_at' => now()
                ]);
            }

            // Load messages
            $messages = $conversation->messages;
            
            // Mark messages as read
            $conversation->messages()
                ->where('sender_id', '!=', $currentUser->id)
                ->where('sender_type', '!=', $currentUserType)
                ->where('is_read', false)
                ->update(['is_read' => true]);
            
            // Add user info
            $conversation->other_user = $otherUser;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'other_user' => $otherUser
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get or create conversation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $validator = Validator::make($request->all(), [
                'conversation_id' => 'required|integer|exists:conversations,id',
                'message' => 'required_without:attachment|string|nullable',
                'attachment' => 'nullable|file|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $currentUser = $request->user();
            $currentUserType = $this->getUserType($currentUser);
            $conversationId = $request->conversation_id;

            // Get conversation and check if user is part of it
            $conversation = Conversation::where('id', $conversationId)
                ->where(function($query) use ($currentUser, $currentUserType) {
                    $query->where('initiator_id', $currentUser->id)
                          ->where('initiator_type', $currentUserType)
                          ->orWhere(function($q) use ($currentUser, $currentUserType) {
                              $q->where('receiver_id', $currentUser->id)
                                ->where('receiver_type', $currentUserType);
                          });
                })->first();

            if (!$conversation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Conversation not found or you are not a participant'
                ], 404);
            }

            // Handle attachment
            $attachmentPath = null;
            $attachmentType = null;
            $attachmentName = null;
            
            if ($request->hasFile('attachment')) {
                $attachment = $request->file('attachment');
                $extension = $attachment->getClientOriginalExtension();
                $attachmentName = $attachment->getClientOriginalName();
                
                // Determine attachment type
                $fileType = $this->getFileType($extension);
                $attachmentType = $fileType;
                
                // Store file
                $attachmentPath = $attachment->store('attachments', 'public');
            }

            // Create message
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => $currentUser->id,
                'sender_type' => $currentUserType,
                'message' => $request->message,
                'attachment' => $attachmentPath,
                'attachment_type' => $attachmentType,
                'attachment_name' => $attachmentName,
                'is_read' => false
            ]);

            // Update conversation last message time
            $conversation->last_message_at = now();
            $conversation->save();

            // Get recipient for notification
            $recipient = null;
            $recipientType = null;
            
            if ($conversation->initiator_id == $currentUser->id && $conversation->initiator_type == $currentUserType) {
                $recipient = $conversation->receiver_id;
                $recipientType = $conversation->receiver_type;
            } else {
                $recipient = $conversation->initiator_id;
                $recipientType = $conversation->initiator_type;
            }

            // Broadcast event for real-time update
            event(new NewMessage($message, $recipient, $recipientType));
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Message sent successfully',
                'data' => $message
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'conversation_id' => 'required|integer|exists:conversations,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $currentUser = $request->user();
            $currentUserType = $this->getUserType($currentUser);
            $conversationId = $request->conversation_id;

            // Get conversation and check if user is part of it
            $conversation = Conversation::where('id', $conversationId)
                ->where(function($query) use ($currentUser, $currentUserType) {
                    $query->where('initiator_id', $currentUser->id)
                          ->where('initiator_type', $currentUserType)
                          ->orWhere(function($q) use ($currentUser, $currentUserType) {
                              $q->where('receiver_id', $currentUser->id)
                                ->where('receiver_type', $currentUserType);
                          });
                })->first();

            if (!$conversation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Conversation not found or you are not a participant'
                ], 404);
            }

            // Mark messages as read
            $updatedCount = $conversation->messages()
                ->where('sender_id', '!=', $currentUser->id)
                ->where('sender_type', '!=', $currentUserType)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Messages marked as read',
                'data' => [
                    'updated_count' => $updatedCount
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to determine user type
     */
    private function getUserType($user)
    {
        if ($user instanceof UUser) {
            return 'user';
        } elseif ($user instanceof SuperAdmin) {
            return 'superadmin';
        }
        
        return null;
    }

    /**
     * Helper method to get user by id and type
     */
    private function getUser($userId, $userType)
    {
        if ($userType === 'user') {
            return UUser::find($userId);
        } elseif ($userType === 'superadmin') {
            return SuperAdmin::find($userId);
        }
        
        return null;
    }

    /**
     * Helper method to determine file type
     */
    private function getFileType($extension)
    {
        $extension = strtolower($extension);
        
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
        $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];
        $audioTypes = ['mp3', 'wav', 'ogg', 'aac'];
        
        if (in_array($extension, $imageTypes)) {
            return 'image';
        } elseif (in_array($extension, $documentTypes)) {
            return 'document';
        } elseif (in_array($extension, $videoTypes)) {
            return 'video';
        } elseif (in_array($extension, $audioTypes)) {
            return 'audio';
        }
        
        return 'file';
    }
}
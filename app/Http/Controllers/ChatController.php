<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatMessages;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChatController extends Controller
{
    public function index($receiverId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $messages = ChatMessages::with(['sender:id,first_name,last_name,avatar', 'receiver:id,first_name,last_name,avatar'])
            ->where(function ($query) use ($user, $receiverId) {
                $query->where('sender_id', $user->id)
                    ->where('receiver_id', $receiverId);
            })
            ->orWhere(function ($query) use ($user, $receiverId) {
                $query->where('sender_id', $receiverId)
                    ->where('receiver_id', $user->id);
            })
            ->select('id', 'sender_id', 'receiver_id', 'message', 'created_at')
            ->orderBy('id', 'asc') // Đảm bảo sắp xếp tin nhắn theo thời gian
            ->get();

        return formatResponse(STATUS_OK, $messages, '', 'Get messages successfully');
    }

    public function store($receiverId, Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$receiverId) {
            return formatResponse(STATUS_FAIL, '', '', 'Missing parameter receiverId', CODE_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:1000',
            'image_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), __('messages.validation_error'));
        }
        $receiver = User::find($receiverId);
        if (!$receiver) {
            return formatResponse(STATUS_FAIL, '', '', 'Receiver not found', CODE_NOT_FOUND);
        }

        $messageData = [
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'message' => $request->input('message') ?: '',
            'image_url' => $request->input('image_url') ?: null,
        ];
        $message = ChatMessages::create($messageData);

        Log::info("Broadcasting MessageSent event from User {$user->id} to User {$receiverId}");
        // Sự kiện broadcast sẽ tự động đính kèm người gửi và người nhận nếu đã được định nghĩa trong broadcastWith()
        broadcast(new MessageSent($message))->toOthers();
        Log::info("MessageSent event dispatched for message ID: {$message->id}");

        return formatResponse(STATUS_OK, $message, '', 'Create message successfully');
    }

    public function getUsers()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $users = User::where('id', '!=', $user->id)
            ->where(function ($query) use ($user) {
                $query->whereIn('id', function ($q) use ($user) {
                    $q->select('sender_id')
                        ->from('chat_messages')
                        ->where('receiver_id', $user->id);
                })
                    ->orWhereIn('id', function ($q) use ($user) {
                        $q->select('receiver_id')
                            ->from('chat_messages')
                            ->where('sender_id', $user->id);
                    });
            })
            ->get(['id', 'first_name', 'last_name', 'avatar', 'email']);

        // Attach latest message for each user
        $users->each(function ($otherUser) use ($user) {
            $latestMessage = ChatMessages::where(function ($query) use ($user, $otherUser) {
                $query->where('sender_id', $user->id)
                    ->where('receiver_id', $otherUser->id);
            })
                ->orWhere(function ($query) use ($user, $otherUser) {
                    $query->where('sender_id', $otherUser->id)
                        ->where('receiver_id', $user->id);
                })
                ->latest('id')
                ->first();

            $otherUser->latest_message = $latestMessage ? $latestMessage->message : null;
            $otherUser->latest_message_time = $latestMessage ? $latestMessage->created_at->diffForHumans() : null;
        });
        $sortedUsers = $users->sortByDesc('latest_message_time')->values();
        return formatResponse(STATUS_OK, $users, '', 'Get users successfully');
    }


    public function getUserId($id)
    {
        $user = User::find($id);
        if ($user) {
            return formatResponse(STATUS_OK, $user, '', 'Get user successfully');
        }
        return formatResponse(STATUS_FAIL, '', 'User not found');
    }


    public function uploadChatImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), 'Validation failed');
        }

        $user = JWTAuth::parseToken()->authenticate();

        $path = $request->file('image')->storePublicly('chat-images', 's3');

        if ($path) {
            $imageUrl = env('URL_IMAGE_S3') . $path;
            return formatResponse(STATUS_OK, ['image_url' => $imageUrl], '', 'Image uploaded successfully');
        }

        return formatResponse(STATUS_FAIL, '', '', 'Image upload failed', CODE_BAD);
    }

    public function deleteChatImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return formatResponse(STATUS_FAIL, '', $validator->errors(), 'Validation failed');
        }

        $imageUrl = $request->input('image_url');
        $user = JWTAuth::parseToken()->authenticate();

        // Chuyển đổi URL thành đường dẫn trong S3
        $filePath = str_replace(env('URL_IMAGE_S3'), '', $imageUrl);

        if (Storage::disk('s3')->exists($filePath)) {
            Storage::disk('s3')->delete($filePath);
            return formatResponse(STATUS_OK, '', '', 'Image deleted successfully');
        }

        return formatResponse(STATUS_FAIL, '', '', 'Image not found', CODE_NOT_FOUND);
    }

}

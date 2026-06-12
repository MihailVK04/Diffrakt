<?php
 
declare(strict_types=1);
 
namespace Diffrakt\Controllers;
 
use Diffrakt\Core\Request;
use Diffrakt\Core\Response;
use Diffrakt\Core\Validator;
use Diffrakt\Models\Conversation;
use Diffrakt\Models\Message;
use Diffrakt\Models\User;
 
class ChatController {
 
    private const PAGE_LIMIT = 30;

    public function listConversations(Request $request): void {
        $userId = $request->userId();
 
        $conversations = Conversation::listForUser($userId);
 
        $formatted = array_map(function (array $c) {
            return [
                'id'                => (int) $c['id'],
                'created_at'        => $c['created_at'],
                'other_user'        => [
                    'id'         => (int) $c['other_user_id'],
                    'username'   => $c['other_username'],
                    'avatar_url' => $c['other_avatar_path']
                        ? 'api/v1/files?path=' . urlencode($c['other_avatar_path'])
                        : null,
                ],
                'last_message_body' => $c['last_message_body'],
                'last_message_at'   => $c['last_message_at'],
            ];
        }, $conversations);
 
        Response::json(['conversations' => $formatted]);
    }

    public function createConversation(Request $request): void {
        $userId = $request->userId();
        $body   = $request->body();
 
        $errors = Validator::validate($body, [
            'username' => ['required'],
        ]);
 
        if (!empty($errors)) {
            Response::unprocessable($errors);
        }
 
        $other = User::findByUsername($body['username']);
 
        if (!$other) {
            Response::notFound('User not found.');
        }
 
        $otherId = (int) $other['id'];
 
        if ($otherId === $userId) {
            Response::badRequest('You cannot open a conversation with yourself.');
        }
 
        if (!Conversation::isMutualFollow($userId, $otherId)) {
            Response::forbidden('You can only chat with users you mutually follow.');
        }
 
        $existing = Conversation::findByPair($userId, $otherId);
 
        if ($existing) {
            Response::json(['conversation' => $this->formatConversation($existing, $userId)], 200);
            return;
        }
 
        $id = Conversation::create($userId, $otherId);
        $conversation = Conversation::findById($id);
 
        Response::json(['conversation' => $this->formatConversation($conversation, $userId)], 201);
    }

    public function getMessages(Request $request): void {
        $userId         = $request->userId();
        $conversationId = (int) ($request->params['id'] ?? 0);
 
        $conversation = $this->resolveConversation($conversationId, $userId);
 
        $afterId = $request->query('after') !== null
            ? (int) $request->query('after')
            : null;

        if ($afterId !== null) {
            $messages = Message::getAfter($conversationId, $afterId);
            Response::json(['messages' => $this->formatMessages($messages)]);
            return;
        }

        $cursor   = $request->query('cursor') !== null ? (int) $request->query('cursor') : null;
        $messages = Message::getPage($conversationId, $cursor, self::PAGE_LIMIT);
 
        $nextCursor = null;
        if (count($messages) === self::PAGE_LIMIT) {
            $nextCursor = (int) end($messages)['id'];
        }

        $messages = array_reverse($messages);
 
        Response::json([
            'messages'    => $this->formatMessages($messages),
            'next_cursor' => $nextCursor,
        ]);
    }

    public function sendMessage(Request $request): void {
        $userId         = $request->userId();
        $conversationId = (int) ($request->params['id'] ?? 0);
 
        $this->resolveConversation($conversationId, $userId);
 
        $body = $request->body();
 
        $errors = Validator::validate($body, [
            'body' => ['required', 'max_length:2000'],
        ]);
 
        if (!empty($errors)) {
            Response::unprocessable($errors);
        }
 
        $messageId = Message::create($conversationId, $userId, trim($body['body']));
        $message   = Message::findById($messageId);
 
        Response::json(['message' => $this->formatMessage($message)], 201);
    }

    private function resolveConversation(int $conversationId, int $userId): array {
        if ($conversationId === 0) {
            Response::notFound('Conversation not found.');
        }
 
        $conversation = Conversation::findById($conversationId);
 
        if (!$conversation) {
            Response::notFound('Conversation not found.');
        }
 
        if ((int) $conversation['user_a_id'] !== $userId && (int) $conversation['user_b_id'] !== $userId) {
            Response::forbidden('You are not a participant in this conversation.');
        }
 
        return $conversation;
    }
 
    private function formatConversation(array $c, int $viewerId): array {
        $otherId = (int) $c['user_a_id'] === $viewerId
            ? (int) $c['user_b_id']
            : (int) $c['user_a_id'];
 
        $other = User::findById($otherId);
 
        return [
            'id'         => (int) $c['id'],
            'created_at' => $c['created_at'],
            'other_user' => [
                'id'         => $otherId,
                'username'   => $other['username'] ?? null,
                'avatar_url' => !empty($other['avatar_path'])
                    ? 'api/v1/files?path=' . urlencode($other['avatar_path'])
                    : null,
            ],
        ];
    }
 
    private function formatMessages(array $messages): array {
        return array_map([$this, 'formatMessage'], $messages);
    }
 
    private function formatMessage(array $m): array {
        return [
            'id'              => (int) $m['id'],
            'conversation_id' => (int) $m['conversation_id'],
            'sender_id'       => (int) $m['sender_id'],
            'body'            => $m['body'],
            'created_at'      => $m['created_at'],
        ];
    }
}

?>
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiClient;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(protected AiClient $ai)
    {
    }

    public function chat(Request $request)
    {
        $data = $request->validate([
            'role' => 'required|string|in:teacher,parent',
            'message' => 'required|string|max:2000',
            'user_id' => 'nullable|integer',
        ]);

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($data['role']),
            ],
            [
                'role' => 'user',
                'content' => $data['message'],
            ],
        ];

        try {
            $reply = $this->ai->chat($messages);
        } catch (\Throwable $e) {
            return $this->error(
                message: 'AI service error.',
                status: 500,
                errors: config('app.debug') ? $e->getMessage() : null
            );
        }

        return $this->success([
            'reply' => $reply,
        ]);
    }

    protected function buildSystemPrompt(string $role): string
    {
        if ($role === 'teacher') {
            return <<<TXT
You are a helpful assistant for a school platform.
You help teachers with:
- Explaining how to use the platform
- Creating quiz ideas
- Interpreting basic student analytics
Avoid giving medical, legal, or financial advice.
If a question is out of scope, ask the user to contact the school admin.
TXT;
        }

        return <<<TXT
You are a helpful assistant for parents using a school platform.
You explain:
- How to see the child's grades and attendance
- How to communicate with teachers
- How to understand performance in simple language
Avoid giving medical, legal, or financial advice.
If a question is out of scope, ask the user to contact the school support.
TXT;
    }
}

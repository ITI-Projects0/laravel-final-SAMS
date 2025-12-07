<?php

namespace App\Services;

use OpenAI;
use RuntimeException;

class AiClient
{
    protected $client;
    protected string $model;
    protected ?string $key;

    public function __construct()
    {
        $this->key   = config('services.openai.key');
        $this->model = config('services.openai.chat_model', 'gpt-4o-mini');

        if (empty($this->key)) {
            throw new RuntimeException('OpenAI key is missing. Set OPENAI_API_KEY in .env.');
        }

        $this->client = OpenAI::client($this->key);
    }

    public function chat(array $messages): string
    {
        $response = $this->client->chat()->create([
            'model'    => $this->model,
            'messages' => $messages,
        ]);

        return $response->choices[0]->message->content ?? '';
    }
}

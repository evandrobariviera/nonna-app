<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiTokenUsage;
use Illuminate\Support\Facades\Http;

class AiService
{
    public function run(
        AiAgent $agent,
        string $userMessage,
        array $context = [],
        ?int $userId = null,
        ?string $clientId = null,
        ?string $trigger = null
    ): string {
        $agent->loadMissing('provider');

        $apiKey = $agent->resolvedApiKey();
        if (!$apiKey) {
            throw new \RuntimeException("Agente '{$agent->name}': nenhuma chave de API configurada para {$agent->provider->name}.");
        }

        $systemPrompt = $this->injectContext($agent->system_prompt, $context);

        [$responseText, $usage] = $this->dispatch($agent, $apiKey->getApiKey(), $systemPrompt, $userMessage);

        $this->logUsage($agent, $usage, $userId, $clientId, $trigger);

        return $responseText;
    }

    private function injectContext(string $prompt, array $context): string
    {
        foreach ($context as $key => $value) {
            $prompt = str_replace('{' . $key . '}', (string) $value, $prompt);
        }
        return $prompt;
    }

    private function dispatch(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        return match ($agent->provider->slug) {
            'openai', 'groq' => $this->callOpenAiCompat($agent, $apiKey, $systemPrompt, $userMessage),
            'anthropic'      => $this->callAnthropic($agent, $apiKey, $systemPrompt, $userMessage),
            'google'         => $this->callGoogle($agent, $apiKey, $systemPrompt, $userMessage),
            default          => throw new \RuntimeException("Provider '{$agent->provider->slug}' não suportado."),
        };
    }

    // OpenAI e Groq usam o mesmo formato de API
    private function callOpenAiCompat(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post(rtrim($agent->provider->base_url, '/') . '/chat/completions', [
                'model'       => $agent->model,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
                'temperature' => $agent->temperature,
                'max_tokens'  => $agent->max_tokens,
            ])
            ->throw()
            ->json();

        return [
            $response['choices'][0]['message']['content'] ?? '',
            [
                'prompt_tokens'     => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $response['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    private function callAnthropic(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(120)
            ->post(rtrim($agent->provider->base_url, '/') . '/messages', [
                'model'      => $agent->model,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_tokens' => $agent->max_tokens,
            ])
            ->throw()
            ->json();

        $in  = $response['usage']['input_tokens'] ?? 0;
        $out = $response['usage']['output_tokens'] ?? 0;

        return [
            $response['content'][0]['text'] ?? '',
            [
                'prompt_tokens'     => $in,
                'completion_tokens' => $out,
                'total_tokens'      => $in + $out,
            ],
        ];
    }

    private function callGoogle(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        $url = rtrim($agent->provider->base_url, '/') . "/models/{$agent->model}:generateContent?key={$apiKey}";

        $response = Http::timeout(120)
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                ],
                'generationConfig' => [
                    'temperature'     => $agent->temperature,
                    'maxOutputTokens' => $agent->max_tokens,
                ],
            ])
            ->throw()
            ->json();

        $in  = $response['usageMetadata']['promptTokenCount'] ?? 0;
        $out = $response['usageMetadata']['candidatesTokenCount'] ?? 0;

        return [
            $response['candidates'][0]['content']['parts'][0]['text'] ?? '',
            [
                'prompt_tokens'     => $in,
                'completion_tokens' => $out,
                'total_tokens'      => $in + $out,
            ],
        ];
    }

    private function logUsage(AiAgent $agent, array $usage, ?int $userId, ?string $clientId, ?string $trigger): void
    {
        $cost = $this->estimateCost($agent, $usage);

        AiTokenUsage::create([
            'agent_id'           => $agent->id,
            'user_id'            => $userId,
            'client_id'          => $clientId,
            'model'              => $agent->model,
            'provider'           => $agent->provider->slug,
            'prompt_tokens'      => $usage['prompt_tokens'],
            'completion_tokens'  => $usage['completion_tokens'],
            'total_tokens'       => $usage['total_tokens'],
            'estimated_cost_usd' => $cost,
            'trigger'            => $trigger,
        ]);
    }

    private function estimateCost(AiAgent $agent, array $usage): float
    {
        $models    = $agent->provider->models ?? [];
        $modelData = collect($models)->firstWhere('id', $agent->model);
        if (!$modelData) {
            return 0.0;
        }

        $inputCost  = ($usage['prompt_tokens'] / 1000) * ($modelData['input_per_1k'] ?? 0);
        $outputCost = ($usage['completion_tokens'] / 1000) * ($modelData['output_per_1k'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }
}

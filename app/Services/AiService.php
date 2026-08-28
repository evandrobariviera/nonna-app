<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\AiTokenUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * Transcreve um áudio curto (mp3/ogg/etc) via Whisper da OpenAI. Usa a chave
     * ativa do provider "openai" diretamente - não depende de um AiAgent configurado,
     * porque transcrição de mídia não é uma "skill" configurável, é infraestrutura
     * de ingestão (ver UazapiMessageIngestor).
     *
     * Whisper tem teto rígido de 25 MB — serve os áudios de WhatsApp, não gravações
     * de reunião. Para arquivos grandes use transcribeAudioLong() (ElevenLabs Scribe).
     */
    public function transcribeAudio(string $audioUrl): ?string
    {
        $provider = AiProvider::where('slug', 'openai')->first();
        $apiKey = $provider?->activeKey();
        if (!$apiKey) {
            return null;
        }

        $audio = Http::timeout(30)->get($audioUrl);
        if (!$audio->successful()) {
            return null;
        }

        $response = Http::withToken($apiKey->getApiKey())
            ->timeout(60)
            ->attach('file', $audio->body(), 'audio.mp3')
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
            ]);

        if (!$response->successful()) {
            Log::warning('AiService::transcribeAudio falhou', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json('text');
    }

    /**
     * Transcreve áudios longos (reuniões de 1-3h) via ElevenLabs Scribe. Diferente
     * do transcribeAudio()/Whisper: não baixa o arquivo pro servidor — manda a URL
     * direto pra ElevenLabs buscar (sem o teto de 25 MB) e pede diarização, que
     * ajuda o agente da ATA a separar quem falou o quê.
     *
     * Chamada síncrona: o Scribe batch processa ~10-20x tempo real, então uma
     * reunião de 2h volta em poucos minutos. Só rodar de dentro de um Job
     * (TranscribeMeetingAudioJob), nunca numa request web.
     */
    public function transcribeAudioLong(string $audioUrl): ?string
    {
        $provider = AiProvider::where('slug', 'elevenlabs')->first();
        $apiKey = $provider?->activeKey();
        if (!$apiKey) {
            Log::warning('AiService::transcribeAudioLong — provider "elevenlabs" sem chave ativa');
            return null;
        }

        $response = Http::withHeaders(['xi-api-key' => $apiKey->getApiKey()])
            ->connectTimeout(30)
            ->timeout(1140) // margem pra reunião de ~3h no batch do Scribe
            ->asMultipart()
            ->post('https://api.elevenlabs.io/v1/speech-to-text', [
                ['name' => 'model_id',          'contents' => 'scribe_v2'],
                ['name' => 'source_url',        'contents' => $audioUrl],
                ['name' => 'language_code',     'contents' => 'pt'],
                ['name' => 'diarize',           'contents' => 'true'],
                ['name' => 'tag_audio_events',  'contents' => 'false'],
            ]);

        if (!$response->successful()) {
            Log::warning('AiService::transcribeAudioLong falhou', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $text = $this->formatDiarizedTranscript($response->json() ?? []);

        return $text !== '' ? $text : null;
    }

    /**
     * Monta o texto final a partir da resposta do Scribe. Se houver mais de um
     * falante, prefixa cada bloco com "Falante N:"; senão devolve o texto corrido.
     */
    private function formatDiarizedTranscript(array $json): string
    {
        $words = $json['words'] ?? [];
        $flat  = trim($json['text'] ?? '');

        if (empty($words)) {
            return $flat;
        }

        $speakers = array_filter(array_unique(array_map(
            fn ($w) => $w['speaker_id'] ?? null,
            $words
        )));
        if (count($speakers) <= 1) {
            return $flat;
        }

        $labels  = [];
        $lines   = [];
        $current = null;
        $buffer  = '';

        $flush = function () use (&$lines, &$labels, &$current, &$buffer) {
            if ($current !== null && trim($buffer) !== '') {
                $label = $labels[$current] ??= 'Falante ' . (count($labels) + 1);
                $lines[] = $label . ': ' . trim($buffer);
            }
        };

        foreach ($words as $w) {
            $speaker = $w['speaker_id'] ?? $current ?? 'speaker_0';
            if ($speaker !== $current) {
                $flush();
                $current = $speaker;
                $buffer  = '';
            }
            $buffer .= $w['text'] ?? '';
        }
        $flush();

        return implode("\n\n", $lines);
    }
    public function chat(
        AiAgent $agent,
        array $history,
        array $context = [],
        ?int $userId = null,
        ?string $clientId = null,
    ): string {
        $agent->loadMissing('provider');

        $apiKey = $agent->resolvedApiKey();
        if (!$apiKey) {
            throw new \RuntimeException("Agente '{$agent->name}': nenhuma chave de API configurada para {$agent->provider->name}.");
        }

        $systemPrompt = $this->buildChatSystemPrompt($agent->system_prompt, $context);
        [$responseText, $usage] = $this->dispatchChat($agent, $apiKey->getApiKey(), $systemPrompt, $history);
        $this->logUsage($agent, $usage, $userId, $clientId, 'chat');

        return $responseText;
    }

    private function buildChatSystemPrompt(string $agentPrompt, array $context): string
    {
        if (empty($context)) {
            return $agentPrompt;
        }

        $labelMap = [
            'task_title'      => 'Tarefa',
            'task_description'=> 'Descrição',
            'task_type'       => 'Tipo',
            'task_status'     => 'Status',
            'task_situation'  => 'Situação',
            'client_name'     => 'Cliente',
            'client_segment'  => 'Segmento',
            'project_name'    => 'Projeto',
            'project_brief'   => 'Brief do Projeto',
            'executor_name'   => 'Executor',
            'macro_plan_name' => 'Macroplanejamento',
        ];

        $skip = ['task_id', 'project_id', 'campaign_id', 'client_id'];
        $lines = [];

        foreach ($context as $key => $value) {
            if (!empty($value) && !in_array($key, $skip)) {
                $label   = $labelMap[$key] ?? $key;
                $lines[] = "{$label}: {$value}";
            }
        }

        if (empty($lines)) {
            return $agentPrompt;
        }

        return $agentPrompt . "\n\n---\nCONTEXTO ATUAL:\n" . implode("\n", $lines);
    }

    private function dispatchChat(AiAgent $agent, string $apiKey, string $systemPrompt, array $history): array
    {
        return match ($agent->provider->slug) {
            'openai', 'groq' => $this->callOpenAiCompatChat($agent, $apiKey, $systemPrompt, $history),
            'anthropic'      => $this->callAnthropicChat($agent, $apiKey, $systemPrompt, $history),
            'google'         => $this->callGoogleChat($agent, $apiKey, $systemPrompt, $history),
            default          => throw new \RuntimeException("Provider '{$agent->provider->slug}' não suportado."),
        };
    }

    // Modelos "reasoning" da OpenAI (o1/o3/o4/gpt-5.x) usam um contrato de API diferente
    // na Chat Completions: rejeitam max_tokens (exigem max_completion_tokens) e rejeitam
    // temperature customizada (só aceitam o default, 1) — descoberto na prática tentando
    // rodar gpt-5.6-terra no agente de ATA. max_completion_tokens funciona igual em
    // modelos antigos também (testado com gpt-4o-mini), então não precisa de branch pra
    // esse parâmetro — só a omissão de temperature é condicional.
    private function isReasoningModel(string $model): bool
    {
        return (bool) preg_match('/^(o1|o3|o4|gpt-5)/', $model);
    }

    private function callOpenAiCompatChat(AiAgent $agent, string $apiKey, string $systemPrompt, array $history): array
    {
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history
        );

        $payload = [
            'model'                 => $agent->model,
            'messages'              => $messages,
            'max_completion_tokens' => $agent->max_tokens,
        ];
        if (!$this->isReasoningModel($agent->model)) {
            $payload['temperature'] = $agent->temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout(300)
            ->post(rtrim($agent->provider->base_url, '/') . '/chat/completions', $payload)
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

    private function callAnthropicChat(AiAgent $agent, string $apiKey, string $systemPrompt, array $history): array
    {
        $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(300)
            ->post(rtrim($agent->provider->base_url, '/') . '/messages', [
                'model'      => $agent->model,
                'system'     => $systemPrompt,
                'messages'   => $history,
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

    private function callGoogleChat(AiAgent $agent, string $apiKey, string $systemPrompt, array $history): array
    {
        $url = rtrim($agent->provider->base_url, '/') . "/models/{$agent->model}:generateContent?key={$apiKey}";

        $contents = array_map(fn($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $history);

        $response = Http::timeout(300)
            ->post($url, [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'          => $contents,
                'generationConfig'  => [
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
        $payload = [
            'model'                 => $agent->model,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'max_completion_tokens' => $agent->max_tokens,
        ];
        if (!$this->isReasoningModel($agent->model)) {
            $payload['temperature'] = $agent->temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout(300)
            ->post(rtrim($agent->provider->base_url, '/') . '/chat/completions', $payload)
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
            ->timeout(300)
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

        $response = Http::timeout(300)
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

    /**
     * Como run(), mas pede saída em JSON estruturado e devolve o array já decodificado
     * (não uma string). Usado por análises que precisam de schema, não texto livre.
     */
    public function runStructured(
        AiAgent $agent,
        string $userMessage,
        array $context = [],
        ?int $userId = null,
        ?string $clientId = null,
        ?string $trigger = null
    ): array {
        $agent->loadMissing('provider');

        $apiKey = $agent->resolvedApiKey();
        if (!$apiKey) {
            throw new \RuntimeException("Agente '{$agent->name}': nenhuma chave de API configurada para {$agent->provider->name}.");
        }

        $systemPrompt = $this->injectContext($agent->system_prompt, $context);
        $systemPrompt .= "\n\n---\nIMPORTANTE: responda ESTRITAMENTE com um único objeto JSON válido, sem markdown, sem texto antes ou depois, sem \`\`\`. Se não conseguir preencher um campo, use null - nunca invente dado.";

        [$responseText, $usage] = $this->dispatchStructured($agent, $apiKey->getApiKey(), $systemPrompt, $userMessage);

        $this->logUsage($agent, $usage, $userId, $clientId, $trigger);

        return $this->parseJsonResponse($responseText, $agent);
    }

    private function dispatchStructured(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        return match ($agent->provider->slug) {
            'openai', 'groq' => $this->callOpenAiCompatStructured($agent, $apiKey, $systemPrompt, $userMessage),
            'anthropic'      => $this->callAnthropic($agent, $apiKey, $systemPrompt, $userMessage),
            'google'         => $this->callGoogleStructured($agent, $apiKey, $systemPrompt, $userMessage),
            default          => throw new \RuntimeException("Provider '{$agent->provider->slug}' não suportado."),
        };
    }

    // OpenAI/Groq têm modo JSON nativo (response_format) - garante saída parseável
    private function callOpenAiCompatStructured(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        $payload = [
            'model'                 => $agent->model,
            'messages'              => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'max_completion_tokens' => $agent->max_tokens,
            'response_format'       => ['type' => 'json_object'],
        ];
        if (!$this->isReasoningModel($agent->model)) {
            $payload['temperature'] = $agent->temperature;
        }

        $response = Http::withToken($apiKey)
            ->timeout(300)
            ->post(rtrim($agent->provider->base_url, '/') . '/chat/completions', $payload)
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

    // Gemini tem modo JSON nativo via responseMimeType
    private function callGoogleStructured(AiAgent $agent, string $apiKey, string $systemPrompt, string $userMessage): array
    {
        $url = rtrim($agent->provider->base_url, '/') . "/models/{$agent->model}:generateContent?key={$apiKey}";

        $response = Http::timeout(300)
            ->post($url, [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'          => [
                    ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                ],
                'generationConfig' => [
                    'temperature'      => $agent->temperature,
                    'maxOutputTokens'  => $agent->max_tokens,
                    'responseMimeType' => 'application/json',
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

    // Anthropic não tem modo JSON nativo - depende só da instrução no prompt,
    // por isso o parseJsonResponse() abaixo tem fallback de extração por regex.
    private function parseJsonResponse(string $responseText, AiAgent $agent): array
    {
        $decoded = json_decode(trim($responseText), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: extrai o primeiro bloco {...} da resposta (modelo pode ter
        // envolvido em ```json ... ``` ou adicionado texto antes/depois)
        if (preg_match('/\{.*\}/s', $responseText, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException(
            "Agente '{$agent->name}': resposta não é um JSON válido. Início da resposta: " .
            mb_substr($responseText, 0, 200)
        );
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

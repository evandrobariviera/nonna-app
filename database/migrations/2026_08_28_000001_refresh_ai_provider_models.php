<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atualiza a lista de modelos de cada provider de IA (coluna ai_providers.models)
 * pro catálogo vigente em 2026-08. O AiProviderSeeder usa insertOrIgnore, então
 * providers que já existem em produção nunca eram atualizados por ele — daí esta
 * migration. Mesma lista está no seeder, pra instalações novas.
 *
 * Preços em USD por 1k tokens (input_per_1k / output_per_1k) — alimentam o
 * dropdown de modelo do agente, a tela de Providers e AiService::estimateCost().
 * Transcrição (ElevenLabs) cobra por hora de áudio, não por token: fica zerado.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        foreach (self::catalog() as $slug => $p) {
            $row = DB::connection('pgsql')->table('ai_providers')->where('slug', $slug)->first();

            if ($row) {
                DB::connection('pgsql')->table('ai_providers')
                    ->where('slug', $slug)
                    ->update([
                        'models'     => json_encode($p['models']),
                        'updated_at' => now(),
                    ]);
                continue;
            }

            DB::connection('pgsql')->table('ai_providers')->insert([
                'id'         => (string) Str::uuid(),
                'name'       => $p['name'],
                'slug'       => $slug,
                'base_url'   => $p['base_url'],
                'models'     => json_encode($p['models']),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Migration de dados (refresh de catálogo) — sem rollback automático.
    }

    /**
     * @return array<string, array{name: string, base_url: string, models: list<array<string, mixed>>}>
     */
    public static function catalog(): array
    {
        return [
            'anthropic' => [
                'name'     => 'Anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'models'   => [
                    ['id' => 'claude-fable-5',    'label' => 'Claude Fable 5',    'input_per_1k' => 0.010,  'output_per_1k' => 0.050],
                    ['id' => 'claude-opus-5',     'label' => 'Claude Opus 5',     'input_per_1k' => 0.005,  'output_per_1k' => 0.025],
                    ['id' => 'claude-opus-4-8',   'label' => 'Claude Opus 4.8',   'input_per_1k' => 0.005,  'output_per_1k' => 0.025],
                    ['id' => 'claude-sonnet-5',   'label' => 'Claude Sonnet 5',   'input_per_1k' => 0.002,  'output_per_1k' => 0.010],
                    ['id' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6', 'input_per_1k' => 0.003,  'output_per_1k' => 0.015],
                    ['id' => 'claude-haiku-4-5',  'label' => 'Claude Haiku 4.5',  'input_per_1k' => 0.001,  'output_per_1k' => 0.005],
                ],
            ],
            'openai' => [
                'name'     => 'OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'models'   => [
                    ['id' => 'gpt-5.6-sol',   'label' => 'GPT-5.6 Sol (frontier)',    'input_per_1k' => 0.005,   'output_per_1k' => 0.030],
                    ['id' => 'gpt-5.6-terra', 'label' => 'GPT-5.6 Terra (equilíbrio)', 'input_per_1k' => 0.002,   'output_per_1k' => 0.012],
                    ['id' => 'gpt-5.6-luna',  'label' => 'GPT-5.6 Luna (custo)',       'input_per_1k' => 0.0002,  'output_per_1k' => 0.0012],
                    ['id' => 'gpt-4o-mini',   'label' => 'GPT-4o Mini (legado)',       'input_per_1k' => 0.00015, 'output_per_1k' => 0.0006],
                ],
            ],
            'google' => [
                'name'     => 'Google',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'models'   => [
                    ['id' => 'gemini-3.1-pro-preview', 'label' => 'Gemini 3.1 Pro',        'input_per_1k' => 0.002,   'output_per_1k' => 0.012],
                    ['id' => 'gemini-3.7-flash',       'label' => 'Gemini 3.7 Flash',      'input_per_1k' => 0.00075, 'output_per_1k' => 0.00375],
                    ['id' => 'gemini-3.5-flash-lite',  'label' => 'Gemini 3.5 Flash-Lite', 'input_per_1k' => 0.0003,  'output_per_1k' => 0.0025],
                    ['id' => 'gemini-2.5-pro',         'label' => 'Gemini 2.5 Pro',        'input_per_1k' => 0.00125, 'output_per_1k' => 0.010],
                    ['id' => 'gemini-2.5-flash',       'label' => 'Gemini 2.5 Flash',      'input_per_1k' => 0.0003,  'output_per_1k' => 0.0025],
                ],
            ],
            'groq' => [
                'name'     => 'Groq',
                'base_url' => 'https://api.groq.com/openai/v1',
                'models'   => [
                    ['id' => 'openai/gpt-oss-120b',     'label' => 'GPT-OSS 120B',  'input_per_1k' => 0.00015,  'output_per_1k' => 0.0006],
                    ['id' => 'openai/gpt-oss-20b',      'label' => 'GPT-OSS 20B',   'input_per_1k' => 0.000075, 'output_per_1k' => 0.0003],
                    ['id' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B', 'input_per_1k' => 0.00059,  'output_per_1k' => 0.00079],
                    ['id' => 'llama-3.1-8b-instant',    'label' => 'Llama 3.1 8B',  'input_per_1k' => 0.00005,  'output_per_1k' => 0.00008],
                ],
            ],
            'elevenlabs' => [
                'name'     => 'ElevenLabs',
                'base_url' => 'https://api.elevenlabs.io/v1',
                'models'   => [
                    ['id' => 'scribe_v2', 'label' => 'Scribe v2 (transcrição de áudio)', 'input_per_1k' => 0, 'output_per_1k' => 0],
                ],
            ],
        ];
    }
};

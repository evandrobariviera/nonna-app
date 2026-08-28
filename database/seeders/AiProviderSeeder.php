<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AiProviderSeeder extends Seeder
{
    /**
     * Cria os providers que ainda não existem (insertOrIgnore — não sobrescreve
     * quem já está no banco). A lista de modelos de providers existentes é
     * atualizada pelas migrations `*_refresh_ai_provider_models` — manter os dois
     * em sincronia. Preços em USD por 1k tokens.
     */
    public function run(): void
    {
        $catalog = [
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
            'anthropic' => [
                'name'     => 'Anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'models'   => [
                    ['id' => 'claude-fable-5',    'label' => 'Claude Fable 5',    'input_per_1k' => 0.010, 'output_per_1k' => 0.050],
                    ['id' => 'claude-opus-5',     'label' => 'Claude Opus 5',     'input_per_1k' => 0.005, 'output_per_1k' => 0.025],
                    ['id' => 'claude-opus-4-8',   'label' => 'Claude Opus 4.8',   'input_per_1k' => 0.005, 'output_per_1k' => 0.025],
                    ['id' => 'claude-sonnet-5',   'label' => 'Claude Sonnet 5',   'input_per_1k' => 0.002, 'output_per_1k' => 0.010],
                    ['id' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6', 'input_per_1k' => 0.003, 'output_per_1k' => 0.015],
                    ['id' => 'claude-haiku-4-5',  'label' => 'Claude Haiku 4.5',  'input_per_1k' => 0.001, 'output_per_1k' => 0.005],
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
                    // Transcrição (speech-to-text). Custo é por hora de áudio, não por
                    // token — colunas per_1k zeradas; ver AiService::transcribeAudioLong.
                    ['id' => 'scribe_v2', 'label' => 'Scribe v2 (transcrição de áudio)', 'input_per_1k' => 0, 'output_per_1k' => 0],
                ],
            ],
        ];

        $rows = [];
        foreach ($catalog as $slug => $p) {
            $rows[] = [
                'id'         => Str::uuid(),
                'name'       => $p['name'],
                'slug'       => $slug,
                'base_url'   => $p['base_url'],
                'models'     => json_encode($p['models']),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::connection('pgsql')->table('ai_providers')->insertOrIgnore($rows);
    }
}

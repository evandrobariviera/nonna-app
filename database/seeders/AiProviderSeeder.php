<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'id'       => Str::uuid(),
                'name'     => 'OpenAI',
                'slug'     => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'models'   => json_encode([
                    ['id' => 'gpt-4o',            'label' => 'GPT-4o',              'input_per_1k' => 0.0025,   'output_per_1k' => 0.01],
                    ['id' => 'gpt-4o-mini',        'label' => 'GPT-4o Mini',         'input_per_1k' => 0.00015,  'output_per_1k' => 0.0006],
                    ['id' => 'gpt-4-turbo',        'label' => 'GPT-4 Turbo',         'input_per_1k' => 0.01,     'output_per_1k' => 0.03],
                    ['id' => 'o1-mini',            'label' => 'o1 Mini',             'input_per_1k' => 0.003,    'output_per_1k' => 0.012],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'       => Str::uuid(),
                'name'     => 'Anthropic',
                'slug'     => 'anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'models'   => json_encode([
                    ['id' => 'claude-opus-4-8',           'label' => 'Claude Opus 4.8',       'input_per_1k' => 0.015,   'output_per_1k' => 0.075],
                    ['id' => 'claude-sonnet-4-6',         'label' => 'Claude Sonnet 4.6',     'input_per_1k' => 0.003,   'output_per_1k' => 0.015],
                    ['id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5',      'input_per_1k' => 0.0008,  'output_per_1k' => 0.004],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'       => Str::uuid(),
                'name'     => 'Google',
                'slug'     => 'google',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'models'   => json_encode([
                    ['id' => 'gemini-2.0-flash',       'label' => 'Gemini 2.0 Flash',     'input_per_1k' => 0.00010, 'output_per_1k' => 0.00040],
                    ['id' => 'gemini-1.5-pro',         'label' => 'Gemini 1.5 Pro',       'input_per_1k' => 0.00125, 'output_per_1k' => 0.00500],
                    ['id' => 'gemini-1.5-flash',       'label' => 'Gemini 1.5 Flash',     'input_per_1k' => 0.000075,'output_per_1k' => 0.00030],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'       => Str::uuid(),
                'name'     => 'Groq',
                'slug'     => 'groq',
                'base_url' => 'https://api.groq.com/openai/v1',
                'models'   => json_encode([
                    ['id' => 'llama-3.3-70b-versatile', 'label' => 'LLaMA 3.3 70B',   'input_per_1k' => 0.00059, 'output_per_1k' => 0.00079],
                    ['id' => 'llama-3.1-8b-instant',    'label' => 'LLaMA 3.1 8B',    'input_per_1k' => 0.00005, 'output_per_1k' => 0.00008],
                    ['id' => 'mixtral-8x7b-32768',      'label' => 'Mixtral 8x7B',    'input_per_1k' => 0.00024, 'output_per_1k' => 0.00024],
                ]),
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::connection('pgsql')->table('ai_providers')->insertOrIgnore($providers);
    }
}

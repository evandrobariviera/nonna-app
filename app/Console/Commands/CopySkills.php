<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Recopia as skills do Claude (planejamento/campanha/projeto-nonna) para resources/ai/skills/.
 * São as regras que o agente "Atualizar documento com reunião" (DocumentUpdateService) segue
 * para escrever igual à skill que gerou o documento. Mudou a skill no Claude → rode isto
 * na máquina que tem as skills sincronizadas e faça commit + push.
 */
class CopySkills extends Command
{
    protected $signature = 'skills:copiar {origem? : Pasta com as skills (padrão: ~/.claude/skills/synced/*)}';

    protected $description = 'Copia as skills planejamento/campanha/projeto-nonna para as referências do agente de documentos';

    private const SKILLS = ['planejamento-nonna', 'campanha-nonna', 'projeto-nonna'];

    public function handle(): int
    {
        $source = $this->argument('origem') ?? $this->defaultSource();
        if (!$source || !is_dir($source)) {
            $this->error('Pasta das skills não encontrada. Informe o caminho: php artisan skills:copiar "C:\\...\\skills\\synced\\<id>"');

            return self::FAILURE;
        }

        foreach (self::SKILLS as $skill) {
            $dir = rtrim($source, '/\\') . DIRECTORY_SEPARATOR . $skill;
            if (!is_file($dir . '/SKILL.md')) {
                $this->warn("{$skill}: SKILL.md não encontrado em {$dir} — mantida a cópia atual.");
                continue;
            }

            // SKILL.md + os arquivos de assets/ (visual, exemplo, esqueleto) num arquivo só.
            $content = file_get_contents($dir . '/SKILL.md');
            foreach (glob($dir . '/assets/*') ?: [] as $asset) {
                $content .= "\n\n---\n\n# Arquivo de referência: assets/" . basename($asset) . "\n\n" . file_get_contents($asset);
            }

            file_put_contents(resource_path("ai/skills/{$skill}.md"), $content);
            $this->info("{$skill}: " . number_format(strlen($content), 0, ',', '.') . ' bytes');
        }

        return self::SUCCESS;
    }

    private function defaultSource(): ?string
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME');
        $dirs = $home ? glob($home . '/.claude/skills/synced/*', GLOB_ONLYDIR) : [];

        return $dirs[0] ?? null;
    }
}

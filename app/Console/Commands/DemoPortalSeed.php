<?php

namespace App\Console\Commands;

use App\Services\Demo\PortalDemoBuilder;
use Illuminate\Console\Command;

/**
 * Cliente fictício completo pro Portal (vídeos tutoriais). Fica numa organização
 * separada (status "demo"), então o time não vê nada nas telas internas.
 * As datas são relativas ao dia em que roda: --fresh refaz tudo atualizado.
 */
class DemoPortalSeed extends Command
{
    protected $signature = 'demo:portal
        {--fresh : Apaga a organização de demonstração e cria tudo de novo}
        {--purge : Só apaga a organização de demonstração}
        {--password=Aurora@2026 : Senha dos contatos no Portal}';

    protected $description = 'Cria (ou recria) o cliente fictício "Aurora Estruturas Metálicas" para demonstrar o Portal';

    public function handle(PortalDemoBuilder $builder): int
    {
        $exists = $builder->organization() !== null;

        if ($this->option('purge')) {
            $exists ? $builder->purge() : $this->info('Não há organização de demonstração.');
            $this->info('Pronto.');

            return self::SUCCESS;
        }

        if ($exists && !$this->option('fresh')) {
            $this->error('A organização de demonstração já existe. Use --fresh para recriar.');

            return self::FAILURE;
        }

        if ($exists) {
            $this->line('Apagando a demonstração anterior...');
            $builder->purge();
        }

        $this->line('Enviando arquivos e criando os dados...');
        $summary = $builder->build((string) $this->option('password'), fn (string $msg) => $this->line("  · {$msg}"));

        $this->newLine();
        $this->info('Cliente fictício criado.');
        $this->table(['Login do Portal', 'Senha', 'Papel'], $summary['logins']);
        foreach ($summary['counts'] as $label => $n) {
            $this->line(str_pad($label, 34, '.') . " {$n}");
        }

        return self::SUCCESS;
    }
}

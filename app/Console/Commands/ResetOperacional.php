<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetOperacional extends Command
{
    protected $signature   = 'reset:operacional {--force : Pula a confirmação interativa}';
    protected $description = 'Remove todas as tarefas, projetos, planejamentos, sprints, oportunidades e onboardings. Mantém clientes, contatos e usuários.';

    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->warn('ATENÇÃO: Esta operação é IRREVERSÍVEL e apagará dados de produção.');
            if (!$this->confirm('Confirma a limpeza operacional do banco?')) {
                $this->info('Operação cancelada.');
                return 0;
            }
        }

        $this->info('Iniciando limpeza...');

        DB::connection('pgsql')->statement('
            TRUNCATE TABLE
                task_approval_tokens,
                task_approval_rounds,
                task_comments,
                task_attachments,
                task_executors,
                tasks,
                sprints,
                projects,
                macro_plans,
                opportunities,
                client_onboardings
            RESTART IDENTITY CASCADE
        ');

        $this->info('✓ task_approval_tokens');
        $this->info('✓ task_approval_rounds');
        $this->info('✓ task_comments');
        $this->info('✓ task_attachments');
        $this->info('✓ task_executors');
        $this->info('✓ tasks');
        $this->info('✓ sprints');
        $this->info('✓ projects');
        $this->info('✓ macro_plans');
        $this->info('✓ opportunities');
        $this->info('✓ client_onboardings');
        $this->newLine();
        $this->info('Limpeza concluída. Clientes, contatos e usuários preservados.');

        return 0;
    }
}

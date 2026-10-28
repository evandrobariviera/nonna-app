<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Offboarding por projeto (2026-10-28, Evandro): cobranças, cancelar cobranças futuras,
    // distrato, sair das contas do cliente, concluir os projetos, inativar no sistema.
    // Prazos = estimativa inicial em dias a partir do início do projeto Offboarding.
    public function up(): void
    {
        $db = DB::connection('pgsql');
        $phases = [
            ['name' => 'Alinhamento da saída', 'start' => 0, 'days' => 2, 'tasks' => [
                ['Registrar o motivo da saída e a data final do contrato', 'administrativo', 'Atendimento', 'Anotar nos comentários do projeto: por que o cliente saiu e até quando vai o contrato (aviso prévio).'],
                ['Avisar o time sobre a saída do cliente', 'reunioes', 'Atendimento', 'Quem atende o cliente precisa saber a data final pra não lançar trabalho novo.'],
            ]],
            ['name' => 'Financeiro', 'start' => 0, 'days' => 6, 'tasks' => [
                ['Conferir cobranças em aberto e cobrar o que falta', 'administrativo', 'Administrativo e Financeiro'],
                ['Cancelar as cobranças futuras', 'administrativo', 'Administrativo e Financeiro', 'Boletos agendados, recorrência no cartão, assinaturas de ferramentas pagas em nome do cliente.'],
                ['Elaborar e enviar o distrato', 'administrativo', 'Administrativo e Financeiro'],
                ['Conferir o distrato assinado', 'administrativo', 'Administrativo e Financeiro', 'Anexar o distrato assinado neste projeto.'],
            ]],
            ['name' => 'Encerrar campanhas e projetos', 'start' => 2, 'days' => 6, 'tasks' => [
                ['Pausar as campanhas de anúncio do cliente', 'trafego', 'Tráfego', 'Na data combinada pra saída — nada de gastar verba depois do fim do contrato.'],
                ['Concluir ou cancelar todos os projetos e tarefas abertos', 'estrategia', 'Gestor de Projetos', 'Menos este Offboarding. Tarefa que não vai ser feita = cancelada.'],
            ]],
            ['name' => 'Acessos', 'start' => 5, 'days' => 5, 'tasks' => [
                ['Sair das contas do cliente', 'setup', 'Tráfego', 'Business Manager, contas de anúncio, Google (Ads, GA4, GTM), redes sociais, site/hospedagem. Remover os usuários da Nonna.'],
            ]],
            ['name' => 'Encerramento no sistema', 'start' => 10, 'days' => 1, 'tasks' => [
                ['Inativar o cliente no App', 'administrativo', 'Atendimento', 'Na página do cliente, status Inativo. Ele some das listas, mas o histórico fica. Fazer por último, depois de concluir este projeto.'],
            ]],
            ['name' => 'Cliente encerrado', 'milestone' => true, 'start' => 10],
        ];

        foreach ($db->table('organizations')->pluck('id') as $orgId) {
            if ($db->table('project_playbooks')->where('organization_id', $orgId)->where('name', 'Offboarding de Cliente')->exists()) {
                continue;
            }
            $roles = $db->table('functional_roles')->where('organization_id', $orgId)->pluck('id', 'name');
            $now = now();
            $playbookId = (string) Str::uuid();
            $db->table('project_playbooks')->insert([
                'id' => $playbookId, 'organization_id' => $orgId, 'name' => 'Offboarding de Cliente',
                'description' => 'Saída do cliente: financeiro, distrato, encerrar campanhas/projetos, sair das contas e inativar.',
                'disciplines' => json_encode(['relacionamento', 'trafego']), 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $pos = 0;
            foreach ($phases as $i => $phase) {
                $phaseId = (string) Str::uuid();
                $milestone = !empty($phase['milestone']);
                $db->table('project_playbook_phases')->insert([
                    'id' => $phaseId, 'project_playbook_id' => $playbookId, 'position' => $i + 1,
                    'name' => $phase['name'], 'is_milestone' => $milestone,
                    'start_offset_days' => $phase['start'], 'duration_days' => $milestone ? null : $phase['days'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($phase['tasks'] ?? [] as $t) {
                    $db->table('project_playbook_tasks')->insert([
                        'id' => (string) Str::uuid(), 'project_playbook_id' => $playbookId, 'project_playbook_phase_id' => $phaseId,
                        'position' => ++$pos, 'title' => $t[0], 'description' => $t[3] ?? null, 'task_type' => $t[1],
                        'destination' => null, 'priority' => null, 'due_offset_days' => null,
                        'functional_role_id' => $roles[$t[2]] ?? null, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('project_playbooks')->where('name', 'Offboarding de Cliente')->delete();
    }
};

<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Contact;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApprovalTestSeeder extends Seeder
{
    public function run(): void
    {
        // ── Usuário interno (criador da tarefa) ──────────────────────────
        $user = User::first();
        if (! $user) {
            $this->command->error('Nenhum usuário encontrado. Faça login primeiro.');
            return;
        }

        // ── Cliente de teste ─────────────────────────────────────────────
        $client = Client::whereNotNull('company_name')->first();
        if (! $client) {
            $this->command->error('Nenhum cliente encontrado.');
            return;
        }

        $this->command->info("Usando cliente: {$client->company_name} ({$client->id})");

        // ── Contato de aprovação ─────────────────────────────────────────
        // Procura um contact já vinculado a esse client com receives_approvals
        $existingApprover = ClientContact::where('client_id', $client->id)
            ->where('receives_approvals', true)
            ->with('contact')
            ->first();

        if ($existingApprover) {
            $this->command->info("Aprovador já existe: {$existingApprover->contact->name}");
        } else {
            // Cria ou reutiliza um contato
            $contact = Contact::whereNotNull('email')->first()
                ?? Contact::create([
                    'name'   => 'João Aprovador (Teste)',
                    'email'  => 'aprovador@teste.com.br',
                    'phone'  => '11999990000',
                    'status' => 'active',
                ]);

            ClientContact::updateOrCreate(
                ['client_id' => $client->id, 'contact_id' => $contact->id],
                [
                    'role'               => 'decisor',
                    'is_primary'         => true,
                    'receives_approvals' => true,
                ]
            );

            $this->command->info("Contato aprovador vinculado: {$contact->name} ({$contact->email})");
        }

        // ── MacroPlan + Project ──────────────────────────────────────────
        $macro = MacroPlan::where('client_id', $client->id)->first();
        if (! $macro) {
            $macro = MacroPlan::create([
                'client_id'    => $client->id,
                'title'        => 'Planejamento de Teste Q3 2026',
                'period_start' => now()->startOfMonth(),
                'period_end'   => now()->addMonths(3)->endOfMonth(),
                'status'       => 'active',
                'created_by'   => $user->id,
            ]);
        }

        $project = Project::where('macro_plan_id', $macro->id)->first();
        if (! $project) {
            $project = Project::create([
                'macro_plan_id' => $macro->id,
                'client_id'     => $client->id,
                'title'         => 'Social Media — Junho',
                'status'        => 'active',
            ]);
        }

        // ── Tarefa de teste ──────────────────────────────────────────────
        $task = Task::create([
            'project_id'    => $project->id,
            'macro_plan_id' => $macro->id,
            'client_id'     => $client->id,
            'title'         => '[TESTE] Post Carrossel — Lançamento de Produto',
            'description'   => 'Carrossel 5 slides para Instagram. Produto: linha verão 2026.',
            'task_type'     => 'criacao',
            'status'        => 'em_producao',
            'situation'     => 'em_revisao_interna',
            'origin'        => 'projeto',
            'created_by'    => $user->id,
        ]);

        $this->command->info("Tarefa criada: {$task->title} ({$task->id})");

        // ── Arquivos de exemplo no R2 ────────────────────────────────────
        // Cria 2 arquivos de texto simulando peças de design
        $slides = [
            ['nome' => 'slide-01-capa.png',    'label' => 'Slide 01 — Capa',             'bg' => [106, 90, 205]],  // roxo
            ['nome' => 'slide-02-produto.png', 'label' => 'Slide 02 — Produto',          'bg' => [255, 140,   0]],  // laranja
            ['nome' => 'slide-03-preco.png',   'label' => 'Slide 03 — Preco e CTA',      'bg' => [ 30,  30,  46]],  // dark
        ];

        foreach ($slides as $s) {
            $img   = imagecreate(800, 600);
            $bg    = imagecolorallocate($img, ...$s['bg']);
            $white = imagecolorallocate($img, 255, 255, 255);
            $gray  = imagecolorallocate($img, 200, 200, 200);

            // Fundo
            imagefill($img, 0, 0, $bg);

            // Borda simulada
            imagerectangle($img, 20, 20, 779, 579, $white);

            // Texto centralizado
            imagestring($img, 5, 300, 270, $s['label'], $white);
            imagestring($img, 3, 270, 300, 'Nonna App - Material de Aprovacao', $gray);

            ob_start();
            imagepng($img);
            $content = ob_get_clean();
            imagedestroy($img);

            $path = "tasks/{$task->id}/{$s['nome']}";
            Storage::disk('r2')->put($path, $content, ['ContentType' => 'image/png']);

            TaskAttachment::create([
                'task_id'     => $task->id,
                'filename'    => $s['nome'],
                'disk_path'   => $path,
                'disk'        => 'r2',
                'mime_type'   => 'image/png',
                'size'        => strlen($content),
                'uploaded_by' => $user->id,
            ]);

            $this->command->info("  Imagem gerada: {$s['nome']} (" . round(strlen($content)/1024, 1) . " KB)");
        }

        $this->command->newLine();
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info('TESTE PRONTO. Para disparar a aprovação:');
        $this->command->info('1. Acesse a tarefa: /tarefas/' . $task->id);
        $this->command->info('2. Clique em Editar');
        $this->command->info('3. Mude Situação para "Enviar para o Cliente"');
        $this->command->info('4. Salve — o webhook dispara e o token é gerado');
        $this->command->info('');
        $this->command->info('Ou dispare diretamente via tinker:');
        $this->command->info("php artisan tinker --execute=\"app(App\Services\TaskApprovalService::class)->submitForApproval(App\Models\Task::find('{$task->id}'), App\Models\User::first(), App\Models\TaskAttachment::where('task_id','{$task->id}')->pluck('id')->toArray())\"");
        $this->command->info('══════════════════════════════════════════════');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateClientsFromClickupSeeder extends Seeder
{
    public function run(): void
    {
        $rows = DB::connection('pgsql')
            ->table('clickup_tasks_clients')
            ->select(['client_task_id', 'company_name', 'website', 'cnpj'])
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = Client::where('clickup_task_id', $row->client_task_id)->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Client::create([
                'clickup_task_id' => $row->client_task_id,
                'company_name'    => $row->company_name ?? 'Sem nome',
                'website'         => $row->website,
                'tax_id'          => $row->cnpj,
                'status'          => 'active',
            ]);

            $created++;
        }

        $this->command->info("Migração concluída: {$created} criados, {$skipped} ignorados (já existiam).");
    }
}

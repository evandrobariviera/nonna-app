<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateContactsFromClickupSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = DB::table('users')->orderBy('id')->value('id');

        if (!$adminUserId) {
            $this->command->error('Nenhum usuário encontrado. Crie um usuário antes de rodar este seeder.');
            return;
        }

        $clickupContacts = DB::table('clickup_tasks_contacts')->get();

        $this->command->info("Migrando {$clickupContacts->count()} contatos do ClickUp...");

        $imported = 0;
        $skipped  = 0;

        foreach ($clickupContacts as $cu) {
            // Limpa emails falsos gerados automaticamente
            $email = null;
            if ($cu->email && !str_contains($cu->email, 'no-email-')) {
                $email = $cu->email;
            }

            // Normaliza nome (ignora contatos com nome vazio)
            $name = trim($cu->contact_name ?? '');
            if (empty($name)) {
                $skipped++;
                continue;
            }

            // Evita duplicatas por nome exato
            $exists = DB::table('contacts')->where('name', $name)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            DB::table('contacts')->insert([
                'id'           => Str::uuid()->toString(),
                'name'         => $name,
                'email'        => $email,
                'phone'        => null,
                'whatsapp'     => $this->normalizePhone($cu->phone),
                'job_title'    => $cu->job_title ?: null,
                'company_name' => null,
                'source'       => 'outros',
                'status'       => 'ganho',
                'notes'        => null,
                'assigned_to'  => null,
                'created_by'   => $adminUserId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $imported++;
        }

        $this->command->info("Importados: {$imported} | Ignorados (vazios/duplicados): {$skipped}");
        $this->command->warn("Atenção: as relações cliente-contato não foram migradas automaticamente.");
        $this->command->warn("A junction clickup_tasks_client_contacts_relation estava vazia.");
        $this->command->info("Vincule os contatos aos clientes manualmente pela tab Contatos na ficha do cliente.");
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        // Remove tudo exceto dígitos e + inicial
        $clean = preg_replace('/[^\d+]/', '', $phone);

        // Converte +5549... para (49) 9...
        if (str_starts_with($clean, '+55') && strlen($clean) >= 13) {
            $digits = substr($clean, 3); // remove +55
            $ddd    = substr($digits, 0, 2);
            $number = substr($digits, 2);

            if (strlen($number) === 9) {
                return "({$ddd}) {$number[0]}{$number[1]}{$number[2]}{$number[3]}{$number[4]}-{$number[5]}{$number[6]}{$number[7]}{$number[8]}";
            }
            if (strlen($number) === 8) {
                return "({$ddd}) {$number[0]}{$number[1]}{$number[2]}{$number[3]}-{$number[4]}{$number[5]}{$number[6]}{$number[7]}";
            }
        }

        return $clean ?: null;
    }
}

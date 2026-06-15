<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'nonna'],
            [
                'id'     => Str::uuid(),
                'name'   => 'Nonna Agência Digital',
                'plan'   => 'enterprise',
                'status' => 'active',
                'settings' => [
                    'branding' => [
                        'primary_color'   => '#6A5ACD',
                        'secondary_color' => '#FF8C00',
                        'logo_url'        => null,
                    ],
                ],
            ]
        );

        // Vincula todos os usuários existentes à organização Nonna como admin
        User::all()->each(function (User $user) use ($org) {
            if (!$org->users()->where('user_id', $user->id)->exists()) {
                $org->users()->attach($user->id, ['id' => Str::uuid(), 'role' => 'admin']);
            }
        });

        // Define o primeiro usuário como owner
        $firstUser = User::first();
        if ($firstUser) {
            $org->update(['owner_user_id' => $firstUser->id]);
        }

        // Preenche organization_id em todos os registros existentes sem tenant
        $tables = [
            'clients', 'contacts', 'opportunities', 'meetings',
            'macro_plans', 'projects', 'tasks', 'sprints',
        ];

        foreach ($tables as $table) {
            DB::connection('pgsql')
                ->table($table)
                ->whereNull('organization_id')
                ->update(['organization_id' => $org->id]);
        }

        $this->command->info("Organização '{$org->name}' (slug: {$org->slug}) pronta.");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Lista estática que existia em OrganizationUser::$functionRoles antes dela virar tabela —
    // congelada aqui só pra popular o dado inicial, não é mais a fonte de verdade depois disso.
    private array $roles = [
        'direcao_geral'    => 'Direção Geral',
        'direcao_criativa' => 'Direção Criativa',
        'coo'              => 'COO & Operação',
        'estrategia'       => 'Estratégia',
        'atendimento'      => 'Atendimento',
        'gestor_campanhas' => 'Gestor de Campanhas',
        'head_criativa'    => 'Head Criativa & Copy',
        'head_tech'        => 'Head de Tecnologia',
        'designer'         => 'Design',
        'trafego'          => 'Tráfego',
        'dev'              => 'Dev',
    ];

    // Papéis com seção própria no Dashboard — apagar quebraria a seção (checa a chave no
    // código), por isso ficam protegidos contra exclusão pela tela.
    private array $protectedKeys = ['atendimento', 'head_criativa', 'head_tech', 'estrategia', 'trafego'];

    public function up(): void
    {
        $now = now();

        $organizations = DB::connection('pgsql')->table('organizations')->get(['id']);

        foreach ($organizations as $org) {
            $roleIdByKey = [];

            foreach ($this->roles as $key => $name) {
                $id = (string) Str::uuid();
                $roleIdByKey[$key] = $id;

                DB::connection('pgsql')->table('functional_roles')->insert([
                    'id'             => $id,
                    'organization_id' => $org->id,
                    'key'            => $key,
                    'name'           => $name,
                    'is_protected'   => in_array($key, $this->protectedKeys, true),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            $memberships = DB::connection('pgsql')->table('organization_users')
                ->where('organization_id', $org->id)
                ->get(['user_id', 'function_roles']);

            foreach ($memberships as $membership) {
                $keys = json_decode($membership->function_roles ?? '[]', true) ?? [];

                foreach ($keys as $key) {
                    if (!isset($roleIdByKey[$key])) {
                        continue; // chave desconhecida (não deveria acontecer, mas não trava o deploy)
                    }

                    DB::connection('pgsql')->table('functional_role_user')->insert([
                        'functional_role_id' => $roleIdByKey[$key],
                        'user_id'            => $membership->user_id,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('functional_role_user')->truncate();
        DB::connection('pgsql')->table('functional_roles')->truncate();
    }
};

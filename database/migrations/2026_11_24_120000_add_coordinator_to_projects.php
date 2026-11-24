<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Acompanhamento (etapa E, 24/11): cada campanha/projeto tem o seu coordenador — a pessoa que
 * confere a cada N dias se está andando. Escolhido no documento (seção Acompanhamento), espelhado
 * em projects.coordinator_id. O Papel Funcional "Coordenador de Campanhas e Projetos" só filtra
 * quem aparece primeiro na lista (protegido: o código procura a chave).
 *
 * Documento do projeto: sai "Quando vamos conferir" da seção 01 (repetia "Conferimos se melhorou
 * em" da seção 06) e entra "Conferir a cada quantos dias" (padrão 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql')->hasColumn('projects', 'coordinator_id')) {
            Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
                $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        $db  = DB::connection('pgsql');
        $now = now();
        foreach ($db->table('organizations')->pluck('id') as $orgId) {
            $exists = $db->table('functional_roles')->where('organization_id', $orgId)->where('key', 'coordenador')->exists();
            if (!$exists) {
                $db->table('functional_roles')->insert([
                    'id'              => (string) Str::uuid(),
                    'organization_id' => $orgId,
                    'key'             => 'coordenador',
                    'name'            => 'Coordenador de Campanhas e Projetos',
                    'is_protected'    => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        foreach ($db->table('documents')->where('kind', 'projeto')->get(['id', 'content']) as $doc) {
            $content = json_decode($doc->content ?? '[]', true) ?: [];
            $changed = false;
            if (array_key_exists('conferir_em', $content)) {
                if (empty($content['conferir_beneficio']) && !empty($content['conferir_em'])) {
                    $content['conferir_beneficio'] = $content['conferir_em'];
                }
                unset($content['conferir_em']);
                $changed = true;
            }
            if (empty($content['checkin_dias'])) {
                $content['checkin_dias'] = 15;
                $changed = true;
            }
            if ($changed) {
                $db->table('documents')->where('id', $doc->id)->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coordinator_id');
        });
    }
};

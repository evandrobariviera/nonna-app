<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_links', function (Blueprint $table) {
            $table->string('label', 150)->nullable()->after('client_id');
        });

        // Backfill dos links já cadastrados — sem isso ficariam sem rótulo nenhum na
        // listagem. Usa o mesmo texto que ClientLink::typeLabel() já resolvia antes de
        // existir o campo, pra não mudar o que o usuário já via na tela.
        $types = \App\Models\ClientLink::$types;

        DB::connection('pgsql')->table('client_links')
            ->whereNull('label')
            ->orderBy('id')
            ->get(['id', 'type', 'type_custom'])
            ->each(function ($row) use ($types) {
                $label = ($row->type === 'outros' && $row->type_custom)
                    ? $row->type_custom
                    : ($types[$row->type] ?? $row->type);

                DB::connection('pgsql')->table('client_links')
                    ->where('id', $row->id)
                    ->update(['label' => $label]);
            });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_links', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};

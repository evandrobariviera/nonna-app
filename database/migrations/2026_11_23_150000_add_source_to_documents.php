<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De onde o documento nasceu: o documento de campanha/projeto criado a partir de um item
        // do planejamento guarda o documento do planejamento e o id do item (PlanItemsLauncher).
        // É o que impede criar a mesma campanha duas vezes.
        $db = Schema::connection('pgsql');
        if (!$db->hasColumn('documents', 'source_document_id')) {
            $db->table('documents', function (Blueprint $table) {
                $table->uuid('source_document_id')->nullable()->after('documentable_id');
                $table->string('source_item_id', 20)->nullable()->after('source_document_id');
                $table->index(['source_document_id', 'source_item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('documents', function (Blueprint $table) {
            $table->dropIndex(['source_document_id', 'source_item_id']);
            $table->dropColumn(['source_document_id', 'source_item_id']);
        });
    }
};

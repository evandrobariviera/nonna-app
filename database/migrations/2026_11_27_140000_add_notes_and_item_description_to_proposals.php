<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propostas: observações livres pro cliente (proposals.notes) e descrição ajustada do item
 * só nesta proposta (proposal_items.description; null = usa a do catálogo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
        Schema::table('proposal_items', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};

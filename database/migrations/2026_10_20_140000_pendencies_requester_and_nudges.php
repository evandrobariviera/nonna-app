<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Pendência ganha o 3º papel — "quem precisa" (ex.: Marlon pede, cliente deve, Alisson
    // cobra). Existentes: quem criou. E o histórico das cobranças (antes só o total
    // nudge_count + a última data).
    public function up(): void
    {
        Schema::connection('pgsql')->table('pendencies', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            $table->index(['requested_by', 'status']);
        });

        DB::connection('pgsql')->statement('UPDATE pendencies SET requested_by = created_by WHERE requested_by IS NULL');

        Schema::connection('pgsql')->create('pendency_nudges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pendency_id')->constrained('pendencies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // whatsapp | email | ligacao | presencial | lembrete (aviso no sino pra alguém do time)
            $table->string('channel', 20);
            $table->timestamp('created_at')->nullable();

            $table->index(['pendency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('pendency_nudges');
        Schema::connection('pgsql')->table('pendencies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
        });
    }
};

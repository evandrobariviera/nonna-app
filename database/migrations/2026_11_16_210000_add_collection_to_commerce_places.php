<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prospecção, etapa 3: coleta + site + Nota Nonna + sinais de anúncio.
 * Continua sem dado do Google: site_signals é o que a Nonna achou no site da empresa
 * (sinais, sem conteúdo); ads_* é a conferência manual feita pela equipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->json('site_signals')->nullable()->after('score_parts');
            $table->timestamp('collected_at')->nullable()->after('site_signals');
            $table->text('collect_error')->nullable()->after('collected_at');
            $table->string('ads_meta', 8)->nullable()->after('collect_error');   // yes | no | unknown
            $table->string('ads_google', 8)->nullable()->after('ads_meta');
            $table->foreignId('ads_checked_by')->nullable()->after('ads_google')->constrained('users')->nullOnDelete();
            $table->timestamp('ads_checked_at')->nullable()->after('ads_checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_places', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ads_checked_by');
            $table->dropColumn(['site_signals', 'collected_at', 'collect_error', 'ads_meta', 'ads_google', 'ads_checked_at']);
        });
    }
};

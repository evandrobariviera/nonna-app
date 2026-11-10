<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Criativos: vídeo aprovado → anúncio na Meta. O vídeo sobe para a Meta, que
 * processa por alguns minutos; o anúncio é criado depois (status
 * "processando" até lá) por FinishMetaVideoAdJob.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('meta_ad_creations', function (Blueprint $table) {
            $table->string('meta_video_id', 50)->nullable()->after('creative_id');
        });

        // Google (Performance Max): vídeo entra pelo link do YouTube.
        Schema::connection('pgsql')->table('google_asset_creations', function (Blueprint $table) {
            $table->string('youtube_id', 20)->nullable()->after('field_type');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meta_ad_creations', function (Blueprint $table) {
            $table->dropColumn('meta_video_id');
        });
        Schema::connection('pgsql')->table('google_asset_creations', function (Blueprint $table) {
            $table->dropColumn('youtube_id');
        });
    }
};

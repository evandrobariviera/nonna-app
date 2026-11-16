<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexão do site (servidor → App): senha própria de cada site, que só serve
 * para entregar leads daquele cliente. Assina cada envio (HMAC) e nunca é
 * transmitida. Usada pelo plugin do WordPress, pelo Nonna CMS e por qualquer
 * outra plataforma. Gerada na primeira vez que alguém abre a conexão do site.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('client_trackings', function (Blueprint $table) {
            $table->text('site_secret')->nullable()->after('site_key');
            $table->timestamp('site_secret_rotated_at')->nullable()->after('site_secret');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_trackings', function (Blueprint $table) {
            $table->dropColumn(['site_secret', 'site_secret_rotated_at']);
        });
    }
};

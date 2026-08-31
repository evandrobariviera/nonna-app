<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Textos das notificações internas (sino) "do sistema" — as que hoje estão
 * fixas no código (onboarding, reuniões, macroplanejamento, portal). Uma linha
 * por (organização, event_key); sem linha = usa o texto padrão do catálogo
 * (App\Services\SystemNotificationService). Destinatário continua no código.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->create('notification_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('event_key', 80);
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('notification_settings');
    }
};

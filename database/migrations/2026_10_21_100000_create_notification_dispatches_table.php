<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de Envios das Mensagens Padrão (WhatsApp/e-mail) — uma linha por
 * tentativa de disparo do NotificationDispatchService, inclusive as que nem
 * chegaram a sair (sem webhook, sem texto, contato sem WhatsApp...).
 *
 * O App só enxerga até a entrega pro n8n: "sent" = n8n respondeu 2xx. Se o
 * n8n aceitou e falhou depois (envio pro WhatsApp em si), só aparece nas
 * Execuções do n8n.
 *
 * Nomes de cliente/contato e destino ficam copiados na linha pra o histórico
 * continuar legível mesmo se o cadastro mudar depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type', 60);    // NotificationTemplate::$types
            $table->string('channel', 20); // whatsapp | email

            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('recipient')->nullable(); // número/e-mail no momento do envio

            $table->string('status', 20); // sent | failed | skipped
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('message_body')->nullable();
            $table->boolean('is_test')->default(false);

            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'status']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};

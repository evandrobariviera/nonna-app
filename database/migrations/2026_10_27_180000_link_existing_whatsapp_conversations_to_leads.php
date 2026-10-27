<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Carga única do histórico: liga as conversas de WhatsApp que já existiam à
 * Central de Leads (ver LinkWhatsappLeads). As novas já se ligam sozinhas.
 * Fora de transação — o comando grava conversa por conversa. Se falhar, o
 * deploy segue; dá pra rodar o comando de novo, ele pula o que já ligou.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        try {
            Artisan::call('leads:link-whatsapp');
            Log::info('Carga do histórico WhatsApp → Central de Leads', ['saida' => Artisan::output()]);
        } catch (\Throwable $e) {
            Log::warning('Carga do histórico WhatsApp → Central de Leads falhou: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        //
    }
};

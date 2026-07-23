<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('status'); // pix | cartao | boleto
            $table->decimal('balance', 10, 2)->nullable()->after('payment_method');
            $table->string('balance_source', 10)->default('manual')->after('balance'); // manual | api
            $table->timestamp('balance_synced_at')->nullable()->after('balance_source');
            $table->boolean('budget_automation_enabled')->default(false)->after('balance_synced_at');
            $table->timestamp('last_billing_sent_at')->nullable()->after('budget_automation_enabled');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'balance',
                'balance_source',
                'balance_synced_at',
                'budget_automation_enabled',
                'last_billing_sent_at',
            ]);
        });
    }
};

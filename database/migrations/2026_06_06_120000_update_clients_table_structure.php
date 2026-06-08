<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // Step 1 — rename + drop old columns
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->renameColumn('cnpj', 'tax_id');
            $table->dropColumn([
                'trade_name',
                'monthly_revenue_range',
                'employee_count',
                'primary_contact_name',
                'primary_contact_email',
                'primary_contact_phone',
            ]);
        });

        // Step 2 — add new columns
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            // Empresa — contato
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('zip_code', 10)->nullable();

            // Responsável (pessoa física)
            $table->string('responsible_name')->nullable();
            $table->date('responsible_birthdate')->nullable();
            $table->string('responsible_rg', 30)->nullable();
            $table->string('responsible_cpf', 20)->nullable();
            $table->text('responsible_address')->nullable();
            $table->string('responsible_marital_status', 30)->nullable();

            // Cobrança
            $table->string('payment_method', 20)->nullable();
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_whatsapp', 30)->nullable();
            $table->text('billing_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'contact_email', 'contact_phone', 'address', 'zip_code',
                'responsible_name', 'responsible_birthdate', 'responsible_rg',
                'responsible_cpf', 'responsible_address', 'responsible_marital_status',
                'payment_method', 'billing_day', 'billing_email', 'billing_whatsapp',
                'billing_notes',
            ]);
        });

        Schema::connection('pgsql')->table('clients', function (Blueprint $table) {
            $table->renameColumn('tax_id', 'cnpj');
            $table->string('trade_name')->nullable();
            $table->string('monthly_revenue_range', 100)->nullable();
            $table->string('employee_count', 100)->nullable();
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('primary_contact_phone', 30)->nullable();
        });
    }
};

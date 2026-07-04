<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('contracts', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('restrict');
        });

        // Backfill a partir do organization_id do cliente vinculado
        DB::connection('pgsql')->statement(
            'UPDATE contracts SET organization_id = clients.organization_id
             FROM clients WHERE contracts.client_id = clients.id'
        );
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('contracts', function (Blueprint $table) {
            $table->dropForeign(['contracts_organization_id_foreign']);
            $table->dropColumn('organization_id');
        });
    }
};

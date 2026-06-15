<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Tabelas de nível raiz que pertencem a um tenant.
    // Tabelas filho (ex: task_comments, diagnostic_personas) herdam o escopo via FK do pai.
    private array $tables = [
        'clients',
        'contacts',
        'opportunities',
        'meetings',
        'macro_plans',
        'projects',
        'tasks',
        'sprints',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::connection('pgsql')->table($tableName, function (Blueprint $table) use ($tableName) {
                $table->uuid('organization_id')->nullable()->after('id');
                $table->foreign('organization_id')
                    ->references('id')->on('organizations')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::connection('pgsql')->table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropForeign(["{$tableName}_organization_id_foreign"]);
                $table->dropColumn('organization_id');
            });
        }
    }
};

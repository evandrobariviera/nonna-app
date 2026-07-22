<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // varchar(20) estourou com um tag real gerado pela IA ("Cliente em busca de
        // fitness", 27 chars) - o prompt pede um rótulo curto ("Persona A"), mas não
        // dá pra confiar só na instrução de prompt como limite de tamanho de banco.
        // SQL puro em vez de ->change() porque doctrine/dbal não está instalado.
        DB::connection('pgsql')->statement(
            'ALTER TABLE service_diagnostic_personas ALTER COLUMN tag TYPE VARCHAR(60)'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement(
            'ALTER TABLE service_diagnostic_personas ALTER COLUMN tag TYPE VARCHAR(20)'
        );
    }
};

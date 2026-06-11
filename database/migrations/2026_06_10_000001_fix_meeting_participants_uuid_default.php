<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        DB::connection('pgsql')->statement(
            'ALTER TABLE meeting_participants ALTER COLUMN id SET DEFAULT gen_random_uuid()'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement(
            'ALTER TABLE meeting_participants ALTER COLUMN id DROP DEFAULT'
        );
    }
};

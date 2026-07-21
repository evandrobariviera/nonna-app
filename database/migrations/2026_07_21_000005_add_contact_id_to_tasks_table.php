<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
            $table->foreignUuid('contact_id')->nullable()->after('created_by')
                ->constrained('contacts')->nullOnDelete();
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            ALTER TABLE tasks ADD CONSTRAINT tasks_creator_xor
            CHECK (
                (created_by IS NOT NULL AND contact_id IS NULL)
                OR (created_by IS NULL AND contact_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_creator_xor');

        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_comments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignUuid('contact_id')->nullable()->after('user_id')
                ->constrained('contacts')->nullOnDelete();
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            ALTER TABLE task_comments ADD CONSTRAINT task_comments_author_xor
            CHECK (
                (user_id IS NOT NULL AND contact_id IS NULL)
                OR (user_id IS NULL AND contact_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('ALTER TABLE task_comments DROP CONSTRAINT IF EXISTS task_comments_author_xor');

        Schema::connection('pgsql')->table('task_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};

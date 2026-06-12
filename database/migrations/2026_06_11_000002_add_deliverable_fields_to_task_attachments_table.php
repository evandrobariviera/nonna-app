<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->boolean('is_deliverable')->default(false)->after('uploaded_by');
            $table->unsignedSmallInteger('round_number')->nullable()->after('is_deliverable');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_attachments', function (Blueprint $table) {
            $table->dropColumn(['is_deliverable', 'round_number']);
        });
    }
};

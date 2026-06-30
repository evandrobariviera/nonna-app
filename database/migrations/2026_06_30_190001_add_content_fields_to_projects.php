<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->jsonb('tags')->nullable()->after('type');
            $table->jsonb('content_ideas')->nullable()->after('tags');
            $table->jsonb('traffic_phases')->nullable()->after('content_ideas');
            $table->date('start_date')->nullable()->after('traffic_phases');
            $table->date('end_date')->nullable()->after('start_date');
            $table->decimal('budget', 10, 2)->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropColumn(['tags', 'content_ideas', 'traffic_phases', 'start_date', 'end_date', 'budget']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('service_benchmarks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            // null = benchmark geral (todos os segmentos)
            $table->string('segment')->nullable();

            // 'conversion_rate' | 'avg_first_response_minutes' | 'service_score' | 'avg_sentiment_score' etc.
            $table->string('metric_key', 60);

            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('sample_size');

            $table->decimal('avg_value', 10, 2);
            $table->decimal('median_value', 10, 2);
            $table->decimal('p10_value', 10, 2);
            $table->decimal('p90_value', 10, 2);

            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['organization_id', 'metric_key', 'segment', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('service_benchmarks');
    }
};

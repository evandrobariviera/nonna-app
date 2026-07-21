<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('type', 40);
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'type', 'channel']);
        });

        DB::connection('pgsql')->statement(
            'ALTER TABLE notification_templates ALTER COLUMN id SET DEFAULT gen_random_uuid()'
        );
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('notification_templates');
    }
};

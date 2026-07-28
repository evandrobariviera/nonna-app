<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // thumbnail_url do Meta é uma URL assinada de CDN com vários parâmetros
        // de query — rotineiramente passa de 255 caracteres.
        Schema::connection('pgsql')->table('ad_ads', function (Blueprint $table) {
            $table->text('creative_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_ads', function (Blueprint $table) {
            $table->string('creative_url')->nullable()->change();
        });
    }
};

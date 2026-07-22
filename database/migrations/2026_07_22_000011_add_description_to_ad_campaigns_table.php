<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            // Texto explicando o que a campanha é e o que significa pro cliente -
            // aparece tanto na tela interna quanto no Portal do Cliente.
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};

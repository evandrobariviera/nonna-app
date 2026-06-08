<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('proposal_url', 500)->nullable()->after('notes');
            $table->unsignedSmallInteger('contract_months')->nullable()->after('proposal_url');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['proposal_url', 'contract_months']);
        });
    }
};

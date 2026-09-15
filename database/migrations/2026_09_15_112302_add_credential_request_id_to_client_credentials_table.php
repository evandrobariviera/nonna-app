<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_credentials', function (Blueprint $table) {
            $table->foreignUuid('credential_request_id')->nullable()
                ->after('client_id')
                ->constrained('client_credential_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credential_request_id');
        });
    }
};

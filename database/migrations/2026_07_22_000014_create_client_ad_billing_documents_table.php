<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('client_ad_billing_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('client_ad_account_id')->constrained('client_ad_accounts')->cascadeOnDelete();
            $table->string('type', 10); // boleto | pix
            $table->decimal('amount', 10, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->text('pix_code')->nullable();
            $table->string('filename', 300)->nullable();
            $table->string('disk_path', 500)->nullable();
            $table->string('disk', 20)->default('r2');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('client_ad_billing_documents');
    }
};

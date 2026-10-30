<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Anexos da pendência: o que explica o pedido (modelo, referência) ou o que chegou
    // (fotos, contrato assinado). Mesmo desenho de meeting_attachments: path + disk.
    public function up(): void
    {
        Schema::connection('pgsql')->create('pendency_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pendency_id')->constrained('pendencies')->cascadeOnDelete();
            $table->string('filename');
            $table->string('disk_path');
            $table->string('disk', 20);
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pendency_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('pendency_attachments');
    }
};

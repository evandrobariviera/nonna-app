<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propostas (Central de Comércio). Uma oportunidade tem N propostas; só uma é aceita.
 * Tudo que vem do catálogo é copiado pra proposta (nome, preço, regras) — mudar o catálogo
 * depois não altera proposta antiga. Ao enviar, a versão congela; mudar depois = nova versão
 * (parent_id). client_products guarda o que o cliente contratou (nasce do aceite + ganho).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable(); // FK logo abaixo, depois da chave primária existir
            $table->string('title', 160);
            $table->integer('version')->default(1);
            $table->string('status', 30)->default('draft'); // draft | pending_release | sent | accepted | rejected

            $table->foreignUuid('package_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->json('package_snapshot')->nullable();
            $table->decimal('ad_budget', 12, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->smallInteger('installments')->default(1);

            $table->boolean('group_on')->default(false);
            $table->string('group_name', 160)->nullable();
            $table->string('group_mode', 10)->nullable(); // sum | fixed
            $table->decimal('group_monthly', 12, 2)->nullable();
            $table->decimal('group_one_time', 12, 2)->nullable();

            $table->decimal('monthly_total', 12, 2)->default(0);
            $table->decimal('one_time_total', 12, 2)->default(0);
            $table->decimal('contract_total', 12, 2)->default(0);
            $table->json('calculation')->nullable();

            $table->decimal('released_percent', 5, 2)->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('release_note', 500)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('opportunity_id');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('proposals')->nullOnDelete();
        });

        Schema::create('proposal_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->string('name', 160);
            $table->string('kind', 20); // monthly | one_time
            $table->integer('quantity')->default(1);
            $table->decimal('list_price', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->json('rules')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('client_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->foreignUuid('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('kind', 20); // package | monthly | one_time
            $table->integer('quantity')->default(1);
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('one_time_price', 12, 2)->nullable();
            $table->string('status', 20)->default('active'); // active | ended
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_products');
        Schema::dropIfExists('proposal_items');
        Schema::dropIfExists('proposals');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Central de Comércio → Prospecção (etapa 2: varredura).
 *
 * Termos do Google Maps Platform (ver .claude/docs/central-comercio.md): NENHUMA tabela
 * guarda nome, endereço, telefone, nota ou avaliação do Google. Só o place_id (permitido
 * guardar para sempre) e o que é da Nonna (segmento, cidade, situação, nota calculada).
 * Nome e endereço são buscados ao vivo na hora de mostrar.
 *
 * - commerce_segments: segmentos que a Nonna aborda (termos de busca + tipo do Google).
 * - commerce_cities: cidades onde a Nonna prospecta.
 * - commerce_scans: cada varredura (cidade + segmento + nicho), com o resultado por termo.
 * - commerce_places: empresas mapeadas (place_id), uma por organização.
 * - commerce_scan_place: em quais varreduras cada empresa apareceu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);                  // "Saúde · Odontologia"
            $table->string('google_type', 60)->nullable(); // includedType da busca (ex.: dentist)
            $table->json('search_terms');                 // ["dentista", "clínica odontológica", ...]
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('commerce_cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->char('state', 2);
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'state']);
        });

        Schema::create('commerce_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('city_id')->constrained('commerce_cities')->restrictOnDelete();
            $table->foreignUuid('segment_id')->nullable()->constrained('commerce_segments')->nullOnDelete();
            $table->string('niche', 120)->nullable();     // recorte em texto livre
            $table->string('status', 20)->default('queued'); // queued | running | done | error
            $table->json('terms')->nullable();            // [{term, found, saturated}]
            $table->integer('places_found')->default(0);  // sem repetidas
            $table->integer('places_new')->default(0);    // ainda não mapeadas antes
            $table->boolean('saturated')->default(false); // algum termo voltou cheio (60)
            $table->integer('api_calls')->default(0);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('commerce_places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('place_id', 300);              // ID do Google (único dado do Google guardado)
            $table->foreignUuid('city_id')->nullable()->constrained('commerce_cities')->nullOnDelete();
            $table->foreignUuid('segment_id')->nullable()->constrained('commerce_segments')->nullOnDelete();
            $table->string('status', 20)->default('mapped'); // mapped | scored | report | sent | replied | opted_out | discarded
            $table->smallInteger('score')->nullable();       // Nota Nonna 0-100 (etapa 3)
            $table->json('score_parts')->nullable();
            $table->timestamp('last_seen_at')->nullable();   // última varredura em que apareceu
            $table->timestamp('place_id_checked_at')->nullable(); // Google recomenda renovar IDs com +12 meses
            $table->timestamps();

            $table->unique(['organization_id', 'place_id']);
            $table->index(['organization_id', 'city_id', 'segment_id', 'status']);
        });

        Schema::create('commerce_scan_place', function (Blueprint $table) {
            $table->foreignUuid('scan_id')->constrained('commerce_scans')->cascadeOnDelete();
            $table->foreignUuid('place_id')->constrained('commerce_places')->cascadeOnDelete();
            $table->primary(['scan_id', 'place_id']);
        });

        $org = DB::table('organizations')->where('slug', 'nonna')->first();
        if ($org && !DB::table('commerce_segments')->where('organization_id', $org->id)->exists()) {
            $this->seed($org->id);
        }
    }

    /** Segmentos e cidades do protótipo (30/10/2026). Editáveis na tela. */
    private function seed(string $orgId): void
    {
        $now = now();
        $segments = [
            ['Saúde · Odontologia', 'dentist', ['dentista', 'clínica odontológica', 'ortodontia', 'implante dentário']],
            ['Saúde · Clínicas e consultórios', 'doctor', ['clínica médica', 'consultório médico']],
            ['Beleza · Salões e estética', 'beauty_salon', ['salão de beleza', 'clínica de estética', 'barbearia']],
            ['Automotivo', 'car_repair', ['oficina mecânica', 'auto center', 'funilaria']],
            ['Alimentação', 'restaurant', ['restaurante', 'pizzaria', 'lanchonete']],
        ];
        foreach ($segments as $i => [$name, $type, $terms]) {
            DB::table('commerce_segments')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $orgId, 'name' => $name, 'google_type' => $type,
                'search_terms' => json_encode($terms, JSON_UNESCAPED_UNICODE), 'is_active' => true, 'sort_order' => $i,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach ([['Videira', 'SC'], ['Caçador', 'SC'], ['Joaçaba', 'SC']] as [$name, $uf]) {
            DB::table('commerce_cities')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => $orgId, 'name' => $name, 'state' => $uf,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_scan_place');
        Schema::dropIfExists('commerce_places');
        Schema::dropIfExists('commerce_scans');
        Schema::dropIfExists('commerce_cities');
        Schema::dropIfExists('commerce_segments');
    }
};

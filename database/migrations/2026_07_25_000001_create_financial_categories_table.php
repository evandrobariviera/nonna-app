<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('financial_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('restrict');

            $table->string('type', 10); // receita | despesa
            $table->string('name', 60);
            $table->string('color', 20)->default('muted');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('financial_categories');
    }

    private function seedDefaults(): void
    {
        $defaults = [
            ['type' => 'receita', 'name' => 'Mensalidade Cliente', 'color' => 'green'],
            ['type' => 'receita', 'name' => 'Projeto Único',       'color' => 'cyan'],
            ['type' => 'despesa', 'name' => 'Folha',                'color' => 'red'],
            ['type' => 'despesa', 'name' => 'Ferramentas & SaaS',   'color' => 'blue'],
            ['type' => 'despesa', 'name' => 'Impostos',             'color' => 'orange'],
            ['type' => 'despesa', 'name' => 'Pró-labore',           'color' => 'purple'],
            ['type' => 'despesa', 'name' => 'Outras Despesas',      'color' => 'muted'],
        ];

        $now = now();

        foreach (Organization::all(['id']) as $organization) {
            $rows = array_map(fn (array $category) => array_merge($category, [
                'id'              => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]), $defaults);

            DB::connection('pgsql')->table('financial_categories')->insert($rows);
        }
    }
};

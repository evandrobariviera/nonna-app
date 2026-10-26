<?php

use App\Support\PhoneKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Chave de comparação de telefone (DDD + últimos 8 dígitos, ver PhoneKey) —
// é o que deixa o lead do site e a conversa do WhatsApp da mesma pessoa
// caírem no mesmo client_leads, mesmo com o número escrito de jeitos diferentes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_leads', function (Blueprint $table) {
            $table->string('phone_key', 12)->nullable()->after('phone');
            $table->index(['client_id', 'phone_key']);
        });

        DB::connection('pgsql')->table('client_leads')->whereNotNull('phone')
            ->select('id', 'phone')->orderBy('id')
            ->each(function ($lead) {
                DB::connection('pgsql')->table('client_leads')->where('id', $lead->id)
                    ->update(['phone_key' => PhoneKey::from($lead->phone)]);
            });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_leads', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'phone_key']);
            $table->dropColumn('phone_key');
        });
    }
};

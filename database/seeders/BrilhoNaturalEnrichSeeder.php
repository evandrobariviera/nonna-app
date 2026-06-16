<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrilhoNaturalEnrichSeeder extends Seeder
{
    private string $clientId = '019eaa38-1018-7327-a3ad-0c540333a258';

    public function run(): void
    {
        $client = DB::connection('pgsql')->table('clients')->where('id', $this->clientId)->first();

        if (!$client) {
            $this->command->error("Cliente {$this->clientId} não encontrado.");
            return;
        }

        $orgId = $client->organization_id;
        $adminUser = DB::connection('pgsql')->table('users')
            ->where('email', 'evandro@nonna.com.br')
            ->first()
            ?? DB::connection('pgsql')->table('users')->orderBy('id')->first();

        if (!$adminUser) {
            $this->command->error("Nenhum usuário encontrado.");
            return;
        }

        // ── Atualizar perfil completo do cliente ──────────────────────────────
        DB::connection('pgsql')->table('clients')->where('id', $this->clientId)->update([
            'responsible_birthdate'      => '1985-07-22',
            'responsible_rg'             => '35.456.789-8',
            'responsible_marital_status' => 'casado',
            'billing_whatsapp'           => '(11) 98765-4321',
            'billing_notes'              => 'Emitir boleto até dia 3. Financeiro: Márcia (11) 3333-4445.',
            'updated_at'                 => now(),
        ]);

        // ── Recriar credenciais com encriptação correta ───────────────────────
        DB::connection('pgsql')->table('client_credentials')->where('client_id', $this->clientId)->delete();

        $credentials = [
            ['instagram',          'https://instagram.com/brilhonaturalcosmeticos', '@brilhonaturalcosmeticos', 'Insta@BN2026!',   '78.200 seguidores. 2FA ativo no celular do João.'],
            ['facebook',           'https://facebook.com/brilhonatural',             'joao@brilhonatural.com.br', 'FaceBN@2026!',  'Perfil pessoal do João. Administrador da Página.'],
            ['meta_bm',            'https://business.facebook.com',                  'joao@brilhonatural.com.br', 'MetaBM#BN2026', 'BM ID: 428374651029384. Nonna tem acesso de parceiro.'],
            ['google_meu_negocio', 'https://business.google.com',                    'mkt@brilhonatural.com.br',  'GMN_BN#2026',   '247 avaliações, nota 4.8 no Google.'],
            ['site',               'https://brilhonatural.com.br/wp-admin',          'admin_brilho',              'WP@BN2026!',    'WordPress + WooCommerce. Rafael tem acesso de Editor.'],
            ['email',              'https://mail.google.com',                         'mkt@brilhonatural.com.br',  'Gmail@BN2026!', 'Google Workspace — conta de marketing usada em todos os acessos.'],
        ];

        foreach ($credentials as [$platform, $url, $username, $password, $notes]) {
            DB::connection('pgsql')->table('client_credentials')->insert([
                'id'         => Str::uuid()->toString(),
                'client_id'  => $this->clientId,
                'platform'   => $platform,
                'access_url' => $url,
                'username'   => $username,
                'password'   => Crypt::encryptString($password),
                'notes'      => $notes,
                'created_by' => $adminUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Recriar contas de anúncios ────────────────────────────────────────
        DB::connection('pgsql')->table('client_ad_accounts')->where('client_id', $this->clientId)->delete();

        $metaAccId   = Str::uuid()->toString();
        $googleAccId = Str::uuid()->toString();
        $tiktokAccId = Str::uuid()->toString();

        DB::connection('pgsql')->table('client_ad_accounts')->insert([
            [
                'id'           => $metaAccId,
                'client_id'    => $this->clientId,
                'platform'     => 'meta_ads',
                'account_id'   => '428374651029384',
                'account_name' => 'Brilho Natural — Meta Ads Principal',
                'status'       => 'ativo',
                'notes'        => 'Conta principal. Pixel e API de Conversões configurados.',
                'created_by'   => $adminUser->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'id'           => $googleAccId,
                'client_id'    => $this->clientId,
                'platform'     => 'google_ads',
                'account_id'   => '721-845-9036',
                'account_name' => 'Brilho Natural — Google Ads',
                'status'       => 'ativo',
                'notes'        => 'Vinculado ao GA4. Conversões de compra configuradas.',
                'created_by'   => $adminUser->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'id'           => $tiktokAccId,
                'client_id'    => $this->clientId,
                'platform'     => 'tiktok_ads',
                'account_id'   => 'TT-8374651029384',
                'account_name' => 'Brilho Natural — TikTok Ads',
                'status'       => 'pausado',
                'notes'        => 'Conta criada. Em processo de verificação da conta de negócios.',
                'created_by'   => $adminUser->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);

        // ── Criar campanhas ───────────────────────────────────────────────────
        if ($orgId) {
            DB::connection('pgsql')->table('ad_campaigns')
                ->whereIn('client_ad_account_id', [$metaAccId, $googleAccId, $tiktokAccId])
                ->delete();

            $metaCamp1Id  = Str::uuid()->toString();
            $metaCamp2Id  = Str::uuid()->toString();
            $googleCampId = Str::uuid()->toString();

            $metaCamp1Ext  = '23856789012345';
            $metaCamp2Ext  = '23856790123456';
            $googleCampExt = '12345678901234';

            DB::connection('pgsql')->table('ad_campaigns')->insert([
                [
                    'id'                   => $metaCamp1Id,
                    'organization_id'      => $orgId,
                    'client_ad_account_id' => $metaAccId,
                    'platform'             => 'meta',
                    'external_id'          => $metaCamp1Ext,
                    'name'                 => 'Brilho Natural — Produtos Q2 2026',
                    'status'               => 'active',
                    'objective'            => 'OUTCOME_SALES',
                    'start_date'           => '2026-04-01',
                    'end_date'             => null,
                    'last_synced_at'       => now(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ],
                [
                    'id'                   => $metaCamp2Id,
                    'organization_id'      => $orgId,
                    'client_ad_account_id' => $metaAccId,
                    'platform'             => 'meta',
                    'external_id'          => $metaCamp2Ext,
                    'name'                 => 'Dia dos Namorados — Kit Presente',
                    'status'               => 'active',
                    'objective'            => 'OUTCOME_SALES',
                    'start_date'           => '2026-06-05',
                    'end_date'             => '2026-06-15',
                    'last_synced_at'       => now(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ],
                [
                    'id'                   => $googleCampId,
                    'organization_id'      => $orgId,
                    'client_ad_account_id' => $googleAccId,
                    'platform'             => 'google',
                    'external_id'          => $googleCampExt,
                    'name'                 => 'Search — Cosméticos Naturais SP',
                    'status'               => 'active',
                    'objective'            => 'CONVERSIONS',
                    'start_date'           => '2026-04-15',
                    'end_date'             => null,
                    'last_synced_at'       => now(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ],
            ]);

            // ── Gerar 30 dias de snapshots ────────────────────────────────────
            DB::connection('pgsql')->table('ad_daily_snapshots')
                ->whereIn('client_ad_account_id', [$metaAccId, $googleAccId])
                ->delete();

            $snapshots = [];

            for ($i = 29; $i >= 0; $i--) {
                $dt = now()->subDays($i);
                $date = $dt->toDateString();
                $isWeekend = in_array($dt->dayOfWeek, [0, 6]);

                // Meta — campanha principal
                $spend1       = round($isWeekend ? rand(85, 110) : rand(105, 140), 2);
                $impressions1 = rand(4500, 8000);
                $clicks1      = (int) round($impressions1 * (rand(14, 24) / 1000));
                $conv1        = (int) round($clicks1 * (rand(3, 8) / 100));
                $revenue1     = round($conv1 * rand(165, 225), 2);

                $snapshots[] = $this->snapshot($orgId, $metaAccId, 'meta', $metaCamp1Ext, 'Brilho Natural — Produtos Q2 2026', $date, $spend1, $revenue1, $impressions1, $clicks1, $conv1);

                // Meta — campanha Dia dos Namorados (somente últimos 10 dias)
                if ($i < 10) {
                    $spend2       = round(rand(210, 290), 2);
                    $impressions2 = rand(9000, 16000);
                    $clicks2      = (int) round($impressions2 * (rand(20, 35) / 1000));
                    $conv2        = (int) round($clicks2 * (rand(4, 9) / 100));
                    $revenue2     = round($conv2 * rand(110, 155), 2);

                    $snapshots[] = $this->snapshot($orgId, $metaAccId, 'meta', $metaCamp2Ext, 'Dia dos Namorados — Kit Presente', $date, $spend2, $revenue2, $impressions2, $clicks2, $conv2);
                }

                // Google Ads
                $spend3       = round($isWeekend ? rand(30, 45) : rand(45, 65), 2);
                $impressions3 = rand(600, 1200);
                $clicks3      = (int) round($impressions3 * (rand(38, 58) / 1000));
                $conv3        = (int) round($clicks3 * (rand(9, 17) / 100));
                $revenue3     = round($conv3 * rand(175, 245), 2);

                $snapshots[] = $this->snapshot($orgId, $googleAccId, 'google', $googleCampExt, 'Search — Cosméticos Naturais SP', $date, $spend3, $revenue3, $impressions3, $clicks3, $conv3);
            }

            foreach (array_chunk($snapshots, 25) as $chunk) {
                DB::connection('pgsql')->table('ad_daily_snapshots')->insert($chunk);
            }

            $this->command->info("✓ " . count($snapshots) . " snapshots diários gerados.");
        } else {
            $this->command->warn("organization_id não encontrado — campanhas e snapshots ignorados.");
        }

        $this->command->info("✓ Perfil atualizado.");
        $this->command->info("✓ 6 credenciais criadas.");
        $this->command->info("✓ 3 contas de anúncios criadas.");
    }

    private function snapshot(string $orgId, string $accId, string $platform, string $extId, string $name, string $date, float $spend, float $revenue, int $impressions, int $clicks, int $conversions): array
    {
        return [
            'id'                   => Str::uuid()->toString(),
            'organization_id'      => $orgId,
            'client_ad_account_id' => $accId,
            'entity_level'         => 'campaign',
            'entity_id'            => $extId,
            'entity_name'          => $name,
            'parent_entity_id'     => null,
            'platform'             => $platform,
            'snapshot_date'        => $date,
            'spend'                => $spend,
            'revenue'              => $revenue,
            'impressions'          => $impressions,
            'clicks'               => $clicks,
            'conversions'          => $conversions,
            'reach'                => (int) ($impressions * rand(65, 90) / 100),
            'cpm'                  => $impressions > 0 ? round(($spend / $impressions) * 1000, 4) : null,
            'cpc'                  => $clicks > 0      ? round($spend / $clicks, 4)               : null,
            'ctr'                  => $impressions > 0 ? round(($clicks / $impressions) * 100, 4) : null,
            'cpa'                  => $conversions > 0 ? round($spend / $conversions, 4)          : null,
            'roas'                 => $spend > 0       ? round($revenue / $spend, 4)              : null,
            'raw_data'             => null,
            'created_at'           => now(),
            'updated_at'           => now(),
        ];
    }
}

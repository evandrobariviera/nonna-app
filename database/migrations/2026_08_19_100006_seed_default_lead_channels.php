<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['kind' => 'site',             'name' => 'Site',                          'color' => 'purple'],
            ['kind' => 'facebook_lead_ad', 'name' => 'Facebook/Instagram Lead Ads',   'color' => 'blue'],
            ['kind' => 'whatsapp',         'name' => 'WhatsApp',                      'color' => 'green'],
        ];

        $now = now();

        foreach (Organization::all(['id']) as $organization) {
            $rows = array_map(fn (array $channel) => array_merge($channel, [
                'id'              => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]), $defaults);

            DB::connection('pgsql')->table('lead_channels')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('lead_channels')
            ->whereIn('kind', ['site', 'facebook_lead_ad', 'whatsapp'])
            ->delete();
    }
};

<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class SampleClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::create([
            'company_name'              => 'Studio Aura',
            'tax_id'                    => '12.345.678/0001-90',
            'website'                   => 'https://studioaura.com.br',
            'segment'                   => 'Beleza & Estética',
            'status'                    => 'active',
            'monthly_ad_budget'         => 'R$ 3.000 / mês',
            'contracted_services'       => ['trafego', 'social', 'site'],
            'contact_email'             => 'contato@studioaura.com.br',
            'contact_phone'             => '(11) 99999-0000',
            'address'                   => 'Rua das Flores, 142, Jardim Paulista – São Paulo/SP',
            'zip_code'                  => '01403-000',
            'responsible_name'          => 'Ana Paula Mendes',
            'responsible_birthdate'     => '1988-03-15',
            'responsible_cpf'           => '123.456.789-00',
            'responsible_marital_status' => 'casado',
            'payment_method'            => 'pix',
            'billing_day'               => 10,
            'billing_email'             => 'financeiro@studioaura.com.br',
            'billing_whatsapp'          => '(11) 98888-0000',
            'notes'                     => 'Cliente exigente com aprovação de artes. Prefere reuniões às terças-feiras à tarde. Tem histórico de atraso em aprovações — acompanhar de perto.',
        ]);
    }
}

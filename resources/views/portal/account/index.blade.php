<x-portal-layout>
    <x-slot name="title">Minha Conta</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Minha Conta</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Dados cadastrais, acessos e contas vinculadas à {{ $client->company_name }}.</p>
    </div>

    {{-- Empresa --}}
    <div class="card mb-5">
        <div class="px-6 py-4 border-b flex items-center gap-2" style="border-color: var(--border2)">
            <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--purple)">Dados da Empresa</span>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-5">
            @php
                $fields = [
                    'Razão Social / Nome'   => $client->company_name,
                    'CNPJ / CPF'            => $client->tax_id,
                    'Site'                  => $client->website,
                    'Segmento'              => $client->segment,
                    'E-mail de Contato'     => $client->contact_email,
                    'Telefone'              => $client->contact_phone,
                    'Endereço'              => $client->address,
                    'CEP'                   => $client->zip_code,
                ];
            @endphp
            @foreach($fields as $label => $value)
                @if($value)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">{{ $label }}</p>
                        <p class="text-sm font-semibold" style="color: var(--text)">{{ $value }}</p>
                    </div>
                @endif
            @endforeach

            @if($client->contracted_services)
                <div class="sm:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide mb-2" style="color: var(--muted)">Serviços Contratados</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($client->contracted_services as $svc)
                            <span class="text-xs font-semibold px-3 py-1 rounded-full"
                                  style="background: rgba(100, 59, 142,.1); color: var(--purple)">
                                {{ \App\Models\Client::$services[$svc] ?? $svc }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Responsável --}}
    <div class="card mb-5">
        <div class="px-6 py-4 border-b" style="border-color: var(--border2)">
            <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--purple)">Responsável Legal</span>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-5">
            @php
                $resp = [
                    'Nome Completo'         => $client->responsible_name,
                    'CPF'                   => $client->responsible_cpf,
                    'RG'                    => $client->responsible_rg,
                    'Data de Nascimento'    => $client->responsible_birthdate?->format('d/m/Y'),
                    'Estado Civil'          => \App\Models\Client::$maritalStatuses[$client->responsible_marital_status] ?? $client->responsible_marital_status,
                    'Endereço Pessoal'      => $client->responsible_address,
                ];
            @endphp
            @foreach($resp as $label => $value)
                @if($value)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">{{ $label }}</p>
                        <p class="text-sm font-semibold" style="color: var(--text)">{{ $value }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Cobrança --}}
    <div class="card mb-5">
        <div class="px-6 py-4 border-b" style="border-color: var(--border2)">
            <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--purple)">Dados de Cobrança</span>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-5">
            @php
                $billing = [
                    'Forma de Pagamento' => \App\Models\Client::$paymentMethods[$client->payment_method] ?? $client->payment_method,
                    'Dia de Vencimento'  => $client->billing_day ? "Todo dia {$client->billing_day}" : null,
                    'E-mail Financeiro'  => $client->billing_email,
                    'WhatsApp Financeiro'=> $client->billing_whatsapp,
                    'Observações'        => $client->billing_notes,
                ];
            @endphp
            @foreach($billing as $label => $value)
                @if($value)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color: var(--muted)">{{ $label }}</p>
                        <p class="text-sm font-semibold" style="color: var(--text)">{{ $value }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Contatos vinculados --}}
    @if($client->contacts->isNotEmpty())
        <div class="card mb-5">
            <div class="px-6 py-4 border-b" style="border-color: var(--border2)">
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--purple)">Contatos Vinculados</span>
            </div>
            <div class="divide-y" style="divide-color: var(--border2)">
                @foreach($client->contacts as $contact)
                    <div class="px-6 py-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold" style="color: var(--text)">{{ $contact->name }}</p>
                            <p class="text-xs mt-0.5" style="color: var(--muted)">
                                {{ $contact->job_title }}
                                @if($contact->email) · {{ $contact->email }}@endif
                                @if($contact->phone) · {{ $contact->phone }}@endif
                            </p>
                        </div>
                        @if($contact->pivot->is_primary)
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                  style="background: rgba(5,150,105,.1); color: var(--green)">Principal</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Contas de anúncios --}}
    @if($client->adAccounts->isNotEmpty())
        <div class="card mb-5">
            <div class="px-6 py-4 border-b" style="border-color: var(--border2)">
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--purple)">Contas de Anúncios</span>
            </div>
            <div class="divide-y">
                @foreach($client->adAccounts as $acc)
                    @php
                        $statusColors = [
                            'ativo'    => ['bg' => 'rgba(5,150,105,.1)',   'text' => 'var(--green)'],
                            'pausado'  => ['bg' => 'rgba(238, 121, 25,.1)',   'text' => 'var(--orange)'],
                            'suspenso' => ['bg' => 'rgba(220,38,38,.1)',   'text' => 'var(--red)'],
                        ];
                        $sc = $statusColors[$acc->status] ?? $statusColors['pausado'];
                    @endphp
                    <div class="px-6 py-4 flex items-center justify-between" style="border-color: var(--border2)">
                        <div>
                            <p class="text-sm font-bold" style="color: var(--text)">{{ $acc->platformLabel() }}</p>
                            <p class="text-xs mt-0.5 font-mono" style="color: var(--muted)">ID: {{ $acc->account_id }}</p>
                            @if($acc->notes)
                                <p class="text-xs mt-1" style="color: var(--muted)">{{ $acc->notes }}</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                              style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                            {{ $acc->statusLabel() }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Credenciais / Senhas --}}
    @if($client->credentials->isNotEmpty())
        <div class="card mb-5" x-data="{ show: {} }">
            <div class="px-6 py-4 border-b flex items-center justify-between" style="border-color: var(--border2)">
                <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--purple)">Credenciais de Acesso</span>
                <span class="text-xs" style="color: var(--muted)">Clique em 👁 para revelar a senha</span>
            </div>
            <div class="divide-y">
                @foreach($client->credentials as $cred)
                    <div class="px-6 py-4" style="border-color: var(--border2)">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-bold mb-2" style="color: var(--text)">{{ $cred->platformLabel() }}</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2">
                                    @if($cred->access_url)
                                        <div>
                                            <p class="text-xs uppercase tracking-wide font-bold mb-0.5" style="color: var(--muted)">URL</p>
                                            <a href="{{ $cred->access_url }}" target="_blank"
                                               class="text-xs font-mono break-all"
                                               style="color: var(--purple)">{{ $cred->access_url }}</a>
                                        </div>
                                    @endif
                                    @if($cred->username)
                                        <div>
                                            <p class="text-xs uppercase tracking-wide font-bold mb-0.5" style="color: var(--muted)">Usuário / Login</p>
                                            <p class="text-xs font-mono" style="color: var(--text)">{{ $cred->username }}</p>
                                        </div>
                                    @endif
                                    @if($cred->password)
                                        <div>
                                            <p class="text-xs uppercase tracking-wide font-bold mb-0.5" style="color: var(--muted)">Senha</p>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-mono" style="color: var(--text)"
                                                      x-text="show['{{ $cred->id }}'] ? '{{ $cred->password }}' : '••••••••••••'">
                                                    ••••••••••••
                                                </span>
                                                <button type="button"
                                                        class="text-sm leading-none"
                                                        style="color: var(--muted)"
                                                        @click="show['{{ $cred->id }}'] = !show['{{ $cred->id }}']"
                                                        x-text="show['{{ $cred->id }}'] ? '🙈' : '👁'">
                                                    👁
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                @if($cred->notes)
                                    <p class="text-xs mt-3 italic" style="color: var(--muted)">{{ $cred->notes }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</x-portal-layout>

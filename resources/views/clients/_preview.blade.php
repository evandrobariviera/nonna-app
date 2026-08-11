{{-- Preview leve pro painel lateral (canvas). Não inclui layout — só o fragmento.
     x-data local: o painel injeta este HTML via x-html, e o MutationObserver
     do Alpine inicializa qualquer x-data/x-show novo que apareça aqui dentro. --}}
<div x-data="{ tab: 'geral' }" class="flex flex-col">

    {{-- Cabeçalho fixo com abas — sticky pra não rolar junto com o conteúdo --}}
    <div class="sticky top-0 z-10 px-6 pt-5" style="background:var(--bg); border-bottom:1px solid var(--border2)">
        <div class="flex items-start gap-3 mb-4">
            @if($client->logoUrl())
                <img src="{{ $client->logoUrl() }}" alt="{{ $client->displayName() }}"
                     class="h-10 w-10 rounded-lg object-cover flex-shrink-0" style="border:1px solid var(--border2)">
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-base font-bold truncate" style="color:var(--text)">{{ $client->displayName() }}</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="badge badge-{{ $client->statusColor() }}">{{ $client->statusLabel() }}</span>
                    @if($client->segment)
                        <span class="text-xs" style="color:var(--muted)">{{ $client->segment }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn" :class="{ active: tab === 'geral' }" @click="tab = 'geral'">Geral</button>
            <button class="tab-btn" :class="{ active: tab === 'briefing' }" @click="tab = 'briefing'">Briefing</button>
            <button class="tab-btn" :class="{ active: tab === 'contatos' }" @click="tab = 'contatos'">
                Contatos
                @if($client->contacts->count())<span class="tab-count">{{ $client->contacts->count() }}</span>@endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'senhas' }" @click="tab = 'senhas'">
                Senhas
                @if($client->credentials->count())<span class="tab-count">{{ $client->credentials->count() }}</span>@endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'links' }" @click="tab = 'links'">
                Links
                @if($client->links->count())<span class="tab-count">{{ $client->links->count() }}</span>@endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'planejamentos' }" @click="tab = 'planejamentos'">
                Planejamentos
                @if($activeMacroplans->count())<span class="tab-count">{{ $activeMacroplans->count() }}</span>@endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'campanhas' }" @click="tab = 'campanhas'">
                Campanhas
                @if($activeCampaigns->count())<span class="tab-count">{{ $activeCampaigns->count() }}</span>@endif
            </button>
            <button class="tab-btn" :class="{ active: tab === 'dossies' }" @click="tab = 'dossies'">
                Dossiê
                @if($client->dossiers->count())<span class="tab-count">{{ $client->dossiers->count() }}</span>@endif
            </button>
        </div>
    </div>

    <div class="p-6">

        {{-- TAB: GERAL --}}
        <div x-show="tab === 'geral'" x-cloak class="flex flex-col gap-6">

            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Contato</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Responsável</p>
                        <p class="text-sm" style="color:var(--text)">{{ $client->responsible_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">E-mail</p>
                        <p class="text-sm truncate" style="color:var(--text)">{{ $client->contact_email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Telefone</p>
                        <p class="text-sm" style="color:var(--text)">{{ $client->contact_phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Verba mensal</p>
                        <p class="text-sm" style="color:var(--text)">{{ $client->monthly_ad_budget ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="card card-body text-center">
                    <p class="text-xl font-bold" style="color:var(--purple)">{{ $client->macroplans_count }}</p>
                    <p class="text-xs" style="color:var(--muted)">Planejamentos</p>
                </div>
                <div class="card card-body text-center">
                    <p class="text-xl font-bold" style="color:var(--purple)">{{ $client->ad_accounts_count }}</p>
                    <p class="text-xs" style="color:var(--muted)">Contas de anúncio</p>
                </div>
            </div>

            @if($recentMacroplans->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Planejamentos recentes</p>
                    <div class="flex flex-col gap-2">
                        @foreach($recentMacroplans as $plan)
                            <a href="{{ route('macroplans.edit', $plan) }}"
                               class="card card-body flex items-center justify-between gap-3 transition-colors"
                               onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                                <span class="text-sm font-medium truncate" style="color:var(--text)">{{ $plan->title }}</span>
                                <span class="badge badge-{{ $plan->statusColor() }} flex-shrink-0">{{ $plan->statusLabel() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <a href="{{ route('clients.show', $client) }}"
               class="text-center px-4 py-2.5 text-xs font-bold transition-colors"
               style="border:1px solid var(--border2); color:var(--muted2)"
               onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
               onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                Ver página completa do cliente →
            </a>
        </div>

        {{-- TAB: BRIEFING --}}
        <div x-show="tab === 'briefing'" x-cloak>
            @if($client->briefing)
                <div class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.75">{{ $client->briefing }}</div>
            @else
                <p class="text-sm" style="color:var(--muted)">Nenhum briefing registrado ainda.</p>
            @endif
            <a href="{{ route('clients.show', ['client' => $client, 'tab' => 'briefing']) }}"
               class="text-xs font-semibold mt-4 inline-block" style="color:var(--purple)">
                {{ $client->briefing ? 'Editar na página completa →' : '+ Adicionar briefing →' }}
            </a>
        </div>

        {{-- TAB: CONTATOS --}}
        <div x-show="tab === 'contatos'" x-cloak>
            @if($client->contacts->isEmpty())
                <p class="text-sm" style="color:var(--muted)">Nenhum contato vinculado.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($client->contacts as $contact)
                        <div class="card card-body">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold truncate" style="color:var(--text)">
                                        {{ $contact->name }}
                                        @if($contact->pivot->is_primary)
                                            <span class="badge badge-purple" style="font-size:9px">Principal</span>
                                        @endif
                                    </p>
                                    @if($contact->job_title)
                                        <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $contact->job_title }}</p>
                                    @endif
                                </div>
                                @if($contact->pivot->role)
                                    <span class="badge flex-shrink-0" style="font-size:10px">{{ $contactRoles[$contact->pivot->role] ?? $contact->pivot->role }}</span>
                                @endif
                            </div>
                            <div class="flex flex-col gap-0.5 mt-2">
                                @if($contact->email)
                                    <a href="mailto:{{ $contact->email }}" class="text-xs font-mono transition-colors" style="color:var(--purple)">{{ $contact->email }}</a>
                                @endif
                                @if($contact->whatsapp)
                                    <p class="text-xs font-mono" style="color:var(--muted2)">{{ $contact->whatsapp }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: SENHAS --}}
        <div x-show="tab === 'senhas'" x-cloak>
            @if($client->credentials->isEmpty())
                <p class="text-sm" style="color:var(--muted)">Nenhuma credencial cadastrada.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($client->credentials as $cred)
                        <div class="card card-body" x-data="{ show: false }">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $cred->platformLabel() }}</p>
                                @if($cred->access_url)
                                    <a href="{{ $cred->access_url }}" target="_blank" rel="noopener"
                                       class="text-xs font-mono truncate max-w-[160px]" style="color:var(--purple)">
                                        {{ parse_url($cred->access_url, PHP_URL_HOST) ?? $cred->access_url }}
                                    </a>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Usuário</p>
                                    <p class="text-sm font-mono" style="color:var(--muted2)">{{ $cred->username ?: '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Senha</p>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-mono" :style="show ? 'color:var(--text)' : 'color:var(--muted)'">
                                            <span x-show="!show">••••••••</span>
                                            <span x-show="show" x-cloak>{{ $cred->password ?: '—' }}</span>
                                        </span>
                                        <button @click="show = !show" class="text-xs font-semibold" style="color:var(--purple)">
                                            <span x-show="!show">Ver</span>
                                            <span x-show="show" x-cloak>Ocultar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @if($cred->notes)
                                <p class="text-xs mt-2" style="color:var(--muted)">{{ $cred->notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: LINKS --}}
        <div x-show="tab === 'links'" x-cloak>
            @if($client->links->isEmpty())
                <p class="text-sm" style="color:var(--muted)">Nenhum link cadastrado.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($client->links as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener"
                           class="card card-body flex items-center justify-between gap-3 transition-colors"
                           onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $link->displayLabel() }}</p>
                                <p class="text-xs font-mono truncate" style="color:var(--purple)">{{ $link->url }}</p>
                                @if($link->notes)
                                    <p class="text-xs mt-0.5 truncate" style="color:var(--muted)">{{ $link->notes }}</p>
                                @endif
                            </div>
                            <span style="color:var(--muted); font-size:12px">↗</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: PLANEJAMENTOS ATIVOS --}}
        <div x-show="tab === 'planejamentos'" x-cloak>
            @if($activeMacroplans->isEmpty())
                <p class="text-sm" style="color:var(--muted)">Nenhum planejamento ativo no momento.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($activeMacroplans as $plan)
                        <a href="{{ route('macroplans.edit', $plan) }}"
                           class="card card-body flex items-center justify-between gap-3 transition-colors"
                           onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate" style="color:var(--text)">{{ $plan->title }}</p>
                                @if($plan->period_start)
                                    <p class="text-xs mt-0.5" style="color:var(--muted)">
                                        {{ $plan->period_start->format('d/m/Y') }} @if($plan->period_end) — {{ $plan->period_end->format('d/m/Y') }} @endif
                                    </p>
                                @endif
                            </div>
                            <span class="badge badge-{{ $plan->statusColor() }} flex-shrink-0">{{ $plan->statusLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: CAMPANHAS ATIVAS --}}
        <div x-show="tab === 'campanhas'" x-cloak>
            @if($activeCampaigns->isEmpty())
                <p class="text-sm" style="color:var(--muted)">Nenhuma campanha ativa no momento.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($activeCampaigns as $campaign)
                        <a href="{{ route('campaigns.show', $campaign) }}"
                           class="card card-body flex items-center justify-between gap-3 transition-colors"
                           onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate" style="color:var(--text)">{{ $campaign->name }}</p>
                                <p class="text-xs mt-0.5" style="color:var(--muted)">
                                    {{ $campaign->platform }} · {{ $campaign->adAccount?->account_name ?? $campaign->adAccount?->account_id }}
                                </p>
                            </div>
                            <span class="badge badge-{{ $campaign->managementStatusColor() }} flex-shrink-0">{{ $campaign->managementStatusLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- TAB: DOSSIÊ DE MARCA --}}
        <div x-show="tab === 'dossies'" x-cloak>
            @if($client->dossiers->isEmpty())
                <p class="text-sm" style="color:var(--muted)">Nenhum dossiê criado.</p>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($client->dossiers as $dossier)
                        <a href="{{ route('clients.dossiers.show', [$client, $dossier]) }}"
                           class="card card-body flex items-center justify-between gap-3 transition-colors"
                           onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate" style="color:var(--text)">{{ $dossier->title }}</p>
                                <p class="text-xs mt-0.5" style="color:var(--muted)">v{{ $dossier->version }} · criado em {{ $dossier->created_at->format('d/m/Y') }}</p>
                            </div>
                            <span class="text-xs font-mono px-2 py-0.5 flex-shrink-0"
                                  style="background:{{ $dossier->faseColor() }}22; color:{{ $dossier->faseColor() }}; border:1px solid {{ $dossier->faseColor() }}44">
                                {{ $dossier->faseLabel() }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>

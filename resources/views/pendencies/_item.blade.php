{{-- Uma pendência — linha do bloco <x-pendencies-panel> ou cartão da Central
     ($showOrigin = true mostra cliente + de onde veio). --}}
@php
    $open  = $p->isOpen();
    $color = $p->agingColor();
    $when  = $open ? $p->whenText() : null;
    $origin = $showOrigin ? $p->originLink() : null;
@endphp

<div x-data="{ editing: false, resolving: false, menu: false }" @pendency-form-close.stop="editing = false"
     class="{{ $showOrigin ? 'card p-3' : 'py-3' }}"
     style="{{ $showOrigin ? 'border-left:3px solid ' . $color : (!$last ? 'border-bottom:1px solid var(--border2)' : '') }}">

    <div x-show="!editing" class="flex items-start gap-2.5">
        <span class="flex-shrink-0 mt-0.5" style="color:{{ $color }}" title="{{ $p->kindLabel() }}">
            <x-icon :name="$p->kindIcon()" size="16" />
        </span>

        <div class="flex-1 min-w-0">
            @if($showOrigin && $p->client)
                <p class="text-xs font-semibold mb-0.5" style="color:var(--muted)">{{ $p->client->displayName() }}</p>
            @endif

            {{-- Título + etiqueta de status (Aberta / Cobrada / Resolvida / Cancelada) — contorno
                 com bolinha, pra não confundir com a etiqueta cheia de "quem deve". --}}
            @php $stColor = ['muted' => 'var(--muted2)', 'orange' => 'var(--orange)', 'green' => 'var(--green)', 'red' => 'var(--red)'][$p->statusColor()] ?? 'var(--muted2)'; @endphp
            <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium min-w-0" style="color:var(--text); {{ $open ? '' : 'text-decoration:line-through; opacity:.55' }}">
                    {{ $p->title }}
                </p>
                <span class="flex-shrink-0 inline-flex items-center gap-1 font-semibold"
                      style="font-size:10px; line-height:1; padding:3px 7px; border-radius:999px; color:{{ $stColor }}; border:1px solid {{ $stColor }}; white-space:nowrap">
                    <span style="width:6px; height:6px; border-radius:50%; background:{{ $stColor }}"></span>
                    {{ $p->statusLabel() }}{{ $p->status === 'cobrada' ? ' ' . $p->nudge_count . 'x' : '' }}
                </span>
            </div>
            @if($p->description)
                <p class="text-xs mt-0.5" style="color:var(--muted2); white-space:pre-line">{{ $p->description }}</p>
            @endif

            <div class="flex items-center flex-wrap gap-x-1.5 gap-y-1 mt-1.5 text-xs" style="color:var(--muted)">
                @if(!$showOrigin)
                    <span class="badge badge-{{ \App\Models\Pendency::$owedBy[$p->owed_by]['color'] ?? 'muted' }}" style="font-size:10px; padding:1px 6px">
                        {{ \App\Models\Pendency::$owedBy[$p->owed_by]['label'] ?? $p->owed_by }}
                    </span>
                @endif
                <span>deve: <strong style="color:var(--muted2)">{{ $p->owedName() }}</strong></span>
                @if($p->owner)
                    <span style="color:var(--border2)">·</span>
                    <span class="flex items-center gap-1" title="Quem cobra">
                        cobra: @unless($showOrigin)<x-user-avatar :user="$p->owner" size="4" />@endunless {{ \Illuminate\Support\Str::of($p->owner->name)->explode(' ')->first() }}
                    </span>
                @endif
                @if($open)
                    <span style="color:var(--border2)">·</span>
                    <span style="color:{{ $color }}; font-weight:600">{{ $when }}</span>
                @endif
            </div>

            @if($open && $p->nudge_count > 0)
                <p class="text-xs mt-1" style="color:var(--orange)">
                    Última cobrança {{ $p->last_nudged_at?->diffForHumans() }}
                </p>
            @endif

            @if(!$open)
                <p class="text-xs mt-1" style="color:var(--muted)">
                    {{ $p->resolvedBy ? 'por ' . $p->resolvedBy->name : '' }}{{ $p->resolved_at ? ' em ' . $p->resolved_at->format('d/m') : '' }}
                    @if($p->resolution_note)
                        — <span style="color:var(--muted2)">{{ $p->resolution_note }}</span>
                    @endif
                </p>
            @endif

            @if($origin)
                <a href="{{ $origin['url'] }}" class="text-xs mt-1 block truncate" style="color:var(--purple)">{{ $origin['label'] }}</a>
            @elseif($p->source_comment_id && !$showOrigin)
                <a href="#comentarios" class="text-xs mt-1 block" style="color:var(--muted2)">do comentário</a>
            @endif

            {{-- Ações --}}
            <div class="flex items-center gap-2 mt-2">
                @if($open)
                    <button type="button" class="btn btn-xs btn-ghost" style="color:var(--green); border-color:rgba(16,185,129,.35)" @click="resolving = !resolving">
                        ✓ Resolver
                    </button>
                    <form method="POST" action="{{ route('pendencies.nudge', $p) }}">
                        @csrf @method('PATCH')
                        @if(!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                        <button type="submit" class="btn btn-xs btn-ghost" title="Registrar que você cobrou (WhatsApp, e-mail, ligação…)">Cobrei</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('pendencies.reopen', $p) }}">
                        @csrf @method('PATCH')
                        @if(!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                        <button type="submit" class="btn btn-xs btn-ghost">Reabrir</button>
                    </form>
                @endif

                <div class="relative ml-auto">
                    <button type="button" class="text-xs px-1.5" style="color:var(--muted2)" @click="menu = !menu" title="Mais ações">•••</button>
                    <div x-show="menu" @click.outside="menu = false" x-cloak
                         class="absolute right-0 mt-1 z-20 py-1 text-xs"
                         style="min-width:150px; background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1)">
                        <button type="button" class="w-full text-left px-3 py-2" style="color:var(--muted2)"
                                onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'"
                                @if($showOrigin)
                                    @click="menu = false; $dispatch('edit-pendency', @js($p->only(['id', 'client_id', 'title', 'description', 'kind', 'owed_by', 'owed_contact_id', 'owed_user_id', 'owed_party', 'owner_id']) + ['due_date' => $p->due_date?->format('Y-m-d')]))"
                                @else
                                    @click="editing = true; menu = false"
                                @endif
                                >Editar</button>
                        @if($open)
                            <form method="POST" action="{{ route('pendencies.cancel', $p) }}"
                                  @submit.prevent="if (await $store.confirmDialog.ask('Cancelar essa pendência? (não é mais necessária)')) $el.submit()">
                                @csrf @method('PATCH')
                                @if(!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                                <button type="submit" class="w-full text-left px-3 py-2" style="color:var(--muted2)"
                                        onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">Não precisa mais</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('pendencies.destroy', $p) }}"
                              @submit.prevent="if (await $store.confirmDialog.ask('Excluir essa pendência? Use só se foi criada por engano.')) $el.submit()">
                            @csrf @method('DELETE')
                            @if(!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                            <button type="submit" class="w-full text-left px-3 py-2" style="color:var(--red)"
                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">Excluir</button>
                        </form>
                    </div>
                </div>
            </div>

            @if($open)
                <form x-show="resolving" x-cloak method="POST" action="{{ route('pendencies.resolve', $p) }}" class="mt-2 flex items-center gap-2">
                    @csrf @method('PATCH')
                    @if(!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                    <input type="text" name="resolution_note" maxlength="2000" placeholder="O que chegou? (opcional)"
                           class="flex-1 min-w-0"
                           style="background:var(--s3); border:1px solid var(--border); border-radius:6px; color:var(--text); padding:5px 9px; font-size:12px; outline:none">
                    <button type="submit" class="btn btn-xs btn-success">Confirmar</button>
                </form>
            @endif
        </div>
    </div>

    @if(!$showOrigin)
    <template x-if="editing">
        <div>
            @include('pendencies._form', ['pendency' => $p, 'users' => $users, 'contacts' => $contacts])
        </div>
    </template>
    @endif
</div>

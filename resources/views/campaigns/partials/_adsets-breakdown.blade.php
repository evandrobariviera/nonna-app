{{-- Breakdown de conjuntos de anúncios (adsets) e seus anúncios/criativos —
     mesmo período selecionado na tela da campanha (campaigns/show.blade.php).
     Espera: $campaign (com adsets.ads eager-loaded), $adsetStats, $adStats
     (Collection<external_id, stdClass> já com cpa/ctr/roas calculados via
     CampaignController::buildCampaignStats()), $periodLabel. --}}
<div class="card card-body-lg">
    <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">
        Conjuntos de anúncios
    </p>

    @if($campaign->adsets->isEmpty())
        <div class="py-6 text-center" style="border:1px dashed var(--border2)">
            <p class="text-xs" style="color:var(--muted)">Nenhum conjunto de anúncios sincronizado ainda.</p>
        </div>
    @else
        <table class="nonna-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Conjunto</th>
                    <th>Status</th>
                    <th>Gasto ({{ $periodLabel }})</th>
                    <th>CPA</th>
                    <th>CTR</th>
                    <th>ROAS</th>
                </tr>
            </thead>
            @foreach($campaign->adsets as $adset)
                @php($adsetRow = $adsetStats->get($adset->external_id))
                <tbody x-data="{ open: false, lockOpen: false }">
                    <tr>
                        <td class="whitespace-nowrap">
                            <button type="button" @click="open = !open" class="text-xs" style="color:var(--muted)">
                                <span :class="open ? 'rotate-90' : ''" class="inline-block transition-transform">▶</span>
                            </button>
                            <button type="button" @click="lockOpen = !lockOpen" class="text-xs ml-1"
                                    style="color:{{ $adset->optimization_locked_reason ? 'var(--orange)' : 'var(--muted)' }}"
                                    title="{{ $adset->optimization_locked_reason ? 'Travado: ' . $adset->optimization_locked_reason : 'Travar otimização deste conjunto' }}">
                                🔒
                            </button>
                        </td>
                        <td class="font-semibold text-[var(--text)]">{{ $adset->name }}</td>
                        <td class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $adset->status }}</td>
                        <td class="text-xs font-mono text-[var(--muted2)]">R$ {{ number_format($adsetRow->spend ?? 0, 2, ',', '.') }}</td>
                        <td class="text-xs font-mono text-[var(--muted2)]">
                            {{ ($adsetRow->cpa ?? null) !== null ? 'R$ ' . number_format($adsetRow->cpa, 2, ',', '.') : '—' }}
                        </td>
                        <td class="text-xs font-mono text-[var(--muted2)]">
                            {{ ($adsetRow->ctr ?? null) !== null ? number_format($adsetRow->ctr, 2, ',', '.') . '%' : '—' }}
                        </td>
                        <td class="text-xs font-mono text-[var(--muted2)]">
                            {{ ($adsetRow->roas ?? null) !== null ? number_format($adsetRow->roas, 2, ',', '.') . 'x' : '—' }}
                        </td>
                    </tr>
                    <tr x-show="lockOpen" x-cloak>
                        <td></td>
                        <td colspan="6" class="!py-2">
                            <form method="POST" action="{{ route('adsets.update-lock', $adset) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <input type="text" name="optimization_locked_reason" value="{{ $adset->optimization_locked_reason }}"
                                       placeholder="Motivo pra travar (deixe em branco pra destravar)"
                                       class="flex-1 px-2 py-1.5 text-xs" style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                <button type="submit" class="btn btn-xs" style="border:1px solid var(--border2); color:var(--text)">
                                    {{ $adset->optimization_locked_reason ? 'Atualizar' : 'Travar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                    <tr x-show="open" x-cloak>
                        <td></td>
                        <td colspan="6" class="!py-0">
                            @if($adset->ads->isEmpty())
                                <p class="text-xs py-3" style="color:var(--muted)">Nenhum anúncio sincronizado neste conjunto.</p>
                            @else
                                <table class="w-full">
                                    <tbody>
                                        @foreach($adset->ads as $ad)
                                            @php($adRow = $adStats->get($ad->external_id))
                                            <tr style="border-top:1px solid var(--border2)">
                                                <td class="py-2 pr-3" style="width:48px">
                                                    @if($ad->creative_url)
                                                        <img src="{{ $ad->creative_url }}" alt="" class="rounded" style="width:36px;height:36px;object-fit:cover">
                                                    @else
                                                        <div class="rounded flex items-center justify-center" style="width:36px;height:36px;background:var(--s3);color:var(--muted);font-size:10px">
                                                            {{ strtoupper(substr($ad->creative_type ?? '?', 0, 1)) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-3 text-xs font-semibold" style="color:var(--text)">{{ $ad->name }}</td>
                                                <td class="py-2 pr-3 text-xs font-mono uppercase" style="color:var(--muted)">{{ $ad->status }}</td>
                                                <td class="py-2 pr-3 text-xs font-mono" style="color:var(--muted2)">R$ {{ number_format($adRow->spend ?? 0, 2, ',', '.') }}</td>
                                                <td class="py-2 pr-3 text-xs font-mono" style="color:var(--muted2)">
                                                    {{ ($adRow->cpa ?? null) !== null ? 'R$ ' . number_format($adRow->cpa, 2, ',', '.') : '—' }}
                                                </td>
                                                <td class="py-2 pr-3 text-xs font-mono" style="color:var(--muted2)">
                                                    {{ ($adRow->ctr ?? null) !== null ? number_format($adRow->ctr, 2, ',', '.') . '%' : '—' }}
                                                </td>
                                                <td class="py-2 text-xs font-mono" style="color:var(--muted2)">
                                                    {{ ($adRow->roas ?? null) !== null ? number_format($adRow->roas, 2, ',', '.') . 'x' : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </td>
                    </tr>
                </tbody>
            @endforeach
        </table>
    @endif
</div>

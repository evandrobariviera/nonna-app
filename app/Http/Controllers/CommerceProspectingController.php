<?php

namespace App\Http\Controllers;

use App\Jobs\CollectCommercePlaceJob;
use App\Jobs\RunCommerceScanJob;
use App\Models\CommerceCity;
use App\Models\CommercePlace;
use App\Models\CommerceScan;
use App\Models\CommerceSegment;
use App\Services\Commerce\GooglePlaces;
use App\Services\Commerce\GoogleUsage;
use App\Services\Commerce\OpportunitySignal;
use App\Services\Commerce\PlaceScorer;
use App\Services\Commerce\ScanRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Central de Comércio → Prospecção (etapa 2: varredura).
 * Empresas mapeadas (só place_id no banco; nome/endereço ao vivo do Google), varreduras
 * e a configuração de segmentos e cidades. Escrita de configuração: só admin/dono.
 */
class CommerceProspectingController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $org = app('currentOrganization');
        $tab = in_array($request->get('aba'), ['empresas', 'varreduras', 'configurar'], true) ? $request->get('aba') : 'empresas';
        $segments = CommerceSegment::orderBy('sort_order')->orderBy('name')->get();
        $cities = CommerceCity::orderBy('name')->get();
        $google = GooglePlaces::forOrganization($org->id);

        $data = compact('tab', 'segments', 'cities') + ['configured' => $google->configured()];
        $data['googleTypes'] = \App\Services\Commerce\GooglePlaceTypes::all();
        $data['scans'] = CommerceScan::with('city', 'segment', 'creator:id,name')->latest()->limit(50)->get();
        $data['running'] = $data['scans']->whereIn('status', ['queued', 'running'])->isNotEmpty();
        // Coleta por varredura: quantas empresas já foram coletadas.
        $data['progress'] = \Illuminate\Support\Facades\DB::connection('pgsql')->table('commerce_scan_place as sp')
            ->join('commerce_places as p', 'p.id', '=', 'sp.place_id')->whereIn('sp.scan_id', $data['scans']->pluck('id'))
            ->selectRaw('sp.scan_id, count(*) as total, count(p.collected_at) as collected')->groupBy('sp.scan_id')->get()->keyBy('scan_id');

        if ($tab === 'empresas') {
            $filters = $request->only('cidade', 'segmento', 'varredura', 'situacao', 'sinal', 'busca', 'novas', 'ordem');
            // "Ver as N novas": só as empresas que entraram na última rodada da varredura.
            $novasDesde = filled($filters['novas'] ?? null) && filled($filters['varredura'] ?? null)
                ? $data['scans']->firstWhere('id', $filters['varredura'])?->lastRunStart()
                : null;
            // Recorte (cidade, segmento, varredura, busca) sem a situação: base dos números do
            // topo e das contagens dos filtros rápidos (protótipo da Central de Comércio).
            $base = CommercePlace::query()
                ->when(filled($filters['busca'] ?? null), fn ($q) => $q->whereRaw("google_data->>'name' ilike ?", ['%' . trim($filters['busca']) . '%']))
                ->when($filters['cidade'] ?? null, fn ($q, $v) => $q->where('city_id', $v))
                ->when($filters['segmento'] ?? null, fn ($q, $v) => $q->where('segment_id', $v))
                ->when($filters['varredura'] ?? null, fn ($q, $v) => $q->whereHas('scans', fn ($s) => $s->whereKey($v)))
                ->when($novasDesde, fn ($q, $d) => $q->where('commerce_places.created_at', '>=', $d));

            $counts = (clone $base)->toBase()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
            $kpi = (clone $base)->toBase()->where('status', '!=', 'discarded')->selectRaw("
                count(*) as mapeadas,
                count(score) as com_nota,
                count(*) filter (where json_array_length(coalesce(site_signals->'contacts'->'whatsapp', '[]'::json)) > 0) as com_whatsapp
            ")->first();

            $query = (clone $base)->with('city', 'segment')
                ->when(isset(CommercePlace::$statuses[$filters['situacao'] ?? '']), fn ($q) => $q->where('status', $filters['situacao']), fn ($q) => $q->where('status', '!=', 'discarded'));

            match ($filters['ordem'] ?? '') {
                'maior'      => $query->orderByRaw('score is null')->orderByDesc('score')->orderByDesc('created_at'),
                'avaliacoes' => $query->orderByRaw("coalesce((google_data->>'reviews')::int, -1) desc")->orderByDesc('created_at'),
                'recentes'   => $query->orderByDesc('created_at'),
                default      => $query->orderByRaw('score is null')->orderBy('score')->orderByDesc('created_at'),
            };
            $advertisers = $this->advertisers();
            if (isset(OpportunitySignal::$labels[$filters['sinal'] ?? ''])) {
                // Sinal é calculado (site + conferência de anúncio): filtra em memória.
                $all = $query->limit(3000)->get()->filter(fn ($p) => OpportunitySignal::for($p, $advertisers->has($p->segment_id . '|' . $p->city_id)) === $filters['sinal'])->values();
                $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
                $places = new \Illuminate\Pagination\LengthAwarePaginator($all->forPage($page, self::PER_PAGE)->values(), $all->count(), self::PER_PAGE, $page, ['path' => $request->url(), 'query' => $request->query()]);
            } else {
                $places = $query->paginate(self::PER_PAGE)->withQueryString();
            }
            $signals = $places->getCollection()->mapWithKeys(fn ($p) => [$p->id => OpportunitySignal::for($p, $advertisers->has($p->segment_id . '|' . $p->city_id))]);
            // Guardado no App; só quem ainda não tem dado do Google é consultado (uma vez).
            $live = $this->googleNames($places->getCollection(), $google);

            $data += compact('filters', 'places', 'live', 'counts', 'signals', 'kpi');
        }

        // Depois das consultas desta página, pra o medidor já mostrar o que ela gastou.
        $data['usage'] = GoogleUsage::summary($org->id);
        // Custo estimado do mês: o que passou da franquia grátis de cada tipo de consulta.
        $data['usageUsd'] = collect($data['usage'])->sum(fn ($u) => $u['free'] === null ? 0
            : max(0, $u['used'] - $u['free']) * ($u['usd_per_1000'] ?? 0) / 1000);

        return view('commerce.prospecting', $data);
    }

    public function storeScan(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');
        $v = $request->validate([
            'city_id'    => ['required', Rule::exists('pgsql.commerce_cities', 'id')->where('organization_id', $org->id)],
            'segment_id' => ['nullable', Rule::exists('pgsql.commerce_segments', 'id')->where('organization_id', $org->id)],
            'niche'      => ['nullable', 'string', 'max:120'],
        ], [], ['city_id' => 'cidade', 'segment_id' => 'segmento', 'niche' => 'nicho']);

        if (empty($v['segment_id']) && blank($v['niche'] ?? null)) {
            return back()->withErrors(['segment_id' => 'Escolha um segmento ou escreva um nicho.'])->withInput();
        }
        if (!GooglePlaces::forOrganization($org->id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }

        $scan = CommerceScan::create([
            'organization_id' => $org->id,
            'city_id'         => $v['city_id'],
            'segment_id'      => $v['segment_id'] ?? null,
            'niche'           => filled($v['niche'] ?? null) ? trim($v['niche']) : null,
            'status'          => 'queued',
            'created_by'      => $request->user()->id,
        ]);
        RunCommerceScanJob::dispatch($scan->id);

        $n = count(ScanRunner::queries($scan->load('city', 'segment')));

        return redirect()->route('commerce.prospecting', ['aba' => 'varreduras'])
            ->with('success', "Varredura começou: {$n} busca(s) no Google. Leva menos de um minuto.");
    }

    /** Coleta (Google + site + nota) de todas as empresas da varredura, em segundo plano. */
    /**
     * Roda a mesma varredura outra vez, atualizando a própria linha (Evandro, 26/11/2026).
     * Empresa que já existia não duplica (ScanRunner::store); places_new vira "novas nesta rodada".
     */
    public function rerunScan(CommerceScan $scan): RedirectResponse
    {
        if (in_array($scan->status, ['queued', 'running'], true)) {
            return back()->with('error', 'Essa varredura já está rodando.');
        }
        if (!GooglePlaces::forOrganization($scan->organization_id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }

        $scan->update([
            'status'      => 'queued',
            'error'       => null,
            'runs'        => $scan->status === 'error' ? $scan->runs : $scan->runs + 1,
            'last_run_at' => now(),
        ]);
        RunCommerceScanJob::dispatch($scan->id);

        return redirect()->route('commerce.prospecting', ['aba' => 'varreduras'])
            ->with('success', 'Varrendo de novo: ' . $scan->label() . '. Empresas que já estavam não duplicam; as novas aparecem na linha.');
    }

    public function collectScan(Request $request, CommerceScan $scan): RedirectResponse
    {
        if (!GooglePlaces::forOrganization($scan->organization_id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }
        $places = $scan->places()->whereNotIn('status', ['discarded', 'opted_out'])
            ->when(!$request->boolean('todas'), fn ($q) => $q->where(fn ($w) => $w->whereNull('collected_at')->orWhere('collected_at', '<', now()->subDays(7))))
            ->pluck('commerce_places.id');

        if ($stop = $this->paidGate($request, $scan->organization_id, $places->count(), route('commerce.scans.collect', $scan), array_filter(['todas' => $request->boolean('todas') ? 1 : null]))) {
            return $stop;
        }
        $allowPaid = $request->boolean('aceito_pago');
        foreach ($places as $id) {
            CollectCommercePlaceJob::dispatch($id, $allowPaid);
        }

        return redirect()->route('commerce.prospecting', ['aba' => 'varreduras'])->with('success', $places->count()
            ? "{$places->count()} empresa(s) na fila de coleta. Cada uma leva até 1 minuto (site e velocidade no celular)."
            : 'Todas as empresas dessa varredura já foram coletadas nos últimos 7 dias.');
    }

    /** "Coletar selecionadas": as empresas marcadas na lista (ids separados por vírgula ou array). */
    public function collectSelected(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');
        if (!GooglePlaces::forOrganization($org->id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }
        $raw = $request->input('ids', []);
        $ids = collect(is_array($raw) ? $raw : explode(',', (string) $raw))->filter()->unique()->take(200);
        $places = CommercePlace::whereIn('id', $ids)->whereNotIn('status', ['discarded', 'opted_out'])->pluck('id');
        if ($places->isEmpty()) {
            return back()->with('error', 'Marque pelo menos uma empresa na lista.');
        }

        if ($stop = $this->paidGate($request, $org->id, $places->count(), route('commerce.places.collect-selected'), ['ids' => $places->implode(',')])) {
            return $stop;
        }
        $allowPaid = $request->boolean('aceito_pago');
        foreach ($places as $id) {
            CollectCommercePlaceJob::dispatch($id, $allowPaid);
        }

        return back()->with('success', "{$places->count()} empresa(s) na fila de coleta. Cada uma leva até 1 minuto (site e velocidade no celular). Atualize a página daqui a pouco.");
    }

    /** Gera o link do raio-x público (etapa 5). Precisa de nota: o raio-x é a nota explicada. */
    public function generateReport(CommercePlace $place): RedirectResponse
    {
        if ($place->score === null) {
            return back()->with('error', 'Colete os dados e a nota antes de gerar o raio-x.');
        }
        if (in_array($place->status, ['opted_out', 'discarded'], true)) {
            return back()->with('error', $place->status === 'opted_out' ? 'Essa empresa pediu para não receber mensagens.' : 'Essa empresa está descartada.');
        }
        $place->ensureReport();

        return back()->with('success', 'Raio-x pronto. Use "Ver raio-x" para conferir e "Copiar link" para mandar.');
    }

    public function collectPlace(Request $request, CommercePlace $place): RedirectResponse
    {
        if (!GooglePlaces::forOrganization($place->organization_id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }
        if ($stop = $this->paidGate($request, $place->organization_id, 1, route('commerce.places.collect', $place))) {
            return $stop;
        }
        CollectCommercePlaceJob::dispatch($place->id, $request->boolean('aceito_pago'));

        return back()->with('success', 'Coleta pedida. Em até 1 minuto a nota aparece; recarregue a página.');
    }

    /**
     * Trava de custo (GoogleUsage): coleta que passa da franquia grátis do mês NÃO vai pra fila
     * sem confirmação — volta mostrando quantas seriam pagas e o custo estimado. Só admin/dono
     * libera (aceito_pago). Dentro da franquia segue direto. null = pode seguir.
     */
    private function paidGate(Request $request, string $organizationId, int $n, string $url, array $hidden = []): ?RedirectResponse
    {
        $est = GoogleUsage::estimate($organizationId, 'coleta', $n);
        if ($est['paid'] === 0) {
            return null;
        }
        $isAdmin = in_array(app()->has('currentOrgRole') ? app('currentOrgRole') : null, ['owner', 'admin'], true);
        if ($request->boolean('aceito_pago')) {
            return $isAdmin ? null : back()->with('error', 'Só admin ou dono da organização libera uso pago do Google.');
        }

        return back()->with('google_paid', $est + ['n' => $n, 'url' => $url, 'hidden' => $hidden, 'can' => $isAdmin]);
    }

    /**
     * Nome, tipo, endereço e link do Maps de cada empresa: do que está guardado (google_data);
     * quem ainda não tem é consultado no Google UMA vez e passa a ficar guardado.
     *
     * @return array<string, array{name: ?string, address: ?string, type: ?string, maps_url: ?string, closed: bool}>
     */
    private function googleNames(\Illuminate\Support\Collection $places, GooglePlaces $google): array
    {
        $out = [];
        $missing = [];
        foreach ($places as $p) {
            if ($d = $p->google_data) {
                $out[$p->place_id] = ['name' => $d['name'] ?? null, 'address' => $d['short_address'] ?? $d['address'] ?? null,
                    'type' => $d['type'] ?? null, 'maps_url' => $d['maps_url'] ?? null, 'closed' => (bool) ($d['closed'] ?? false)];
            } else {
                $missing[$p->place_id] = $p;
            }
        }
        foreach ($missing ? $google->details(array_keys($missing)) : [] as $placeId => $d) {
            $out[$placeId] = $d;
            $missing[$placeId]->update(['google_data' => ['name' => $d['name'], 'short_address' => $d['address'], 'type' => $d['type'],
                'maps_url' => $d['maps_url'], 'closed' => $d['closed']], 'google_data_at' => now()]);
        }

        return $out;
    }

    /** Página da empresa: nota, o que pesou, posição na cidade, sinais e conferência de anúncio. Dados do Google guardados. */
    public function showPlace(CommercePlace $place): View
    {
        $place->load('city', 'segment', 'adsChecker:id,name', 'scans.city', 'scans.segment');
        $google = GooglePlaces::forOrganization($place->organization_id);
        $liveError = null;
        // Já coletada: lê o que está guardado. Senão, consulta o Google uma vez e guarda.
        $g = isset($place->google_data['reviews']) ? $place->google_data : null;
        if (!$g && $google->configured()) {
            try {
                if ($g = $google->collect($place->place_id)) {
                    $place->update(['google_data' => $g, 'google_data_at' => now()]);
                }
            } catch (\Throwable $e) {
                $liveError = $e->getMessage();
            }
        }

        $peers = CommercePlace::where('segment_id', $place->segment_id)->where('city_id', $place->city_id)
            ->whereNotNull('score')->where('status', '!=', 'discarded')->orderByDesc('score')->get(['id', 'place_id', 'score', 'score_parts']);
        $rank = $place->score !== null ? $peers->search(fn ($p) => $p->id === $place->id) : false;
        $top = $peers->take(3);
        $topLive = $this->googleNames(CommercePlace::whereIn('id', $top->pluck('id'))->get(), $google);
        $signal = OpportunitySignal::for($place, $this->advertisers()->has($place->segment_id . '|' . $place->city_id));
        $reasons = PlaceScorer::reasons($g, $place->site_signals ?? []);

        return view('commerce.place', compact('place', 'g', 'liveError', 'peers', 'rank', 'top', 'topLive', 'signal', 'reasons'));
    }

    public function markAds(Request $request, CommercePlace $place): RedirectResponse
    {
        $v = $request->validate([
            'platform' => ['required', 'in:meta,google'],
            'answer'   => ['required', 'in:yes,no,unknown'],
        ]);
        $place->update([$v['platform'] === 'meta' ? 'ads_meta' : 'ads_google' => $v['answer'], 'ads_checked_by' => $request->user()->id, 'ads_checked_at' => now()]);

        return back();
    }

    /** "segmento|cidade" de quem anuncia (conferido), para o sinal "Não anuncia, concorrente sim". */
    private function advertisers(): \Illuminate\Support\Collection
    {
        return CommercePlace::where(fn ($q) => $q->where('ads_meta', 'yes')->orWhere('ads_google', 'yes'))
            ->get(['segment_id', 'city_id'])->mapWithKeys(fn ($p) => [$p->segment_id . '|' . $p->city_id => true]);
    }

    // Excluir varredura (só admin/dono): apaga ela e as empresas que só vieram por ela.
    public function destroyScan(CommerceScan $scan): RedirectResponse
    {
        if (in_array($scan->status, ['queued', 'running'], true)) {
            return back()->with('error', 'Essa varredura ainda está rodando. Espere terminar para excluir.');
        }
        $label = $scan->label();
        ['deleted' => $deleted, 'kept' => $kept] = $scan->deleteWithPlaces();

        return redirect()->route('commerce.prospecting', ['aba' => 'varreduras'])->with('success',
            "Varredura \"{$label}\" excluída, com {$deleted} empresa(s)."
            . ($kept ? " {$kept} ficaram, porque também estão em outra varredura ou já tiveram conversa." : ''));
    }

    public function discard(CommercePlace $place): RedirectResponse
    {
        $place->update(['status' => $place->status === 'discarded' ? 'mapped' : 'discarded']);

        return back()->with('success', $place->status === 'discarded' ? 'Empresa descartada: some da lista e não recebe raio-x.' : 'Empresa voltou para a lista.');
    }

    // ── Configuração (só admin/dono) ──

    /** WhatsApp da agência no botão "Quero melhorar meu raio-x" (settings.prospecting.whatsapp). */
    public function saveReportContact(Request $request): RedirectResponse
    {
        $data = $request->validate(['whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[\d\s()+-]*$/']], [], ['whatsapp' => 'WhatsApp']);
        $org = app('currentOrganization');
        $settings = $org->settings ?? [];
        $settings['prospecting']['whatsapp'] = filled($data['whatsapp'] ?? null) ? trim($data['whatsapp']) : null;
        $org->update(['settings' => $settings]);

        return redirect()->route('commerce.prospecting', ['aba' => 'configurar'])->with('success',
            $settings['prospecting']['whatsapp'] ? 'WhatsApp do raio-x salvo. O botão "Quero melhorar meu raio-x" já aparece na página.' : 'WhatsApp do raio-x removido. A página volta a pedir para responder a mensagem.');
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        CommerceSegment::create($this->segmentData($request) + ['organization_id' => app('currentOrganization')->id, 'sort_order' => (int) CommerceSegment::max('sort_order') + 1]);

        return redirect()->route('commerce.prospecting', ['aba' => 'configurar'])->with('success', 'Segmento criado.');
    }

    public function updateSegment(Request $request, CommerceSegment $segment): RedirectResponse
    {
        $segment->update($this->segmentData($request, $segment));

        return redirect()->route('commerce.prospecting', ['aba' => 'configurar'])->with('success', 'Segmento salvo.');
    }

    private function segmentData(Request $request, ?CommerceSegment $segment = null): array
    {
        $org = app('currentOrganization');
        $v = $request->validate([
            'name'         => ['required', 'string', 'max:120', Rule::unique('pgsql.commerce_segments', 'name')->where('organization_id', $org->id)->ignore($segment?->id)],
            'google_type'  => ['nullable', 'regex:/^[a-z_]{3,60}$/'],
            'search_terms' => ['required', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
        ], ['google_type.regex' => 'O tipo do Google é em inglês, minúsculas e sublinhado (ex.: dentist, beauty_salon).'], ['search_terms' => 'termos de busca']);

        $terms = collect(preg_split('/[\r\n,;]+/', $v['search_terms']))->map(fn ($t) => trim($t))->filter()->unique()->take(8)->values()->all();
        if (!$terms) {
            throw \Illuminate\Validation\ValidationException::withMessages(['search_terms' => 'Escreva ao menos um termo de busca.']);
        }

        return ['name' => trim($v['name']), 'google_type' => $v['google_type'] ?: null, 'search_terms' => $terms, 'is_active' => $request->boolean('is_active', true)];
    }

    public function storeCity(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');
        $v = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2'],
        ], [], ['name' => 'cidade', 'state' => 'UF']);
        $state = strtoupper($v['state']);
        if (!isset(CommerceCity::UFS[$state])) {
            return back()->withErrors(['city' => 'UF inválida.'])->withInput();
        }
        if (CommerceCity::where('name', trim($v['name']))->where('state', $state)->exists()) {
            return back()->withErrors(['city' => 'Essa cidade já está na lista.'])->withInput();
        }
        $city = new CommerceCity(['organization_id' => $org->id, 'name' => trim($v['name']), 'state' => $state]);

        // Localiza já no cadastro: cidade com nome/UF errados nem entra na lista.
        // Sem chave do Google, entra e é localizada na primeira varredura.
        $google = GooglePlaces::forOrganization($org->id);
        if ($google->configured()) {
            try {
                $city->locate($google);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['city' => $e->getMessage()])->withInput();
            }
        } else {
            $city->save();
        }

        return redirect()->route('commerce.prospecting', ['aba' => 'configurar'])
            ->with('success', $city->located() ? "Cidade incluída: {$city->google_label}." : 'Cidade incluída.');
    }

    public function destroyCity(CommerceCity $city): RedirectResponse
    {
        if (CommerceScan::where('city_id', $city->id)->exists()) {
            return back()->with('error', 'Essa cidade já tem varreduras e não pode sair da lista.');
        }
        $city->delete();

        return redirect()->route('commerce.prospecting', ['aba' => 'configurar'])->with('success', 'Cidade removida.');
    }
}

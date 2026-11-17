<?php

namespace App\Http\Controllers;

use App\Jobs\CollectCommercePlaceJob;
use App\Jobs\RunCommerceScanJob;
use App\Models\CommerceCity;
use App\Models\CommercePlace;
use App\Models\CommerceScan;
use App\Models\CommerceSegment;
use App\Services\Commerce\GooglePlaces;
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
        $data['scans'] = CommerceScan::with('city', 'segment', 'creator:id,name')->latest()->limit(50)->get();
        $data['running'] = $data['scans']->whereIn('status', ['queued', 'running'])->isNotEmpty();
        // Coleta por varredura: quantas empresas já foram coletadas.
        $data['progress'] = \Illuminate\Support\Facades\DB::connection('pgsql')->table('commerce_scan_place as sp')
            ->join('commerce_places as p', 'p.id', '=', 'sp.place_id')->whereIn('sp.scan_id', $data['scans']->pluck('id'))
            ->selectRaw('sp.scan_id, count(*) as total, count(p.collected_at) as collected')->groupBy('sp.scan_id')->get()->keyBy('scan_id');

        if ($tab === 'empresas') {
            $filters = $request->only('cidade', 'segmento', 'varredura', 'situacao', 'sinal');
            $query = CommercePlace::with('city', 'segment')
                ->when($filters['cidade'] ?? null, fn ($q, $v) => $q->where('city_id', $v))
                ->when($filters['segmento'] ?? null, fn ($q, $v) => $q->where('segment_id', $v))
                ->when($filters['varredura'] ?? null, fn ($q, $v) => $q->whereHas('scans', fn ($s) => $s->whereKey($v)))
                ->when(isset(CommercePlace::$statuses[$filters['situacao'] ?? '']), fn ($q) => $q->where('status', $filters['situacao']), fn ($q) => $q->where('status', '!=', 'discarded'));

            $counts = (clone $query)->toBase()->reorder()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
            $query->orderByRaw('score is null')->orderBy('score')->orderByDesc('created_at');
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
            // Ao vivo, só os da página (termos do Google: não guardar). Custa por empresa mostrada.
            $live = $google->details($places->pluck('place_id')->all());

            $data += compact('filters', 'places', 'live', 'counts', 'signals');
        }

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
    public function collectScan(Request $request, CommerceScan $scan): RedirectResponse
    {
        if (!GooglePlaces::forOrganization($scan->organization_id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }
        $places = $scan->places()->whereNotIn('status', ['discarded', 'opted_out'])
            ->when(!$request->boolean('todas'), fn ($q) => $q->where(fn ($w) => $w->whereNull('collected_at')->orWhere('collected_at', '<', now()->subDays(7))))
            ->pluck('commerce_places.id');
        foreach ($places as $id) {
            CollectCommercePlaceJob::dispatch($id);
        }

        return redirect()->route('commerce.prospecting', ['aba' => 'varreduras'])->with('success', $places->count()
            ? "{$places->count()} empresa(s) na fila de coleta. Cada uma leva até 1 minuto (site e velocidade no celular)."
            : 'Todas as empresas dessa varredura já foram coletadas nos últimos 7 dias.');
    }

    public function collectPlace(CommercePlace $place): RedirectResponse
    {
        if (!GooglePlaces::forOrganization($place->organization_id)->configured()) {
            return back()->with('error', 'Falta a chave do Google Maps (Places) em Configurações → Integrações.');
        }
        CollectCommercePlaceJob::dispatch($place->id);

        return back()->with('success', 'Coleta pedida. Em até 1 minuto a nota aparece; recarregue a página.');
    }

    /** Página da empresa: nota, o que pesou, posição na cidade, sinais e conferência de anúncio. Dados do Google ao vivo. */
    public function showPlace(CommercePlace $place): View
    {
        $place->load('city', 'segment', 'adsChecker:id,name', 'scans.city', 'scans.segment');
        $google = GooglePlaces::forOrganization($place->organization_id);
        $g = null;
        $liveError = null;
        if ($google->configured()) {
            try {
                $g = $google->collect($place->place_id); // ao vivo, não guardar
            } catch (\Throwable $e) {
                $liveError = $e->getMessage();
            }
        }

        $peers = CommercePlace::where('segment_id', $place->segment_id)->where('city_id', $place->city_id)
            ->whereNotNull('score')->where('status', '!=', 'discarded')->orderByDesc('score')->get(['id', 'place_id', 'score', 'score_parts']);
        $rank = $place->score !== null ? $peers->search(fn ($p) => $p->id === $place->id) : false;
        $top = $peers->take(3);
        $topLive = $google->details($top->pluck('place_id')->all());
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

    public function discard(CommercePlace $place): RedirectResponse
    {
        $place->update(['status' => $place->status === 'discarded' ? 'mapped' : 'discarded']);

        return back()->with('success', $place->status === 'discarded' ? 'Empresa descartada: some da lista e não recebe raio-x.' : 'Empresa voltou para a lista.');
    }

    // ── Configuração (só admin/dono) ──

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
        if (CommerceCity::where('name', trim($v['name']))->where('state', $state)->exists()) {
            return back()->withErrors(['name' => 'Essa cidade já está na lista.'])->withInput();
        }
        CommerceCity::create(['organization_id' => $org->id, 'name' => trim($v['name']), 'state' => $state]);

        return redirect()->route('commerce.prospecting', ['aba' => 'configurar'])->with('success', 'Cidade incluída.');
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

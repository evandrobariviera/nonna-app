<?php

namespace App\Http\Controllers;

use App\Jobs\RunCommerceScanJob;
use App\Models\CommerceCity;
use App\Models\CommercePlace;
use App\Models\CommerceScan;
use App\Models\CommerceSegment;
use App\Services\Commerce\GooglePlaces;
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

        if ($tab === 'empresas') {
            $filters = $request->only('cidade', 'segmento', 'varredura', 'situacao');
            $query = CommercePlace::with('city', 'segment')
                ->when($filters['cidade'] ?? null, fn ($q, $v) => $q->where('city_id', $v))
                ->when($filters['segmento'] ?? null, fn ($q, $v) => $q->where('segment_id', $v))
                ->when($filters['varredura'] ?? null, fn ($q, $v) => $q->whereHas('scans', fn ($s) => $s->whereKey($v)))
                ->when(isset(CommercePlace::$statuses[$filters['situacao'] ?? '']), fn ($q) => $q->where('status', $filters['situacao']), fn ($q) => $q->where('status', '!=', 'discarded'));

            $counts = (clone $query)->toBase()->reorder()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
            $places = $query->orderByRaw('score is null')->orderBy('score')->orderByDesc('created_at')->paginate(self::PER_PAGE)->withQueryString();
            // Ao vivo, só os da página (termos do Google: não guardar). Custa por empresa mostrada.
            $live = $google->details($places->pluck('place_id')->all());

            $data += compact('filters', 'places', 'live', 'counts');
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

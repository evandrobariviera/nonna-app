<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientOnboarding;
use App\Services\ClientOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientOnboardingController extends Controller
{
    // Lista de clientes em onboarding — "Gestão de Clientes > Entrada (Onboarding)".
    public function index(Request $request)
    {
        $emAndamento = ClientOnboarding::with('client', 'responsible')
            ->whereNull('completed_at')
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->get()
            ->sortBy(fn ($o) => $o->client?->displayName())
            ->values();

        $concluidos = ClientOnboarding::with('client', 'responsible')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(15)
            ->get();

        return view('onboarding.index', compact('emAndamento', 'concluidos'));
    }

    // Cria a esteira de onboarding pra um cliente que não tem (ex: fechado
    // antes dessa feature existir, ou cadastro feito por fora).
    public function store(Client $client, ClientOnboardingService $service)
    {
        $service->createRecord($client, Auth::id());

        return back()->with('success', 'Onboarding iniciado.');
    }

    // Liga/desliga um item de checklist (passos humanos das fases 2-5, ou
    // correção manual dos passos automáticos).
    public function toggle(Request $request, Client $client)
    {
        $data = $request->validate([
            'key'  => 'required|string|in:' . implode(',', array_keys(ClientOnboarding::$checklistLabels)),
            'done' => 'required|boolean',
        ]);

        $onboarding = $client->onboarding;
        abort_unless($onboarding, 404);

        $onboarding->setChecklistItem($data['key'], $data['done']);

        $this->syncPhaseCompletion($onboarding);

        return back();
    }

    // Marca fase_completed_at / avança current_phase conforme os checklists
    // ficam 100% — mantém o progresso coerente sem uma tela de gestão de fase.
    private function syncPhaseCompletion(ClientOnboarding $onboarding): void
    {
        $phases = $onboarding->phaseKeys();
        $current = 'concluido';

        foreach ($phases as $i => $phase) {
            $checklist = $onboarding->{$phase . '_checklist'} ?? [];
            $allDone = $checklist !== [] && !in_array(false, $checklist, true);

            $onboarding->{$phase . '_completed_at'} = $allDone
                ? ($onboarding->{$phase . '_completed_at'} ?? now())
                : null;

            if (!$allDone && $current === 'concluido') {
                $current = $phase;
            }
        }

        $onboarding->current_phase = $current;
        $onboarding->completed_at = $current === 'concluido' ? ($onboarding->completed_at ?? now()) : null;
        $onboarding->save();
    }
}

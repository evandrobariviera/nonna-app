<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Services\AdSync\CampaignControls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Central de Campanhas, etapa 4: pausar, reativar e ajustar verba diária pela
 * página da campanha. A lógica (teto do mês, diário, API) fica em CampaignControls.
 */
class CampaignControlController extends Controller
{
    public function __construct(private CampaignControls $controls) {}

    public function state(AdCampaign $campaign): JsonResponse
    {
        return response()->json($this->controls->state($campaign));
    }

    public function status(Request $request, AdCampaign $campaign): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'active' => 'required|boolean',
            'reason' => 'required|string|min:5|max:500',
        ], ['reason.*' => 'Escreva o porquê (pelo menos algumas palavras).']);
        if ($v->fails()) {
            return response()->json(['message' => $v->errors()->first()], 422);
        }

        try {
            $error = $this->controls->setStatus($campaign, $request->boolean('active'), $request->input('reason'), $request->user()->id);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return $error
            ? response()->json(['message' => 'A plataforma não aceitou: ' . $error], 422)
            : response()->json(['message' => $request->boolean('active') ? 'Campanha reativada.' : 'Campanha pausada.', 'state' => $this->controls->state($campaign)]);
    }

    public function budget(Request $request, AdCampaign $campaign): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'level'  => 'required|in:campaign,adset',
            'target' => 'required|string|max:200',
            'daily'  => 'required|numeric|min:1|max:1000000',
            'reason' => 'required|string|min:5|max:500',
        ], ['reason.*' => 'Escreva o porquê (pelo menos algumas palavras).', 'daily.*' => 'Informe a nova verba diária em reais.']);
        if ($v->fails()) {
            return response()->json(['message' => $v->errors()->first()], 422);
        }

        try {
            $error = $this->controls->setDailyBudget($campaign, $request->input('level'), $request->input('target'), (float) $request->input('daily'), $request->input('reason'), $request->user()->id);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return $error
            ? response()->json(['message' => $error], 422)
            : response()->json(['message' => 'Verba atualizada.', 'state' => $this->controls->state($campaign)]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Projects\ProjectMapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mapa de Projetos — visão do gestor de projetos (seção Visões, abaixo do Painel de
 * Produção). Toda a lógica mora em ProjectMapService.
 */
class ProjectMapController extends Controller
{
    public function index(Request $request, ProjectMapService $service): View
    {
        $incluirInativos = $request->boolean('inativos');

        return view('mapa-projetos.index', $service->build($incluirInativos) + [
            'incluirInativos' => $incluirInativos,
            'limiteTarefa' => ProjectMapService::TAREFA_ESTAGNADA_DIAS_UTEIS,
            'limiteProjeto' => ProjectMapService::PROJETO_ESTAGNADO_DIAS,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Projects\AgencyTimeline;
use Illuminate\Http\Request;

// Cronograma da agência — controle visual de todos os planejamentos e suas campanhas/projetos
// (Services\Projects\AgencyTimeline).
class CronogramaController extends Controller
{
    public function index(Request $request, AgencyTimeline $timeline)
    {
        $window = array_key_exists($request->get('janela'), AgencyTimeline::WINDOWS) ? $request->get('janela') : 'trimestre';

        return view('cronograma.index', ['data' => $timeline->build($window)]);
    }
}

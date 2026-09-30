<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Services\Production\ProductionLoadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Carga de Produção (seção Visões) — quanto cada cliente consumiu da agência, por
 * período, executor, responsável e tipo. Lógica em ProductionLoadService.
 */
class ProductionLoadController extends Controller
{
    public function index(Request $request, ProductionLoadService $service): View
    {
        $service->fromRequest($request);

        return view('carga-producao.index', $service->build() + [
            'f' => $service->filtros,
            'inicio' => $service->inicio,
            'fim' => $service->fim,
            'historicoDesde' => ProductionLoadService::HISTORICO_DESDE,
            // Cliente filtrado inteiro — o form de limites precisa do creative_lead_id atual
            // (clients.update-production grava os dois campos juntos).
            'clienteSel' => $service->filtros['cliente'] ? Client::find($service->filtros['cliente']) : null,
            'opcoesClientes' => Client::query()
                ->when(! $service->filtros['inativos'], fn ($q) => $q->where('status', '!=', 'inactive'))
                ->orderBy('company_name')->get(['id', 'nickname', 'company_name']),
            'opcoesPessoas' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Planilha (CSV) com as tarefas por trás dos números — mesmos filtros da tela. */
    public function export(Request $request, ProductionLoadService $service): StreamedResponse
    {
        $service->fromRequest($request);
        $lista = $request->get('lista') === 'abertas' ? 'abertas' : 'executadas';
        $tarefas = $lista === 'abertas' ? $service->emExecucao() : $service->executadas();

        $nome = sprintf('carga-producao-%s-%s-a-%s.csv', $lista, $service->inicio->format('Y-m-d'), $service->fim->format('Y-m-d'));

        return response()->streamDownload(function () use ($tarefas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos certo
            fputcsv($out, ['Cliente', 'Tarefa', 'Tipo', 'Status', 'Executor(es)', 'Responsável(is)', 'Entrega combinada', 'Concluída em', 'Criada em', 'Link'], ';');
            foreach ($tarefas as $t) {
                fputcsv($out, [
                    $t->client?->displayName() ?? 'Interno (sem cliente)',
                    $t->title,
                    Task::$types[$t->task_type] ?? ($t->task_type ?: 'Sem tipo'),
                    Task::$statuses[$t->status]['label'] ?? $t->status,
                    ProductionLoadService::executoresDe($t)->pluck('name')->implode(', '),
                    ProductionLoadService::responsaveisDe($t)->pluck('name')->implode(', '),
                    $t->due_date?->format('d/m/Y'),
                    $t->concluida_em ? \Carbon\Carbon::parse($t->concluida_em)->format('d/m/Y H:i') : '',
                    $t->created_at?->format('d/m/Y'),
                    route('tasks.show', $t->id),
                ], ';');
            }
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

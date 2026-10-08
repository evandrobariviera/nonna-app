<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Services\ProjectApprovalPageService;
use Illuminate\View\View;

/**
 * "Ver como o cliente vê": a Central de Aprovações do Portal (e as páginas de
 * projeto/avulsas) exatamente como o cliente vê, só pra consulta — pra equipe
 * conferir descrição/destaque antes de mandar e explicar numa ligação.
 *
 * Renderiza as MESMAS views do Portal; a binding 'portal.preview' faz o
 * portal-layout trocar a lateral do Portal por uma faixa de pré-visualização,
 * e os links apontam pra cá (projetos) ou pra tarefa interna (peças).
 */
class ApprovalPreviewController extends Controller
{
    public function __construct(private ProjectApprovalPageService $pages) {}

    public function central(Client $client): View
    {
        $this->enterPreview($client);

        $groups = $this->pages->central($client)->map(function ($g) use ($client) {
            $g['url'] = $g['project']
                ? route('approvals.preview.project', $g['project'])
                : route('approvals.preview.loose', $client);

            return $g;
        });

        return view('portal.approvals.index', [
            'client'            => $client,
            'openGroups'        => $groups->reject(fn ($g) => $g['finished'])->values(),
            'doneGroups'        => $groups->filter(fn ($g) => $g['finished'])->values(),
            'totalPending'      => $groups->sum(fn ($g) => $g['summary']['pending']),
            'groupsWithPending' => $groups->filter(fn ($g) => $g['summary']['pending'] > 0)->count(),
            'myPending'         => 0,
        ]);
    }

    public function project(Project $project): View
    {
        $client = Client::findOrFail($project->resolvedClientId());
        $this->enterPreview($client);

        $items = $this->withTaskUrls($this->pages->items($project));
        abort_if($items->isEmpty(), 404, 'Nenhuma peça deste projeto passou por aprovação ainda — o cliente não vê esta página.');

        return view('portal.approvals.project', [
            'client'      => $client,
            'pageTitle'   => $project->title,
            'pageType'    => $project->typeLabel(),
            'description' => $project->client_description,
            'highlight'   => $items->firstWhere('is_highlight', true),
            'pieces'      => $items->reject(fn ($i) => $i['is_highlight'])->values(),
            'summary'     => $this->pages->summary($items),
            'myPending'   => 0,
            'scopeArgs'   => ['projeto', $project],
            'centralUrl'  => route('approvals.preview.central', $client),
        ]);
    }

    public function loose(Client $client): View
    {
        $this->enterPreview($client);

        $items = $this->withTaskUrls($this->pages->looseItems($client));
        abort_if($items->isEmpty(), 404);

        return view('portal.approvals.project', [
            'client'      => $client,
            'pageTitle'   => 'Peças avulsas',
            'pageType'    => 'Avulsas',
            'description' => 'Materiais que não fazem parte de um projeto.',
            'highlight'   => null,
            'pieces'      => $items,
            'summary'     => $this->pages->summary($items),
            'myPending'   => 0,
            'scopeArgs'   => ['avulsas'],
            'centralUrl'  => route('approvals.preview.central', $client),
        ]);
    }

    private function enterPreview(Client $client): void
    {
        app()->instance('portal.preview', ['client' => $client]);
    }

    // Na pré-visualização, a peça abre a tarefa interna (é a equipe olhando).
    private function withTaskUrls($items)
    {
        return $items->map(function ($i) {
            $i['url'] = route('tasks.show', $i['task']);

            return $i;
        });
    }
}

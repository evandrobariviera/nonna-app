<?php

namespace App\Http\Controllers;

use App\Models\AdAccountConnection;
use App\Models\ClientAdAccount;
use App\Models\GoogleAssetCreation;
use App\Models\Task;
use App\Services\AdSync\GooglePmaxAssetCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Criativos → Google: arte aprovada vira imagem num grupo de recursos da
 * Performance Max, pausada. A lógica fica em GooglePmaxAssetCreator.
 */
class GooglePmaxAssetController extends Controller
{
    public function __construct(private GooglePmaxAssetCreator $creator) {}

    public function create(Task $task): View
    {
        abort_unless($task->client_id, 404);

        $accounts = ClientAdAccount::where('client_id', $task->client_id)->where('platform', 'google_ads')->get();
        $images = MetaAdCreationController::approvedImages($task)->map(fn ($img) => ['image' => $img, 'info' => GooglePmaxAssetCreator::inspect($img)]);

        return view('ad-creations.google', [
            'task'        => $task->load(['client', 'latestApprovalRound']),
            'accounts'    => $accounts,
            'connections' => AdAccountConnection::whereIn('client_ad_account_id', $accounts->pluck('id'))->get()->keyBy('client_ad_account_id'),
            'images'      => $images,
            'creations'   => GoogleAssetCreation::with(['createdBy:id,name', 'attachment'])->where('task_id', $task->id)->latest()->get(),
        ]);
    }

    public function groups(ClientAdAccount $adAccount): JsonResponse
    {
        abort_unless($adAccount->platform === 'google_ads', 404);

        try {
            return response()->json(['groups' => $this->creator->assetGroups($adAccount)]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request, Task $task): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'client_ad_account_id' => 'required|uuid',
            'asset_group_id'       => 'required|string|max:30',
            'images'               => 'nullable|array|max:20',
            'images.*'             => 'uuid',
            'youtube_url'          => 'nullable|string|max:300',
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $youtubeId = GooglePmaxAssetCreator::youtubeId($request->input('youtube_url'));
        if ($request->filled('youtube_url') && !$youtubeId) {
            return back()->withErrors(['google' => 'Link do YouTube não reconhecido. Cole o link do vídeo (youtube.com/watch?v=… ou youtu.be/…).'])->withInput();
        }

        $account = ClientAdAccount::where('client_id', $task->client_id)->where('platform', 'google_ads')->find($request->input('client_ad_account_id'));
        $images = MetaAdCreationController::approvedImages($task)->whereIn('id', $request->input('images', []))->values();
        if (!$account || ($images->isEmpty() && !$youtubeId)) {
            return back()->withErrors(['google' => 'Escolha pelo menos uma imagem ou cole o link do vídeo no YouTube.'])->withInput();
        }

        $done = [];
        try {
            $group = collect($this->creator->assetGroups($account))->firstWhere('id', $request->input('asset_group_id'))
                ?? throw new \RuntimeException('Grupo de recursos não encontrado nesta conta.');
            if ($group['total'] + $images->count() > GooglePmaxAssetCreator::MAX_IMAGES_PER_GROUP) {
                return back()->withErrors(['google' => "O grupo já tem {$group['total']} imagens; o Google aceita até 20. Pause ou remova alguma no Google Ads antes."])->withInput();
            }
            if ($youtubeId && $group['videos'] >= GooglePmaxAssetCreator::MAX_VIDEOS_PER_GROUP) {
                return back()->withErrors(['google' => "O grupo já tem {$group['videos']} vídeos; o Google aceita até " . GooglePmaxAssetCreator::MAX_VIDEOS_PER_GROUP . '.'])->withInput();
            }

            if ($images->isNotEmpty()) {
                $result = $this->creator->create($task, $account, $group, $images, Auth::id());
                if (!$result['ok']) {
                    return back()->withErrors(['google' => 'O Google recusou as imagens: ' . $result['error']])->withInput();
                }
                $done[] = count($result['creations']) . ' imagem(ns)';
            }
            if ($youtubeId) {
                $video = $this->creator->addYoutube($task, $account, $group, $youtubeId, Auth::id());
                if (!$video['ok']) {
                    return back()->withErrors(['google' => ($done ? implode(' e ', $done) . ' enviada(s), mas o Google recusou o vídeo: ' : 'O Google recusou o vídeo: ') . $video['error']])->withInput();
                }
                $done[] = 'o vídeo do YouTube';
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['google' => 'Não foi possível enviar: ' . $e->getMessage()])->withInput();
        }

        return redirect()->route('google-pmax.create', $task)
            ->with('success', ucfirst(implode(' e ', $done)) . ' enviado(s) PAUSADO(S) ao grupo "' . $group['name'] . '". Clique em "Ativar" quando quiser que entrem no ar.');
    }

    public function activate(GoogleAssetCreation $creation): RedirectResponse
    {
        abort_unless($creation->status === 'pausado' && $creation->link_resource, 422);

        if ($error = $this->creator->activate($creation, Auth::id())) {
            return back()->withErrors(['google' => 'O Google não ativou: ' . $error]);
        }

        return back()->with('success', 'Imagem ativada. Ela entra na combinação da Performance Max depois da revisão do Google.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// Comentários da campanha/projeto — mesmo jeito dos comentários da tarefa
// (TaskCommentController): editor rico, só quem escreveu edita/remove. Comentário novo
// avisa quem está na conversa (ver notifyTeam), menos quem escreveu.
class ProjectCommentController extends Controller
{
    public function store(Request $request, Project $project, NotificationService $notifications)
    {
        $body = $this->cleanBody($request);
        if ($body === null) {
            return $this->back($project)->withErrors(['comment_body' => 'O comentário não pode ficar vazio.']);
        }

        $project->comments()->create([
            'organization_id' => $project->organization_id,
            'user_id'         => Auth::id(),
            'body'            => $body,
            'visible_to_client' => $request->boolean('visible_to_client'),
        ]);

        $this->notifyTeam($project, $body, $notifications);

        return $this->back($project)->with('success', 'Comentário adicionado.');
    }

    public function update(Request $request, Project $project, ProjectComment $comment)
    {
        abort_unless($comment->project_id === $project->id && $comment->user_id === Auth::id(), 403);

        $body = $this->cleanBody($request);
        if ($body === null) {
            return $this->back($project)->withErrors(['comment_body' => 'O comentário não pode ficar vazio.']);
        }

        $comment->update(['body' => $body]);

        return $this->back($project)->with('success', 'Comentário atualizado.');
    }

    public function destroy(Project $project, ProjectComment $comment)
    {
        abort_unless($comment->project_id === $project->id && $comment->user_id === Auth::id(), 403);

        $comment->delete();

        return $this->back($project);
    }

    private function cleanBody(Request $request): ?string
    {
        $data = $request->validate(['comment_body' => 'required|string|max:5000000']);
        $body = RichTextSanitizer::clean($data['comment_body']);

        // Editor vazio manda "<p></p>" — só vale se sobrou texto ou imagem.
        return trim(strip_tags($body)) === '' && !str_contains($body, '<img') ? null : $body;
    }

    /**
     * Atenção eficiente (2026-11-06): antes avisava TODOS os membros a cada comentário — 30% de
     * todo o sino, e quem só executa nem abria. Agora avisa quem está na conversa:
     *  - o responsável criativo do cliente;
     *  - quem já comentou neste projeto/campanha;
     *  - membro citado no texto (@Nome ou o nome completo).
     * Os demais membros veem na aba de comentários. Repetições viram um aviso só (NotificationService).
     */
    private function notifyTeam(Project $project, string $body, NotificationService $notifications): void
    {
        $leadId = $project->client?->creative_lead_id ?? $project->macroPlan?->client?->creative_lead_id;
        // reorder(): comments() vem com ->latest(), e no Postgres DISTINCT + ORDER BY por coluna
        // fora do SELECT dá erro (derrubava todo comentário novo com 500).
        $commenters = $project->comments()->reorder()->distinct()->pluck('user_id');

        $text = Str::lower(RichTextSanitizer::toPlainText($body));
        $mentioned = $project->members()->get(['users.id', 'users.name'])
            ->filter(function ($member) use ($text) {
                $name = Str::lower(trim($member->name));
                $first = Str::before($name, ' ');

                return $name !== '' && (str_contains($text, '@' . $first) || str_contains($text, $name));
            })
            ->pluck('id');

        $ids = $commenters->merge($mentioned)->push($leadId)->filter()->unique()->reject(fn ($id) => $id === Auth::id());
        if ($ids->isEmpty()) {
            return;
        }

        $notifications->notifyUsers(
            User::whereIn('id', $ids)->get(),
            'project_comment',
            Auth::user()->name . ' comentou em ' . $project->title,
            Str::limit(RichTextSanitizer::toPlainText($body), 180),
            $this->url($project) . '#comentarios',
            $project,
            $project->organization_id,
        );
    }

    private function url(Project $project): string
    {
        return $project->macro_plan_id
            ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
            : route('projects.showDirect', $project);
    }

    private function back(Project $project)
    {
        return redirect()->to($this->url($project) . '#comentarios');
    }
}

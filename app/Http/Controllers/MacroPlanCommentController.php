<?php

namespace App\Http\Controllers;

use App\Models\MacroPlan;
use App\Models\MacroPlanComment;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// Discussão do planejamento — mesmo jeito dos comentários da campanha/projeto
// (ProjectCommentController). Comentário novo avisa todo mundo envolvido no planejamento
// (MacroPlan::discussionRecipientIds), menos quem escreveu.
class MacroPlanCommentController extends Controller
{
    public function store(Request $request, MacroPlan $macroplan, NotificationService $notifications)
    {
        $body = $this->cleanBody($request);
        if ($body === null) {
            return $this->back($macroplan)->withErrors(['comment_body' => 'O comentário não pode ficar vazio.']);
        }

        $macroplan->comments()->create([
            'organization_id' => $macroplan->organization_id,
            'user_id'         => Auth::id(),
            'body'            => $body,
        ]);

        $this->notifyTeam($macroplan, $body, $notifications);

        return $this->back($macroplan)->with('success', 'Comentário adicionado.');
    }

    public function update(Request $request, MacroPlan $macroplan, MacroPlanComment $comment)
    {
        abort_unless($comment->macro_plan_id === $macroplan->id && $comment->user_id === Auth::id(), 403);

        $body = $this->cleanBody($request);
        if ($body === null) {
            return $this->back($macroplan)->withErrors(['comment_body' => 'O comentário não pode ficar vazio.']);
        }

        $comment->update(['body' => $body]);

        return $this->back($macroplan)->with('success', 'Comentário atualizado.');
    }

    public function destroy(MacroPlan $macroplan, MacroPlanComment $comment)
    {
        abort_unless($comment->macro_plan_id === $macroplan->id && $comment->user_id === Auth::id(), 403);

        $comment->delete();

        return $this->back($macroplan);
    }

    private function cleanBody(Request $request): ?string
    {
        $data = $request->validate(['comment_body' => 'required|string|max:5000000']);
        $body = RichTextSanitizer::clean($data['comment_body']);

        // Editor vazio manda "<p></p>" — só vale se sobrou texto ou imagem.
        return trim(strip_tags($body)) === '' && !str_contains($body, '<img') ? null : $body;
    }

    private function notifyTeam(MacroPlan $macroplan, string $body, NotificationService $notifications): void
    {
        $ids = $macroplan->discussionRecipientIds()->reject(fn ($id) => $id === Auth::id());
        if ($ids->isEmpty()) {
            return;
        }

        $notifications->notifyUsers(
            User::whereIn('id', $ids)->get(),
            'macro_plan_comment',
            Auth::user()->name . ' comentou no ' . $macroplan->standardTitle(),
            Str::limit(RichTextSanitizer::toPlainText($body), 180),
            $this->url($macroplan),
            $macroplan,
            $macroplan->organization_id,
        );
    }

    private function url(MacroPlan $macroplan): string
    {
        return route('macroplans.edit', [$macroplan, 'bloco' => 'discussao']);
    }

    private function back(MacroPlan $macroplan)
    {
        return redirect()->to($this->url($macroplan));
    }
}

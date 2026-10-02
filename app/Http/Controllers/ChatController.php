<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

// Chat interno (widget flutuante + tela /chat). Toda rota que toca uma conversa
// passa pela ChatConversationPolicy — só participante, sem exceção pra admin.
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    // Todas as rotas do chat são chamadas pelo widget via fetch. O $request->validate()
    // padrão aqui REDIRECIONA no erro (bootstrap/app.php usa shouldRenderJsonWhen só pra
    // api/*, o que desliga o expectsJson() do Laravel) — o widget receberia HTML no lugar
    // do erro. Por isso: erro de validação sempre volta como 422 JSON com a 1ª mensagem.
    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    // Tela cheia — mesmo componente do widget, em modo página.
    public function index(Request $request): View
    {
        return view('chat.index', ['openConversation' => $request->query('c')]);
    }

    public function status(Request $request): JsonResponse
    {
        $since = $request->query('since');

        return response()->json(
            $this->chat->status($request->user(), is_numeric($since) ? (int) $since : null)
        );
    }

    public function conversations(Request $request): JsonResponse
    {
        return response()->json(['conversations' => $this->chat->conversationsFor($request->user())]);
    }

    public function people(Request $request): JsonResponse
    {
        $people = $this->chat->teammates($request->user())->map(fn (User $u) => [
            'id'       => $u->id,
            'name'     => $u->name,
            'avatar'   => $u->avatarUrl(),
            'initials' => mb_strtoupper(mb_substr($u->name, 0, 2)),
        ]);

        return response()->json(['people' => $people]);
    }

    // Atalho "/" da caixa de mensagem: busca tarefa/projeto/campanha/cliente pra citar.
    public function references(Request $request): JsonResponse
    {
        $data = $this->validateJson($request, [
            'type' => ['required', 'in:tarefa,projeto,campanha,cliente'],
            'q'    => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['items' => $this->chat->searchReferences($data['type'], $data['q'] ?? '')]);
    }

    public function openDirect(Request $request): JsonResponse
    {
        $data = $this->validateJson($request, ['user_id' => ['required', 'integer']]);

        // Só colega da organização atual (e não login de cliente).
        $other = $this->chat->teammates($request->user())->firstWhere('id', $data['user_id']);
        abort_unless($other, 404);

        $conversation = $this->chat->findOrCreateDirect($request->user(), User::findOrFail($other->id));

        return response()->json(['conversation' => $this->chat->conversationIdentity($conversation, $request->user())]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $query = $conversation->messages()->with(['user:id,name', 'attachments']);

        if (is_numeric($request->query('after'))) {
            // Polling: só o que chegou depois do último id que o widget já tem.
            $messages = $query->where('id', '>', (int) $request->query('after'))->orderBy('id')->limit(100)->get();
        } else {
            // Abertura (ou "carregar anteriores" com before=): últimas 30, em ordem cronológica.
            $messages = $query
                ->when(is_numeric($request->query('before')), fn ($q) => $q->where('id', '<', (int) $request->query('before')))
                ->orderByDesc('id')->limit(30)->get()->reverse()->values();
        }

        $me = $conversation->participants()->where('user_id', $request->user()->id)->first();

        return response()->json([
            'conversation' => array_merge(
                $this->chat->conversationIdentity($conversation, $request->user()),
                ['muted' => (bool) $me?->muted]
            ),
            'messages' => $messages->map(fn ($m) => $this->chat->messagePayload($m, $request->user()))->values(),
            'has_more' => $messages->count() === 30,
        ]);
    }

    public function send(Request $request, ChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $data = $this->validateJson($request, [
            'body'    => ['nullable', 'string', 'max:5000'],
            'files'   => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:51200'], // 50 MB — acima disso, Drive
        ]);

        if (trim((string) ($data['body'] ?? '')) === '' && !$request->hasFile('files')) {
            return response()->json(['message' => 'Escreva uma mensagem ou anexe um arquivo.'], 422);
        }

        try {
            $message = $this->chat->send($conversation, $request->user(), $data['body'] ?? null, $request->file('files', []));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $this->chat->messagePayload($message, $request->user())], 201);
    }

    public function markRead(Request $request, ChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $data = $this->validateJson($request, ['message_id' => ['required', 'integer', 'min:0']]);
        $this->chat->markRead($conversation, $request->user(), $data['message_id']);

        return response()->json(['ok' => true]);
    }

    public function toggleMute(Request $request, ChatConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $participant = $conversation->participants()->where('user_id', $request->user()->id)->firstOrFail();
        $participant->update(['muted' => !$participant->muted]);

        return response()->json(['muted' => $participant->muted]);
    }

    // Anexo: confere participação e redireciona pro link temporário do R2 (1h).
    public function attachment(Request $request, ChatMessageAttachment $attachment)
    {
        $conversation = ChatConversation::findOrFail($attachment->message->conversation_id);
        Gate::authorize('view', $conversation);

        return redirect()->away($attachment->temporaryUrl($request->boolean('download')));
    }
}

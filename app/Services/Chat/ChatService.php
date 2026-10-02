<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatParticipant;
use App\Models\Sector;
use App\Models\User;
use App\Support\UploadOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ChatService
{
    // ── Canais de Setor ──────────────────────────────────────────────────────

    // Canal acompanha o Setor: mesmo nome, mesmos membros. Quem sai do Setor sai do
    // canal (perde o acesso ao histórico dele — o canal é do Setor, não da pessoa).
    public function syncSectorChannel(Sector $sector): ChatConversation
    {
        $conversation = ChatConversation::firstOrCreate(
            ['organization_id' => $sector->organization_id, 'type' => 'sector', 'sector_id' => $sector->id],
            ['name' => $sector->name]
        );

        if ($conversation->name !== $sector->name) {
            $conversation->update(['name' => $sector->name]);
        }

        $memberIds = $sector->users()->pluck('users.id')->all();

        $conversation->participants()->whereNotIn('user_id', $memberIds)->delete();

        $existing = $conversation->participants()->pluck('user_id')->all();
        foreach (array_diff($memberIds, $existing) as $userId) {
            // Entra "em dia": não herda como não lido todo o histórico do canal.
            ChatParticipant::create([
                'conversation_id'      => $conversation->id,
                'user_id'              => $userId,
                'last_read_message_id' => (int) $conversation->messages()->max('id'),
            ]);
        }

        return $conversation;
    }

    // ── Conversa individual ──────────────────────────────────────────────────

    public function findOrCreateDirect(User $me, User $other): ChatConversation
    {
        if ($me->id === $other->id) {
            throw new RuntimeException('Não dá pra abrir conversa com você mesmo.');
        }

        $orgId = app('currentOrganization')->id;
        $key   = ChatConversation::directKey($me->id, $other->id);

        return DB::connection('pgsql')->transaction(function () use ($orgId, $key, $me, $other) {
            $conversation = ChatConversation::firstOrCreate(
                ['organization_id' => $orgId, 'direct_key' => $key],
                ['type' => 'direct', 'created_by' => $me->id]
            );

            foreach ([$me->id, $other->id] as $userId) {
                ChatParticipant::firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId]);
            }

            return $conversation;
        });
    }

    // Colegas que podem receber mensagem: membros da organização atual, sem usuários
    // de cliente (client_id preenchido = login de cliente, não equipe).
    public function teammates(User $me): Collection
    {
        return app('currentOrganization')->users()
            ->whereNull('users.client_id')
            ->where('users.id', '!=', $me->id)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.avatar_path', 'users.avatar_disk']);
    }

    // ── Envio ────────────────────────────────────────────────────────────────

    /**
     * @param  UploadedFile[]  $files
     */
    public function send(ChatConversation $conversation, User $sender, ?string $body, array $files = []): ChatMessage
    {
        $body = trim((string) $body);
        $disk = config('filesystems.default', 'r2');

        // Sobe os arquivos ANTES de gravar a mensagem: se o R2 falhar no meio, nada
        // fica gravado pela metade (mensagem com anexo "fantasma").
        $stored = [];
        foreach ($files as $file) {
            $mimeType = $file->getMimeType();
            $path = $file->store("chat/{$conversation->id}", UploadOptions::forStore((string) $mimeType, $disk));

            if ($path === false) {
                throw new RuntimeException("Falha ao enviar \"{$file->getClientOriginalName()}\". Tente de novo.");
            }

            $stored[] = [
                'filename'  => Str::limit($file->getClientOriginalName(), 250, ''),
                'disk_path' => $path,
                'disk'      => $disk,
                'mime_type' => $mimeType,
                'size'      => $file->getSize(),
            ];
        }

        return DB::connection('pgsql')->transaction(function () use ($conversation, $sender, $body, $stored) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $sender->id,
                'body'            => $body !== '' ? $body : null,
            ]);

            foreach ($stored as $attachment) {
                $message->attachments()->create($attachment);
            }

            $conversation->update(['last_message_at' => $message->created_at]);

            // Quem envia já "leu" a própria mensagem (e tudo antes dela).
            ChatParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_message_id' => $message->id]);

            return $message->load(['attachments', 'user']);
        });
    }

    public function markRead(ChatConversation $conversation, User $user, int $messageId): void
    {
        $maxId = (int) $conversation->messages()->max('id');
        $messageId = min($messageId, $maxId);

        ChatParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('last_read_message_id', '<', $messageId)
            ->update(['last_read_message_id' => $messageId]);
    }

    // ── Leitura / payloads JSON pro widget ───────────────────────────────────

    // Lista de conversas do usuário, com não lidas e prévia da última mensagem.
    public function conversationsFor(User $user): array
    {
        $conversations = ChatConversation::forUser($user->id)
            ->with(['users:users.id,users.name,users.avatar_path,users.avatar_disk'])
            ->get();

        if ($conversations->isEmpty()) {
            return [];
        }

        $unread = $this->unreadByConversation($user);

        $lastIds = ChatMessage::whereIn('conversation_id', $conversations->pluck('id'))
            ->groupBy('conversation_id')
            ->selectRaw('max(id) as id')
            ->pluck('id');

        $lastMessages = ChatMessage::with('attachments:id,message_id,filename')
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('conversation_id');

        return $conversations
            ->map(function (ChatConversation $c) use ($user, $unread, $lastMessages) {
                $me   = $c->users->firstWhere('id', $user->id);
                $last = $lastMessages->get($c->id);

                return array_merge($this->conversationIdentity($c, $user), [
                    'muted'           => (bool) $me?->pivot->muted,
                    'unread'          => (int) ($unread[$c->id] ?? 0),
                    'last_message_at' => ($c->last_message_at ?? $c->created_at)?->toIso8601String(),
                    'last_preview'    => $last ? $this->preview($last, $user) : null,
                ]);
            })
            // Setores primeiro; dentro de cada grupo, conversa mais recente em cima.
            ->sort(function ($a, $b) {
                $rank = fn ($c) => $c['type'] === 'sector' ? 0 : 1;
                return [$rank($a), $b['last_message_at']] <=> [$rank($b), $a['last_message_at']];
            })
            ->values()
            ->all();
    }

    // Nome/avatar de como a conversa aparece PRA ESTE usuário (na individual, é o outro).
    public function conversationIdentity(ChatConversation $c, User $viewer): array
    {
        $c->loadMissing('users:users.id,users.name,users.avatar_path,users.avatar_disk');

        if ($c->type === 'direct') {
            $other = $c->users->firstWhere('id', '!=', $viewer->id);

            return [
                'id'       => $c->id,
                'type'     => 'direct',
                'name'     => $other?->name ?? 'Usuário removido',
                'avatar'   => $other?->avatarUrl(),
                'initials' => $this->initials($other?->name),
                'members'  => 2,
            ];
        }

        return [
            'id'       => $c->id,
            'type'     => $c->type,
            'name'     => $c->name ?? 'Conversa',
            'avatar'   => null,
            'initials' => $this->initials($c->name),
            'members'  => $c->users->count(),
        ];
    }

    /** @return array<string,int> conversation_id => não lidas */
    public function unreadByConversation(User $user, bool $excludeMuted = false): array
    {
        return DB::connection('pgsql')->table('chat_participants as p')
            ->join('chat_messages as m', function ($join) {
                $join->on('m.conversation_id', '=', 'p.conversation_id')
                    ->whereColumn('m.id', '>', 'p.last_read_message_id')
                    ->whereNull('m.deleted_at')
                    ->where(fn ($q) => $q->whereNull('m.user_id')->orWhereColumn('m.user_id', '!=', 'p.user_id'));
            })
            ->where('p.user_id', $user->id)
            ->when($excludeMuted, fn ($q) => $q->where('p.muted', false))
            ->groupBy('p.conversation_id')
            ->selectRaw('p.conversation_id, count(m.id) as total')
            ->pluck('total', 'conversation_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // Polling leve do widget: total de não lidas + mensagens novas (de outras pessoas,
    // em conversas não silenciadas) desde o último id visto — pra prévia/som.
    public function status(User $user, ?int $sinceId): array
    {
        $latestId = (int) ChatMessage::whereIn('conversation_id', ChatParticipant::where('user_id', $user->id)->select('conversation_id'))
            ->max('id');

        $new = [];
        if ($sinceId !== null && $latestId > $sinceId) {
            $new = ChatMessage::with(['user:id,name', 'attachments:id,message_id,filename', 'conversation'])
                ->whereIn('conversation_id', ChatParticipant::where('user_id', $user->id)->where('muted', false)->select('conversation_id'))
                ->where('id', '>', $sinceId)
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
                ->orderBy('id')
                ->limit(10)
                ->get()
                ->map(fn (ChatMessage $m) => [
                    'id'                => $m->id,
                    'conversation_id'   => $m->conversation_id,
                    'conversation_name' => $this->conversationIdentity($m->conversation, $user)['name'],
                    'conversation_type' => $m->conversation->type,
                    'sender'            => $m->user?->name ?? 'Usuário removido',
                    'preview'           => $this->preview($m, $user),
                ])
                ->all();
        }

        return [
            'unread_total' => array_sum($this->unreadByConversation($user, excludeMuted: true)),
            'latest_id'    => $latestId,
            'new'          => $new,
        ];
    }

    public function messagePayload(ChatMessage $m, User $viewer): array
    {
        return [
            'id'          => $m->id,
            'user_id'     => $m->user_id,
            'user_name'   => $m->user?->name ?? 'Usuário removido',
            'mine'        => $m->user_id === $viewer->id,
            'body_html'   => $m->bodyHtml(),
            'created_at'  => $m->created_at->toIso8601String(),
            'attachments' => $m->attachments->map(fn (ChatMessageAttachment $a) => [
                'id'           => $a->id,
                'filename'     => $a->filename,
                'size'         => (int) $a->size,
                'is_image'     => $a->isImage(),
                'url'          => route('chat.attachments.show', $a),
                'download_url' => route('chat.attachments.show', [$a, 'download' => 1]),
            ])->values()->all(),
        ];
    }

    private function preview(ChatMessage $m, User $viewer): string
    {
        $text = trim((string) $m->body);
        if ($text === '' && $m->attachments->isNotEmpty()) {
            $text = '📎 ' . $m->attachments->first()->filename;
        }

        $prefix = $m->user_id === $viewer->id ? 'Você: ' : '';

        return $prefix . Str::limit(preg_replace('/\s+/', ' ', $text), 80);
    }

    private function initials(?string $name): string
    {
        return $name ? mb_strtoupper(mb_substr($name, 0, 2)) : '?';
    }
}

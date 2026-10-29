<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Meeting;
use App\Models\NotificationTemplate;
use App\Models\Opportunity;
use App\Models\User;
use App\Jobs\GenerateMeetingAtaJob;
use App\Services\MeetingAgendaService;
use App\Services\MeetingAtaService;
use App\Services\MeetingText;
use App\Services\NotificationDispatchService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $view    = $request->get('view', 'calendario');
        $clients = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);

        if ($view === 'calendario') {
            [$weeks, $eventsByDay, $refMonth] = $this->buildCalendar($request);

            return view('meetings.index', compact('view', 'clients', 'weeks', 'eventsByDay', 'refMonth'));
        }

        if ($view === 'quadros') {
            $board = $this->buildBoard($request);

            return view('meetings.index', compact('view', 'clients', 'board'));
        }

        $meetings = $this->filteredMeetings($request);

        return view('meetings.index', compact('view', 'meetings', 'clients'));
    }

    // Fragmento da listagem — chamado via fetch por live-filter.js conforme o
    // usuário filtra, sem recarregar a página inteira.
    public function results(Request $request)
    {
        $meetings = $this->filteredMeetings($request);

        return view('meetings._results', compact('meetings'));
    }

    private function filteredMeetings(Request $request)
    {
        $query = Meeting::withAgendaFlag()->with(['client', 'organizer', 'participants'])
            ->orderBy('scheduled_at', 'desc');

        $this->applyFilters($query, $request);

        return $query->paginate(20)->withQueryString();
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
    }

    // Grade mensal (visão calendário) — semanas completas Dom-Sáb cobrindo o mês
    // de referência, com as reuniões agrupadas por dia.
    private function buildCalendar(Request $request): array
    {
        $refMonth = $request->filled('month') && preg_match('/^\d{4}-\d{2}$/', $request->month)
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();

        $gridStart = $refMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $refMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $query = Meeting::with('client')
            ->whereBetween('scheduled_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at');

        $this->applyFilters($query, $request);

        $eventsByDay = $query->get()->groupBy(fn (Meeting $m) => $m->scheduled_at->format('Y-m-d'));

        $weeks  = [];
        $cursor = $gridStart->copy();
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [$weeks, $eventsByDay, $refMonth];
    }

    // Board Kanban por status — não filtra por status (a própria coluna já é a
    // segmentação), só type/client_id.
    private function buildBoard(Request $request)
    {
        $query = Meeting::withAgendaFlag()->with(['client', 'organizer'])
            ->orderBy('scheduled_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return $query->get()->groupBy('status');
    }

    public function create()
    {
        $users        = User::orderBy('name')->get(['id', 'name']);
        $clients      = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $opportunities = Opportunity::orderBy('title')->get(['id', 'title', 'client_id']);
        // Traz todos os contatos com os clientes vinculados — o filtro por cliente
        // selecionado no form acontece no client-side (Alpine), já que o
        // client_id pode mudar sem recarregar a página.
        $contacts = Contact::with('clients:id')->orderBy('name')->get(['id', 'name']);

        return view('meetings.create', compact('users', 'clients', 'opportunities', 'contacts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'type'             => 'required|string|max:50',
            'modality'         => 'required|string|max:30',
            'status'           => 'required|string|max:30',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'client_id'        => 'nullable|uuid|exists:clients,id',
            'opportunity_id'   => 'nullable|uuid|exists:opportunities,id',
            'organized_by'     => 'required|exists:users,id',
            'location'         => 'nullable|string|max:255',
            'online_link'      => 'nullable|url|max:500',
            'agenda'           => 'nullable|string',
            'agenda_items'          => 'nullable|array',
            'agenda_items.*.type'   => 'required|in:macro_plan,project,task',
            'agenda_items.*.id'     => 'required|uuid',
            'participants'     => 'nullable|array',
            'participants.*'   => 'exists:users,id',
            'contacts'         => 'nullable|array',
            'contacts.*'       => 'exists:contacts,id',
        ]);
        $data['agenda'] = MeetingText::fromEditor($data['agenda'] ?? null);

        $meeting = Meeting::create([
            ...collect($data)->except(['contacts', 'agenda_items'])->all(),
            'created_by' => Auth::id(),
        ]);

        if (!empty($data['participants'])) {
            $meeting->participants()->sync($data['participants']);
        }
        if (!empty($data['contacts'])) {
            $meeting->contacts()->sync($data['contacts']);
        }
        if (!empty($data['agenda_items'])) {
            app(MeetingAgendaService::class)->sync($meeting, array_values($data['agenda_items']));
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Reunião criada com sucesso.');
    }

    public function show(Meeting $meeting)
    {
        $meeting->load(['client', 'opportunity', 'organizer', 'participants', 'contacts', 'createdBy', 'attachments.uploadedBy', 'macroPlan',
            'agendaMacroPlans', 'agendaProjects.members:users.id,users.name,users.avatar_path,users.avatar_disk', 'agendaTasks.executors',
            'tasks' => fn ($q) => $q->orderByDesc('created_at')]);
        $users = User::orderBy('name')->get(['id', 'name']);

        // Notificações internas geradas por esta reunião (ver NotificationService::notifyUsers,
        // source_type = class_basename do model, sempre "Meeting").
        $meetingNotifications = \App\Models\InternalNotification::where('source_type', 'Meeting')
            ->where('source_id', $meeting->id)
            ->with('user:id,name')
            ->orderByDesc('generated_at')
            ->get();

        // Pauta gerada pelo agente de IA (structure_ata) vem em Markdown com a mesma
        // sintaxe da ATA — reaproveita o parser em vez de mostrar texto cru.
        $agendaCards = MeetingText::cards($meeting->agenda);

        return view('meetings.show', compact('meeting', 'users', 'meetingNotifications', 'agendaCards'));
    }

    /**
     * Preview leve para o painel lateral (canvas) — não a página completa.
     */
    public function preview(Meeting $meeting)
    {
        $meeting->load(['client', 'organizer', 'macroPlan', 'attachments']);

        return view('meetings._preview', compact('meeting'));
    }

    public function ataPrint(Meeting $meeting)
    {
        $meeting->load(['client', 'organizer', 'participants']);
        $cards = MeetingText::cards($meeting->ata);

        return view('meetings.ata-print', compact('meeting', 'cards'));
    }

    public function edit(Meeting $meeting)
    {
        $meeting->load(['participants', 'contacts']);
        $users         = User::orderBy('name')->get(['id', 'name']);
        $clients       = Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(['id', 'company_name', 'nickname']);
        $opportunities = Opportunity::orderBy('title')->get(['id', 'title', 'client_id']);
        $contacts      = Contact::with('clients:id')->orderBy('name')->get(['id', 'name']);
        $agendaItems   = app(MeetingAgendaService::class)->formItems($meeting);

        return view('meetings.edit', compact('meeting', 'users', 'clients', 'opportunities', 'contacts', 'agendaItems'));
    }

    // Botão "Gerar ATA com IA" (ver MeetingAtaService / GenerateMeetingAtaJob).
    public function generateAta(Meeting $meeting)
    {
        if (blank($meeting->transcricao)) {
            return redirect()->route('meetings.show', $meeting)
                ->with('warning', 'Cole a transcrição (ou transcreva um áudio anexado) antes de gerar a ATA.');
        }

        $this->dispatchAta($meeting);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Gerando a ATA — leva cerca de um minuto. Os participantes são avisados quando ficar pronta.');
    }

    // A página da reunião pergunta a cada poucos segundos se a ATA já ficou pronta.
    public function ataStatus(Meeting $meeting)
    {
        return response()->json([
            'generating'      => MeetingAtaService::isGenerating($meeting),
            'ata_recorded_at' => $meeting->ata_recorded_at?->toIso8601String(),
        ]);
    }

    private function dispatchAta(Meeting $meeting): void
    {
        // Um clique por vez: com a geração em andamento, não enfileira outra.
        if (\Illuminate\Support\Facades\Cache::add(MeetingAtaService::generatingKey($meeting->id), Auth::id(), now()->addMinutes(10))) {
            GenerateMeetingAtaJob::dispatch($meeting->id, (int) Auth::id());
        }
    }

    // Seletor da pauta citada (ver MeetingAgendaService::search).
    public function agendaSearch(Request $request, MeetingAgendaService $agenda)
    {
        $data = $request->validate([
            'q'         => 'nullable|string|max:100',
            'client_id' => 'nullable|uuid',
        ]);

        return response()->json(['items' => $agenda->search($data['q'] ?? '', $data['client_id'] ?? null)]);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'type'             => 'required|string|max:50',
            'modality'         => 'required|string|max:30',
            'status'           => 'required|string|max:30',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'client_id'        => 'nullable|uuid|exists:clients,id',
            'opportunity_id'   => 'nullable|uuid|exists:opportunities,id',
            'organized_by'     => 'required|exists:users,id',
            'location'         => 'nullable|string|max:255',
            'online_link'      => 'nullable|url|max:500',
            'agenda'           => 'nullable|string',
            'agenda_items'          => 'nullable|array',
            'agenda_items.*.type'   => 'required|in:macro_plan,project,task',
            'agenda_items.*.id'     => 'required|uuid',
            'ata'              => 'nullable|string',
            'transcricao'      => 'nullable|string',
            'participants'     => 'nullable|array',
            'participants.*'   => 'exists:users,id',
            'contacts'         => 'nullable|array',
            'contacts.*'       => 'exists:contacts,id',
        ]);

        // Pauta e ATA vêm do editor rico (ver MeetingText — a IA continua lendo Markdown).
        $data['agenda'] = MeetingText::fromEditor($data['agenda'] ?? null);
        $data['ata']    = MeetingText::fromEditor($data['ata'] ?? null);
        // O editor reenvia a ATA mesmo quando só o título/pauta mudou, e pode serializar o
        // HTML um pouco diferente (ou converter uma ATA antiga em Markdown). Conteúdo igual =
        // não mexe — senão os participantes receberiam "ATA atualizada" sem nada ter mudado.
        if ($data['ata'] !== null && MeetingText::sameContent($data['ata'], $meeting->ata)) {
            unset($data['ata']);
        }

        // "Atualizada em" acompanha toda mudança de conteúdo da ATA (só chega aqui se mudou).
        if (!empty($data['ata'])) {
            $data['ata_recorded_at'] = now();
        }

        // Carimba quando a transcrição muda — a automação de fechamento de Macro
        // usa isso pra decidir se re-processa a ATA (ver finalize_macro_meeting).
        if (array_key_exists('transcricao', $data) && ($data['transcricao'] ?? null) !== $meeting->transcricao) {
            $data['transcricao_updated_at'] = now();
        }

        $meeting->update(collect($data)->except('agenda_items')->all());

        $meeting->participants()->sync($data['participants'] ?? []);
        $meeting->contacts()->sync($data['contacts'] ?? []);
        // Só mexe na pauta citada quando o seletor veio no form (marcador abaixo) — um
        // form sem ele não pode apagar o que estava citado.
        if ($request->boolean('agenda_items_sent')) {
            app(MeetingAgendaService::class)->sync($meeting, array_values($data['agenda_items'] ?? []));
        }

        // Botão "Salvar e gerar ATA com IA" (ao lado da Transcrição, na edição).
        if ($request->boolean('generate_ata') && filled($meeting->transcricao)) {
            $this->dispatchAta($meeting);

            return redirect()->route('meetings.show', $meeting)
                ->with('success', 'Reunião salva. Gerando a ATA — leva cerca de um minuto; os participantes são avisados quando ficar pronta.');
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Reunião atualizada.');
    }

    public function updateStatus(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'status' => 'required|string|max:30',
        ]);

        $meeting->update(['status' => $data['status']]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Status atualizado.');
    }

    // Notifica todo mundo vinculado à reunião: contatos do cliente (WhatsApp/e-mail,
    // via webhook n8n) e participantes internos (sino de notificação do próprio App,
    // ver NotificationService — não há canal e-mail/WhatsApp genérico pra User).
    public function notify(Meeting $meeting, NotificationDispatchService $service)
    {
        $meeting->loadMissing('contacts', 'participants');

        $notifiedContacts = 0;
        if ($meeting->client && $meeting->contacts->isNotEmpty()) {
            foreach ($meeting->contacts as $contact) {
                foreach (array_keys(NotificationTemplate::$channels) as $channel) {
                    $service->dispatch('reuniao_lembrete', $channel, $meeting->client, $contact, [
                        'data_reuniao' => $meeting->scheduled_at->format('d/m/Y H:i'),
                        'link_reuniao' => $meeting->online_link ?? '',
                    ]);
                }
            }
            $notifiedContacts = $meeting->contacts->count();
        }

        $notifiedParticipants = 0;
        if ($meeting->participants->isNotEmpty()) {
            app(SystemNotificationService::class)->send(
                'meeting.lembrete_manual',
                $meeting->participants,
                [
                    'meeting_title' => $meeting->title,
                    'meeting_date'  => $meeting->scheduled_at->format('d/m/Y H:i'),
                ],
                route('meetings.show', $meeting),
                $meeting,
                $meeting->organization_id,
            );
            $notifiedParticipants = $meeting->participants->count();
        }

        if ($notifiedContacts === 0 && $notifiedParticipants === 0) {
            return back()->with('warning', 'Nenhum contato ou participante interno vinculado a esta reunião.');
        }

        return back()->with('success', "Lembrete enviado — {$notifiedContacts} contato(s) e {$notifiedParticipants} participante(s) da equipe.");
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return redirect()->route('meetings.index')
            ->with('success', 'Reunião removida.');
    }
}

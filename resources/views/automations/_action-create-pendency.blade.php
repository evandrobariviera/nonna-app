{{-- Ação "Criar Pendência" — mesmo bloco no create e no edit ($cfg = action_config salvo,
     [] no create). Executada por AutomationJob::createPendency(). Cada pessoa (quem deve /
     quem cobra / quem precisa) é uma pessoa fixa OU alguém da entidade que disparou. --}}
@php
    $cfg = $cfg ?? [];
    $inp = 'background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)';
    $lbl = 'color:var(--muted); letter-spacing:.05em';
    // "Variáveis de pessoa" — grupo => [valor => [rótulo, entidades onde faz sentido (null = todas)]].
    // Resolvidas em AutomationJob::resolvePerson().
    $personSources = [
        'Fixa' => [
            'fixed' => ['Pessoa fixa (escolher ao lado)', null],
        ],
        'Da tarefa que disparou' => [
            'executor'     => ['Executor da tarefa', ['task', 'ticket']],
            'responsavel'  => ['Responsável da tarefa', ['task', 'ticket']],
            'task_creator' => ['Quem criou a tarefa', ['task', 'ticket']],
        ],
        'Do cliente' => [
            'client_creative_lead' => ['Direção criativa do cliente', null],
            'client_ad_manager'    => ['Gestor da conta de anúncio do cliente', null],
        ],
        'Do planejamento' => [
            'plan_responsible' => ['Responsável do planejamento', ['task', 'ticket', 'project', 'meeting', 'macro_plan']],
        ],
        'Outros' => [
            'organizer'         => ['Organizador da reunião', ['meeting']],
            'opportunity_owner' => ['Responsável da oportunidade', ['opportunity']],
            'creator'           => ['Quem criou o registro que disparou', null],
        ],
    ];
    $sourceSelect = function (string $field, string $default) use ($cfg, $personSources, $inp) {
        $current = $cfg[$field] ?? $default;
        $html = '<select name="action_config[' . $field . ']" x-model="' . $field . '" class="w-full px-3 py-2.5 text-sm focus:outline-none" style="' . $inp . '">';
        foreach ($personSources as $group => $options) {
            $html .= '<optgroup label="' . e($group) . '">';
            foreach ($options as $value => [$label, $entities]) {
                $show = $entities ? ' x-show="' . e(json_encode($entities)) . '.includes(entityType)"' : '';
                $html .= '<option value="' . $value . '"' . ($current === $value ? ' selected' : '') . $show . '>' . e($label) . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html . '</select>';
    };
@endphp

<div x-show="actionType === 'create_pendency'" x-cloak class="grid gap-3"
     x-data="{
         p_owed_by: @js($cfg['owed_by'] ?? 'cliente'),
         owed_user_source: @js($cfg['owed_user_source'] ?? 'fixed'),
         owner_source: @js($cfg['owner_source'] ?? 'fixed'),
         requester_source: @js($cfg['requester_source'] ?? 'executor'),
     }">
    <p class="text-xs" style="color:var(--muted)">
        Cria uma pendência (algo que alguém precisa entregar pra o trabalho andar). Se quem disparou for Tarefa, Projeto ou Planejamento,
        a pendência fica ligada a ele; senão, só ao cliente. Não cria de novo se já existir uma aberta com o mesmo título no mesmo lugar.
    </p>

    <div>
        <label class="block text-xs font-semibold mb-1" style="{{ $lbl }}">O QUE ESTÁ PENDENTE *</label>
        <input type="text" name="action_config[pendency_title]" value="{{ $cfg['pendency_title'] ?? '' }}"
               placeholder="Ex: Subir criativo na campanha — {task_title}"
               class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
        <p class="text-xs mt-1" style="color:var(--muted)">Variáveis: {task_title}, {client_name}, {project_name}, {meeting_title}, {opportunity_title}...</p>
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1" style="{{ $lbl }}">DETALHES (OPCIONAL)</label>
        <textarea name="action_config[pendency_description]" rows="2" class="w-full px-3 py-2.5 text-sm focus:outline-none resize-none" style="{{ $inp }}">{{ $cfg['pendency_description'] ?? '' }}</textarea>
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1" style="{{ $lbl }}">TIPO</label>
        <select name="action_config[pendency_kind]" class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
            @foreach(\App\Models\Pendency::$kinds as $key => $k)
                <option value="{{ $key }}" @selected(($cfg['pendency_kind'] ?? 'insumo') === $key)>{{ $k['label'] }}</option>
            @endforeach
        </select>
    </div>

    {{-- QUEM DEVE --}}
    <div class="pt-3" style="border-top:1px solid var(--border2)">
        <label class="block text-xs font-semibold mb-1" style="{{ $lbl }}">QUEM DEVE ENTREGAR</label>
        <select name="action_config[owed_by]" x-model="p_owed_by" class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
            @foreach(\App\Models\Pendency::$owedBy as $key => $o)
                <option value="{{ $key }}" @selected(($cfg['owed_by'] ?? 'cliente') === $key)>{{ $o['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div x-show="p_owed_by === 'cliente'" x-cloak>
        <label class="flex items-center gap-2 cursor-pointer text-xs" style="color:var(--text)">
            <input type="hidden" name="action_config[owed_primary_contact]" value="0">
            <input type="checkbox" name="action_config[owed_primary_contact]" value="1" @checked(!empty($cfg['owed_primary_contact']))
                   style="width:16px; height:16px; accent-color:var(--purple)">
            Usar o contato principal do cliente (habilita "Cobrar no WhatsApp")
        </label>
    </div>
    <div x-show="p_owed_by === 'agencia'" x-cloak class="grid grid-cols-2 gap-3">
        <div>{!! $sourceSelect('owed_user_source', 'fixed') !!}</div>
        <div x-show="owed_user_source === 'fixed'">
            <select name="action_config[owed_user_id]" class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
                <option value="">Qual pessoa…</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(($cfg['owed_user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div x-show="p_owed_by === 'terceiro'" x-cloak>
        <input type="text" name="action_config[owed_party]" value="{{ $cfg['owed_party'] ?? '' }}" placeholder="Ex.: Gráfica, fornecedor, Meta…"
               class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
    </div>

    {{-- QUEM COBRA / QUEM PRECISA --}}
    @foreach([['owner', 'QUEM COBRA', 'fixed'], ['requester', 'QUEM PRECISA', 'executor']] as [$role, $label, $default])
        <div class="pt-3 grid grid-cols-2 gap-3" style="border-top:1px solid var(--border2)">
            <div class="col-span-2">
                <label class="block text-xs font-semibold" style="{{ $lbl }}">{{ $label }}</label>
            </div>
            <div>{!! $sourceSelect($role . '_source', $default) !!}</div>
            <div x-show="{{ $role }}_source === 'fixed'">
                <select name="action_config[{{ $role }}_user_id]" class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
                    <option value="">Qual pessoa…</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(($cfg[$role . '_user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endforeach
    <p class="text-xs -mt-1" style="color:var(--muted)">Se a pessoa não puder ser descoberta (ex.: tarefa sem executor), quem cobra fica com quem criou a automação.</p>

    {{-- PRAZO — nomes próprios (pendency_*): todas as ações dividem o mesmo form e "Criar Tarefa" já usa title/due_in_days --}}
    <div class="pt-3 grid grid-cols-2 gap-3" style="border-top:1px solid var(--border2)">
        <div>
            <label class="block text-xs font-semibold mb-1" style="{{ $lbl }}">PRAZO EM DIAS (0 = SEM PRAZO)</label>
            <input type="number" name="action_config[pendency_due_in_days]" min="0" value="{{ $cfg['pendency_due_in_days'] ?? 0 }}"
                   class="w-full px-3 py-2.5 text-sm focus:outline-none" style="{{ $inp }}">
        </div>
        <div class="flex items-end pb-2.5">
            <label class="flex items-center gap-2 cursor-pointer text-xs" style="color:var(--text)">
                <input type="hidden" name="action_config[pendency_skip_weekends]" value="0">
                <input type="checkbox" name="action_config[pendency_skip_weekends]" value="1" @checked(!empty($cfg['pendency_skip_weekends']))
                       style="width:16px; height:16px; accent-color:var(--purple)">
                Rolar pro próximo dia útil
            </label>
        </div>
    </div>
</div>

<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AiAgent;
use App\Models\Client;
use App\Models\Sector;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function index()
    {
        $automations = Automation::with('createdBy')
            ->orderBy('entity_type')
            ->orderBy('name')
            ->get()
            ->groupBy('entity_type');

        return view('automations.index', compact('automations'));
    }

    public function create()
    {
        $agents = AiAgent::where('is_active', true)->orderBy('name')->get();

        return view('automations.create', [
            'entityTypes'     => Automation::$entityTypes,
            'triggerTypes'    => Automation::$triggerTypes,
            'actionTypes'     => Automation::$actionTypes,
            'agents'          => $agents,
            'dateFields'      => $this->dateFieldsByEntity(),
            'conditionFields' => $this->conditionFieldsByEntity(),
            'sectors'         => Sector::orderBy('name')->get(),
            'clients'         => Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(),
            'taskTypes'       => Task::$types,
            'users'           => $this->organizationUsers(),
            'functionRoles'   => \App\Models\FunctionalRole::where('organization_id', app('currentOrganization')->id)
                ->orderBy('name')->pluck('name', 'key'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'entity_type'   => 'required|in:task,ticket,project,campaign,opportunity,meeting,macro_plan',
            'trigger_type'  => 'required|in:status_changed,field_updated,date_reached,executor_added,created,manual',
            'trigger_config'=> 'nullable|array',
            'action_type'   => 'required|in:run_ai_agent,send_webhook,update_field,send_notification,create_record,create_macroplan_review,create_internal_review_pauta,create_macroplan_from_meeting,structure_ata',
            'action_config' => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $data['created_by']    = auth()->id();
        $data['is_active']     = $request->boolean('is_active', true);
        $data['trigger_config']= $request->input('trigger_config', []);
        $data['action_config'] = $request->input('action_config', []);

        // Armazena o nome do agente no action_config para facilitar exibição
        if (in_array($data['action_type'], ['run_ai_agent', 'create_internal_review_pauta', 'structure_ata'], true) && !empty($data['action_config']['agent_id'])) {
            $agent = AiAgent::find($data['action_config']['agent_id']);
            if ($agent) {
                $data['action_config']['agent_name'] = $agent->name;
            }
        }

        Automation::create($data);

        return redirect()->route('automations.index')
            ->with('success', 'Automação criada com sucesso.');
    }

    public function edit(Automation $automation)
    {
        $agents = AiAgent::where('is_active', true)->orderBy('name')->get();

        return view('automations.edit', [
            'automation'      => $automation,
            'entityTypes'     => Automation::$entityTypes,
            'triggerTypes'    => Automation::$triggerTypes,
            'actionTypes'     => Automation::$actionTypes,
            'agents'          => $agents,
            'dateFields'      => $this->dateFieldsByEntity(),
            'conditionFields' => $this->conditionFieldsByEntity(),
            'sectors'         => Sector::orderBy('name')->get(),
            'clients'         => Client::where('status', 'active')->orderByRaw('COALESCE(nickname, company_name)')->get(),
            'taskTypes'       => Task::$types,
            'users'           => $this->organizationUsers(),
            'functionRoles'   => \App\Models\FunctionalRole::where('organization_id', app('currentOrganization')->id)
                ->orderBy('name')->pluck('name', 'key'),
        ]);
    }

    public function update(Request $request, Automation $automation)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'entity_type'   => 'required|in:task,ticket,project,campaign,opportunity,meeting,macro_plan',
            'trigger_type'  => 'required|in:status_changed,field_updated,date_reached,executor_added,created,manual',
            'trigger_config'=> 'nullable|array',
            'action_type'   => 'required|in:run_ai_agent,send_webhook,update_field,send_notification,create_record,create_macroplan_review,create_internal_review_pauta,create_macroplan_from_meeting,structure_ata',
            'action_config' => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $data['is_active']     = $request->boolean('is_active', true);
        $data['trigger_config']= $request->input('trigger_config', []);
        $data['action_config'] = $request->input('action_config', []);

        if (in_array($data['action_type'], ['run_ai_agent', 'create_internal_review_pauta', 'structure_ata'], true) && !empty($data['action_config']['agent_id'])) {
            $agent = AiAgent::find($data['action_config']['agent_id']);
            if ($agent) {
                $data['action_config']['agent_name'] = $agent->name;
            }
        }

        $automation->update($data);

        return redirect()->route('automations.index')
            ->with('success', 'Automação atualizada.');
    }

    public function destroy(Automation $automation)
    {
        $automation->delete();

        return redirect()->route('automations.index')
            ->with('success', 'Automação removida.');
    }

    public function toggleActive(Automation $automation)
    {
        $automation->update(['is_active' => !$automation->is_active]);

        return back()->with('success', $automation->is_active ? 'Automação ativada.' : 'Automação pausada.');
    }

    public function logs(Automation $automation)
    {
        $logs = $automation->logs()->latest('ran_at')->paginate(30);

        return view('automations.logs', compact('automation', 'logs'));
    }

    // Campos de condição disponíveis por entity_type — a UI troca de lista conforme o
    // usuário troca a entidade selecionada, então manda o mapa inteiro de uma vez.
    private function conditionFieldsByEntity(): array
    {
        return collect(array_keys(Automation::$entityTypes))
            ->mapWithKeys(fn ($type) => [$type => Automation::conditionFieldsFor($type)])
            ->all();
    }

    // Campos de data disponíveis pro gatilho "Data alcançada" por entity_type — mesmo
    // princípio de conditionFieldsByEntity(), a UI troca a lista conforme a entidade.
    private function dateFieldsByEntity(): array
    {
        return collect(array_keys(Automation::$entityTypes))
            ->mapWithKeys(fn ($type) => [$type => Automation::dateFieldsFor($type)])
            ->all();
    }

    private function organizationUsers()
    {
        return User::whereHas('organizations', fn ($q) => $q->where('organizations.id', app('currentOrganization')->id))
            ->orderBy('name')
            ->get();
    }
}

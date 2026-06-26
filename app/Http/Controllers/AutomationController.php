<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AiAgent;
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
            'entityTypes'  => Automation::$entityTypes,
            'triggerTypes' => Automation::$triggerTypes,
            'actionTypes'  => Automation::$actionTypes,
            'agents'       => $agents,
            'taskStatuses' => \App\Models\Task::$statuses,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'entity_type'   => 'required|in:task,project,campaign',
            'trigger_type'  => 'required|in:status_changed,field_updated,created,manual',
            'trigger_config'=> 'nullable|array',
            'action_type'   => 'required|in:run_ai_agent,send_webhook,update_field,send_notification',
            'action_config' => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $data['created_by']    = auth()->id();
        $data['is_active']     = $request->boolean('is_active', true);
        $data['trigger_config']= $request->input('trigger_config', []);
        $data['action_config'] = $request->input('action_config', []);

        // Armazena o nome do agente no action_config para facilitar exibição
        if ($data['action_type'] === 'run_ai_agent' && !empty($data['action_config']['agent_id'])) {
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
            'automation'   => $automation,
            'entityTypes'  => Automation::$entityTypes,
            'triggerTypes' => Automation::$triggerTypes,
            'actionTypes'  => Automation::$actionTypes,
            'agents'       => $agents,
            'taskStatuses' => \App\Models\Task::$statuses,
        ]);
    }

    public function update(Request $request, Automation $automation)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'entity_type'   => 'required|in:task,project,campaign',
            'trigger_type'  => 'required|in:status_changed,field_updated,created,manual',
            'trigger_config'=> 'nullable|array',
            'action_type'   => 'required|in:run_ai_agent,send_webhook,update_field,send_notification',
            'action_config' => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $data['is_active']     = $request->boolean('is_active', true);
        $data['trigger_config']= $request->input('trigger_config', []);
        $data['action_config'] = $request->input('action_config', []);

        if ($data['action_type'] === 'run_ai_agent' && !empty($data['action_config']['agent_id'])) {
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
}

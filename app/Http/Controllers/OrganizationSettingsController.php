<?php

namespace App\Http\Controllers;

use App\Models\AiAgent;
use App\Models\FunctionalRole;
use App\Models\NotificationTemplate;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function index(): View
    {
        $org          = app('currentOrganization');
        $integrations = $org->integrations()->orderBy('provider')->orderBy('label')->get();
        $members      = $org->users()->withPivot(['role'])->with('functionalRoles')->orderBy('name')->get();
        $apiTokens    = $org->tokens()->orderByDesc('created_at')->get();
        $aiAgents     = AiAgent::where('is_active', true)->orderBy('name')->get();
        $sectors      = Sector::with('users:id,name')->orderBy('name')->get();
        $functionalRoles = FunctionalRole::where('organization_id', $org->id)
            ->withCount('users')
            ->orderByDesc('is_protected')
            ->orderBy('name')
            ->get();

        // Usuários que existem em `users` mas não pertencem a nenhuma organização —
        // normalmente sobras de registro público de teste (/register). Sem isso, esses
        // usuários não aparecem em lugar nenhum do sistema (nem na Equipe, que só lista
        // membros da organização atual).
        $orphanUsers = User::doesntHave('organizations')->orderByDesc('created_at')->get();

        $notificationTemplates = NotificationTemplate::where('organization_id', $org->id)
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->keyBy('channel'));

        return view('settings.index', compact('org', 'integrations', 'members', 'apiTokens', 'aiAgents', 'notificationTemplates', 'sectors', 'orphanUsers', 'functionalRoles'));
    }

    public function createToken(Request $request): RedirectResponse
    {
        $org  = app('currentOrganization');
        $data = $request->validate(['token_name' => ['required', 'string', 'max:80']]);

        $token = $org->createToken($data['token_name']);

        return back()
            ->with('new_token', $token->plainTextToken)
            ->with('success', 'Token gerado. Copie agora — não será exibido novamente.')
            ->with('tab', 'api');
    }

    public function deleteToken(Request $request, int $tokenId): RedirectResponse
    {
        $org = app('currentOrganization');
        $org->tokens()->where('id', $tokenId)->delete();

        return back()->with('success', 'Token revogado.')->with('tab', 'api');
    }

    public function update(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:120'],
            'primary_color'              => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color'            => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url'                   => ['nullable', 'url', 'max:500'],
            'campaign_insights_agent_id' => ['nullable', 'uuid', 'exists:ai_agents,id'],
        ]);

        $settings = $org->settings ?? [];
        $settings['branding']['primary_color']   = $data['primary_color']   ?? '#643B8E';
        $settings['branding']['secondary_color'] = $data['secondary_color'] ?? '#EE7919';
        $settings['branding']['logo_url']        = $data['logo_url'] ?? null;
        $settings['campaign_insights']['agent_id'] = $data['campaign_insights_agent_id'] ?? null;

        $org->update([
            'name'     => $data['name'],
            'settings' => $settings,
        ]);

        return back()->with('success', 'Configurações salvas.');
    }
}

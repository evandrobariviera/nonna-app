<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\NotificationTemplate;
use App\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    /**
     * Disparo de teste — usa o próprio admin logado como destinatário
     * fictício, pra dar pra conferir no n8n sem precisar de cliente/contato/
     * assinatura reais configurados ainda.
     */
    public function test(string $type, string $channel): RedirectResponse
    {
        abort_unless(array_key_exists($type, NotificationTemplate::$types), 404);
        abort_unless(array_key_exists($channel, NotificationTemplate::$channels), 404);

        $org = app('currentOrganization');

        if (!$org->integration('n8n')?->credential('webhook_url')) {
            return back()
                ->withErrors(['test' => 'Configure a URL do webhook em Configurações > Integrações (n8n) antes de testar.'])
                ->with('tab', 'mensagens');
        }

        $template = NotificationTemplate::where('organization_id', $org->id)
            ->where('type', $type)->where('channel', $channel)->first();

        if (!$template || (blank($template->subject) && blank($template->body))) {
            return back()
                ->withErrors(['test' => 'Salve um texto pra esse gatilho/canal antes de testar.'])
                ->with('tab', 'mensagens');
        }

        $admin = auth()->user();
        $fakeClient = new Client(['company_name' => 'Cliente de Teste']);
        $fakeClient->organization_id = $org->id; // não é $fillable — setado direto, é modelo fictício não persistido
        $fakeContact = new Contact(['name' => $admin->name, 'email' => $admin->email, 'whatsapp' => null]);

        // "cliente"/"contato" ficam de fora — o dispatch() já preenche os dois
        // sozinho a partir do $fakeClient/$fakeContact reais definidos acima.
        $sampleVars = [];
        foreach (NotificationTemplate::$variableHints[$type] ?? [] as $hint) {
            $key = trim($hint, '{}');
            if (in_array($key, ['cliente', 'contato'], true)) {
                continue;
            }
            $sampleVars[$key] = "[{$key}]";
        }

        app(NotificationDispatchService::class)->dispatch($type, $channel, $fakeClient, $fakeContact, $sampleVars);

        return back()->with('success', 'Disparo de teste enviado — confira no n8n.')->with('tab', 'mensagens');
    }

    public function update(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $data = $request->validate([
            'templates'              => ['nullable', 'array'],
            'templates.*.*.subject'  => ['nullable', 'string', 'max:200'],
            'templates.*.*.body'     => ['nullable', 'string', 'max:4000'],
        ]);

        foreach (NotificationTemplate::$types as $type => $label) {
            foreach (NotificationTemplate::$channels as $channel => $channelLabel) {
                $subject = $data['templates'][$type][$channel]['subject'] ?? null;
                $body    = $data['templates'][$type][$channel]['body'] ?? null;

                if (blank($subject) && blank($body)) {
                    continue;
                }

                NotificationTemplate::updateOrCreate(
                    ['organization_id' => $org->id, 'type' => $type, 'channel' => $channel],
                    ['subject' => $subject, 'body' => $body]
                );
            }
        }

        return back()->with('success', 'Mensagens padrão salvas.')->with('tab', 'mensagens');
    }
}

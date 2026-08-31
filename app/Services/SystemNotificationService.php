<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Notificações internas (sino) "do sistema" — as que nascem no código, não de
 * uma Automação. O texto (título/corpo) e o liga/desliga são editáveis em
 * Configurações → Notificações Internas (tabela notification_settings); sem
 * linha salva, cai no padrão do catálogo abaixo.
 *
 * Destinatário e link continuam resolvidos por quem chama — aqui só entra
 * texto + on/off. Interpolação por `{token}` (ver App\Support\TemplateVariables).
 */
class SystemNotificationService
{
    /**
     * event_key => [group, label, context, title, body, kind]
     * `context` casa com App\Support\TemplateVariables::for().
     */
    public static array $catalog = [
        'onboarding.contrato_analise' => [
            'group'   => 'Onboarding',
            'label'   => 'Contrato em análise (cadastro concluído)',
            'context' => 'client',
            'title'   => 'Contrato em análise — {client_name}',
            'body'    => 'Cliente concluiu o cadastro. Contrato gerado (rascunho) e ticket aberto para conferência.',
            'kind'    => 'contrato_analise',
        ],
        'meeting.transcricao_ok' => [
            'group'   => 'Reuniões',
            'label'   => 'Transcrição de áudio concluída',
            'context' => 'meeting',
            'title'   => 'Transcrição concluída',
            'body'    => 'O áudio "{attachment_name}" foi transcrito e preenchido na reunião "{meeting_title}".',
            'kind'    => 'meeting_transcribed',
        ],
        'meeting.transcricao_falhou' => [
            'group'   => 'Reuniões',
            'label'   => 'Falha na transcrição de áudio',
            'context' => 'meeting',
            'title'   => 'Falha na transcrição',
            'body'    => 'Não foi possível transcrever "{attachment_name}" — verifique se o áudio abre normalmente e tente de novo.',
            'kind'    => 'meeting_transcribe_failed',
        ],
        'meeting.lembrete_manual' => [
            'group'   => 'Reuniões',
            'label'   => 'Lembrete de reunião (botão "Notificar")',
            'context' => 'meeting',
            'title'   => 'Lembrete: {meeting_title}',
            'body'    => 'Reunião em {meeting_date}.',
            'kind'    => 'reuniao_lembrete',
        ],
        'macroplan.geracao_falhou' => [
            'group'   => 'Planejamento',
            'label'   => 'Falha ao montar o Macroplanejamento por IA',
            'context' => 'macro_plan',
            'title'   => 'Falha ao montar o Macroplanejamento por IA',
            'body'    => 'A reunião "{meeting_title}" ({client_name}) foi encerrada, mas o agente não gerou o planejamento: {reason}. Monte o Macroplanejamento manualmente a partir da ATA.',
            'kind'    => 'macroplan_generation_failed',
        ],
        'portal.modulo_solicitado' => [
            'group'   => 'Portal do Cliente',
            'label'   => 'Cliente pediu contratação de módulo',
            'context' => 'portal_lead',
            'title'   => 'Pedido de contratação — {module_label}',
            'body'    => '{client_name} quer contratar o módulo "{module_label}".',
            'kind'    => 'modulo_solicitado',
        ],
    ];

    /**
     * @param Collection<int, \App\Models\User> $users
     */
    public function send(
        string $eventKey,
        Collection $users,
        array $vars = [],
        ?string $link = null,
        ?Model $source = null,
        ?string $organizationId = null,
    ): void {
        $cat = self::$catalog[$eventKey] ?? null;
        if (!$cat) {
            throw new \InvalidArgumentException("Notificação de sistema desconhecida: {$eventKey}");
        }

        if ($users->isEmpty()) {
            return;
        }

        // hasTable: janela entre push e deploy da migration — sem linha salva o
        // texto vem do catálogo mesmo.
        $setting = Schema::hasTable('notification_settings')
            ? NotificationSetting::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organizationId)
                ->where('event_key', $eventKey)
                ->first()
            : null;

        if ($setting && !$setting->is_enabled) {
            return;
        }

        $title = $this->render(filled($setting?->title) ? $setting->title : $cat['title'], $vars);
        $body  = $this->render(filled($setting?->body) ? $setting->body : $cat['body'], $vars);

        app(NotificationService::class)->notifyUsers(
            $users, $cat['kind'], $title, $body, $link, $source, $organizationId,
        );
    }

    private function render(string $text, array $vars): string
    {
        $repl = [];
        foreach ($vars as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $repl['{' . $k . '}'] = (string) $v;
            }
        }

        // Remove tokens não fornecidos (junto de um ": " / "- " que os anteceda)
        // pra não vazar "{algo}" nem pontuação solta no texto final.
        $text = strtr($text, $repl);
        $text = preg_replace('/(\s*[:\-–]\s*)?\{[a-z_]+\}/iu', '', $text);
        $text = preg_replace('/ {2,}/', ' ', $text);
        return trim(preg_replace('/\s+([.,;!?])/', '$1', $text));
    }
}

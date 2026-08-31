<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOnboarding extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'current_phase',
        'fase1_checklist',
        'fase1_notes',
        'fase1_completed_at',
        'fase2_checklist',
        'fase2_notes',
        'fase2_completed_at',
        'fase3_checklist',
        'fase3_notes',
        'fase3_completed_at',
        'fase4_checklist',
        'fase4_notes',
        'fase4_completed_at',
        'fase5_checklist',
        'fase5_notes',
        'fase5_completed_at',
        'responsible_id',
        'completed_at',
    ];

    protected $casts = [
        'fase1_checklist'    => 'array',
        'fase2_checklist'    => 'array',
        'fase3_checklist'    => 'array',
        'fase4_checklist'    => 'array',
        'fase5_checklist'    => 'array',
        'fase1_completed_at' => 'datetime',
        'fase2_completed_at' => 'datetime',
        'fase3_completed_at' => 'datetime',
        'fase4_completed_at' => 'datetime',
        'fase5_completed_at' => 'datetime',
        'completed_at'       => 'datetime',
    ];

    public static array $phases = [
        'fase1' => 'Fase 1 — Gatilho (Dia 0)',
        'fase2' => 'Fase 2 — Welcome Call (Dias 1-2)',
        'fase3' => 'Fase 3 — Setup Técnico (Dias 3-4)',
        'fase4' => 'Fase 4 — Kick-off (Dia 5)',
        'fase5' => 'Fase 5 — Macroplanejamento',
        'concluido' => 'Concluído',
    ];

    public static array $defaultChecklists = [
        'fase1' => [
            'whatsapp_boas_vindas'  => false,
            'link_cadastro_enviado' => false,
            'cadastro_preenchido'   => false,
            'contrato_gerado'       => false,
            'pasta_drive_criada'    => false,
            'grupo_whatsapp_criado' => false,
            'ticket_contrato_aberto' => false,
        ],
        'fase2' => [
            'welcome_call_realizada' => false,
            'regras_apresentadas'    => false,
            'acessos_coletados'      => false,
            'contrato_enviado'       => false,
            'manual_enviado'         => false,
            'kickoff_agendado'       => false,
        ],
        'fase3' => [
            'bm_auditado'                  => false,
            'pixel_configurado'            => false,
            'gtm_instalado'                => false,
            'ga4_configurado'              => false,
            'api_conversoes_configurada'   => false,
            'looker_studio_criado'         => false,
        ],
        'fase4' => [
            'kickoff_realizado'        => false,
            'imersao_negocio_feita'    => false,
        ],
        'fase5' => [
            'macroplanejamento_criado' => false,
            'projetos_criados'         => false,
            'backlog_preenchido'       => false,
            'sprint_iniciado'          => false,
        ],
    ];

    public static array $checklistLabels = [
        'whatsapp_boas_vindas'       => 'WhatsApp de boas-vindas enviado',
        'link_cadastro_enviado'      => 'Link de cadastro enviado',
        'cadastro_preenchido'        => 'Cliente preencheu o cadastro',
        'contrato_gerado'            => 'Contrato gerado automaticamente',
        'pasta_drive_criada'         => 'Pasta no Drive criada',
        'grupo_whatsapp_criado'      => 'Grupo de WhatsApp criado',
        'ticket_contrato_aberto'     => 'Ticket "Contrato em análise" aberto',
        'welcome_call_realizada'     => 'Welcome Call realizada',
        'regras_apresentadas'        => 'Regras da agência apresentadas',
        'acessos_coletados'          => 'Acessos e senhas coletados',
        'contrato_enviado'           => 'Contrato assinado enviado',
        'manual_enviado'             => 'Manual do cliente enviado',
        'kickoff_agendado'           => 'Kick-off estratégico agendado',
        'bm_auditado'                => 'Business Manager auditado',
        'pixel_configurado'          => 'Pixel configurado',
        'gtm_instalado'              => 'GTM instalado',
        'ga4_configurado'            => 'GA4 configurado',
        'api_conversoes_configurada' => 'API de Conversões configurada',
        'looker_studio_criado'       => 'Dashboard Looker Studio criado',
        'kickoff_realizado'          => 'Kick-off estratégico realizado',
        'imersao_negocio_feita'      => 'Imersão no negócio concluída',
        'macroplanejamento_criado'   => 'Macroplanejamento criado',
        'projetos_criados'           => 'Projetos criados',
        'backlog_preenchido'         => 'Backlog preenchido',
        'sprint_iniciado'            => 'Sprint iniciado',
    ];

    public function phaseLabel(): string
    {
        return self::$phases[$this->current_phase] ?? $this->current_phase;
    }

    /**
     * Marca (ou desmarca) um item de checklist em qualquer fase — encontra a fase
     * dona da chave sozinho. Retorna false se a chave não existe em nenhuma fase.
     */
    public function setChecklistItem(string $key, bool $done): bool
    {
        foreach (['fase1', 'fase2', 'fase3', 'fase4', 'fase5'] as $phase) {
            $checklist = $this->{$phase . '_checklist'} ?? self::$defaultChecklists[$phase];
            if (array_key_exists($key, $checklist)) {
                $checklist[$key] = $done;
                $this->{$phase . '_checklist'} = $checklist;
                $this->save();
                return true;
            }
        }
        return false;
    }

    public function markDone(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->setChecklistItem($key, true);
        }
    }

    public function phaseKeys(): array
    {
        return ['fase1', 'fase2', 'fase3', 'fase4', 'fase5'];
    }

    public function progressPercent(): int
    {
        $total = 0;
        $done = 0;

        foreach (['fase1', 'fase2', 'fase3', 'fase4', 'fase5'] as $phase) {
            $checklist = $this->{$phase . '_checklist'} ?? self::$defaultChecklists[$phase] ?? [];
            $total += count($checklist);
            $done  += count(array_filter($checklist));
        }

        return $total > 0 ? (int) round(($done / $total) * 100) : 0;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}

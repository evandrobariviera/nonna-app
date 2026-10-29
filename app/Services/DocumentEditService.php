<?php

namespace App\Services;

use App\Models\MacroPlan;
use App\Models\MacroPlanActivity;
use App\Models\MacroPlanAttachment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAttachment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Edição do documento HTML (planejamento / campanha / projeto) dentro do App (2026-10-29).
 * O documento continua vindo da skill; aqui a equipe corrige texto e os dados do bloco
 * escondido ("nonna-dados"). Regras combinadas com o Evandro:
 *   - qualquer pessoa da equipe edita; cada salvar vira uma VERSÃO nova (o arquivo anterior
 *     continua nos anexos) e entra no histórico com quem editou;
 *   - mudar data/verba no bloco de dados atualiza também o registro no App (campanha:
 *     vai ao ar, fim, entrega das peças; planejamento: período, verba e datas de cada
 *     campanha/projeto) — assim documento e App não se desencontram.
 */
class DocumentEditService
{
    private const DATA_BLOCK = '~(<script[^>]*id=["\']nonna-dados["\'][^>]*>)(.*?)(</script>)~is';

    /** Documento atual: [attachment, html, dados do bloco (ou null)]. */
    public function load(MacroPlan|Project $owner): array
    {
        $attachment = $owner->mainDocument();
        abort_unless($attachment, 404, 'Este item ainda não tem documento.');

        $html = Storage::disk($attachment->disk)->get($attachment->disk_path) ?? '';

        return [$attachment, $html, $this->readData($html)];
    }

    public function readData(string $html): ?array
    {
        if (!preg_match(self::DATA_BLOCK, $html, $m)) {
            return null;
        }
        $data = json_decode(trim($m[2]), true);

        return is_array($data) ? $data : null;
    }

    /** Versões do documento (HTMLs anexados), mais recente primeiro. */
    public function versions(MacroPlan|Project $owner)
    {
        return $owner->attachments()
            ->where(fn ($q) => $q->where('mime_type', 'like', '%html%')->orWhere('filename', 'ilike', '%.htm%'))
            ->with('uploadedBy:id,name')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Salva a versão editada. $data = bloco de dados editado no painel (null = documento
     * sem bloco). Devolve a lista do que mudou no App (pra mensagem de sucesso).
     *
     * @return array<int, string>
     */
    public function save(MacroPlan|Project $owner, string $html, ?array $data, int $userId): array
    {
        if ($data !== null) {
            $html = $this->writeData($html, $data);
        }

        return DB::connection('pgsql')->transaction(function () use ($owner, $html, $data, $userId) {
            $previous   = $owner->mainDocument();
            $before     = $previous ? $this->readData(Storage::disk($previous->disk)->get($previous->disk_path) ?? '') : null;
            $attachment = $this->storeVersion($owner, $html, $previous?->filename, $userId);
            $owner->update(['document_attachment_id' => $attachment->id]);

            $changes = $data !== null ? $this->applyData($owner, $data, $before) : [];
            $this->log($owner, 'document_edited', $previous?->filename, $changes ? implode(' · ', $changes) : 'texto', $userId);

            return $changes;
        });
    }

    /** Volta pra uma versão anterior (vira a atual de novo) e reaplica os dados dela no App. */
    public function restore(MacroPlan|Project $owner, MacroPlanAttachment|ProjectAttachment $version, int $userId): array
    {
        $ownerKey = $owner instanceof MacroPlan ? 'macro_plan_id' : 'project_id';
        abort_unless($version->{$ownerKey} === $owner->id, 403);

        $html    = Storage::disk($version->disk)->get($version->disk_path) ?? '';
        $data    = $this->readData($html);
        $current = $owner->mainDocument();
        $before  = $current ? $this->readData(Storage::disk($current->disk)->get($current->disk_path) ?? '') : null;

        return DB::connection('pgsql')->transaction(function () use ($owner, $version, $data, $before, $userId) {
            $owner->update(['document_attachment_id' => $version->id]);
            $changes = $data !== null ? $this->applyData($owner, $data, $before) : [];
            $this->log($owner, 'document_restored', null, $version->created_at->format('d/m/Y H:i') . ($changes ? ' · ' . implode(' · ', $changes) : ''), $userId);

            return $changes;
        });
    }

    private function writeData(string $html, array $data): string
    {
        // "</" escapado: um texto com "</script>" dentro do JSON fecharia o bloco antes da hora.
        $json = str_replace('</', '<\/', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (preg_match(self::DATA_BLOCK, $html)) {
            return preg_replace_callback(self::DATA_BLOCK, fn ($m) => $m[1] . "\n" . $json . "\n" . $m[3], $html, 1);
        }

        return str_ireplace('</body>', '<script type="application/json" id="nonna-dados">' . "\n" . $json . "\n</script>\n</body>", $html);
    }

    private function storeVersion(MacroPlan|Project $owner, string $html, ?string $previousName, int $userId): MacroPlanAttachment|ProjectAttachment
    {
        $disk = config('filesystems.default', 'r2');
        $dir  = $owner instanceof MacroPlan ? "macroplans/{$owner->id}" : "projects/{$owner->id}";
        $path = $dir . '/' . Str::uuid() . '.html';
        Storage::disk($disk)->put($path, $html, ['ContentType' => 'text/html; charset=UTF-8']);

        $base = preg_replace('/(\s—\s(editado|v)\b.*)?\.html?$/iu', '', $previousName ?: 'documento');
        $attrs = [
            'filename'    => $base . ' — editado ' . now()->format('d-m H\hi') . '.html',
            'disk_path'   => $path,
            'disk'        => $disk,
            'mime_type'   => 'text/html',
            'size'        => strlen($html),
            'uploaded_by' => $userId,
        ];

        return $owner instanceof MacroPlan
            ? MacroPlanAttachment::create($attrs + ['macro_plan_id' => $owner->id])
            : ProjectAttachment::create($attrs + ['project_id' => $owner->id]);
    }

    /**
     * Dados do bloco → registro no App. Só leva o que mudou NO DOCUMENTO em relação à versão
     * anterior ($before): se alguém mudou a data direto no App e depois só corrigiu uma frase,
     * a data do App fica (não volta pra data velha do documento). Sem bloco anterior, leva tudo.
     * Os Observers registram cada mudança de data no histórico. Devolve o resumo legível.
     */
    private function applyData(MacroPlan|Project $owner, array $data, ?array $before = null): array
    {
        $changes = [];
        $touched = fn (array $now, ?array $old, string $key) => $old === null || ($now[$key] ?? null) !== ($old[$key] ?? null);

        if ($owner instanceof Project) {
            $map = ['inicio' => ['start_date', 'início'], 'fim' => ['end_date', 'término']];
            if ($owner->type === 'campanha') {
                $map['inicio'][1] = 'vai ao ar';
                $map['entrega_pecas'] = ['pieces_due_date', 'entrega das peças'];
            }
            $update = [];
            foreach ($map as $key => [$column, $label]) {
                $date = $this->date($data[$key] ?? null);
                if ($date && $touched($data, $before, $key) && $owner->{$column}?->toDateString() !== $date) {
                    $update[$column] = $date;
                    $changes[] = $label . ' ' . Carbon::parse($date)->format('d/m/Y');
                }
            }
            if ($update) {
                $owner->update($update);
            }

            return $changes;
        }

        // Planejamento: período, verba e as datas de cada campanha/projeto (pelo nome).
        $update = [];
        foreach (['periodo_inicio' => 'period_start', 'periodo_fim' => 'period_end'] as $key => $column) {
            $date = $this->date($data[$key] ?? null);
            if ($date && $touched($data, $before, $key) && $owner->{$column}?->toDateString() !== $date) {
                $update[$column] = $date;
            }
        }
        if ($update) {
            $owner->update($update);
            $changes[] = 'período ' . $owner->period_start->format('d/m/Y') . ' a ' . $owner->period_end->format('d/m/Y');
        }

        $verbaTouched = $before === null || (float) ($data['verba_mensal'] ?? 0) !== (float) ($before['verba_mensal'] ?? 0);
        if ($verbaTouched && isset($data['verba_mensal']) && is_numeric($data['verba_mensal'])) {
            $verba = (float) $data['verba_mensal'];
            $current = MacroPlanBudgetSync::parseMoney((string) ($owner->bloco1['verba_total'] ?? ''));
            if ($current === null || abs($current - $verba) >= 0.01) {
                $owner->update(['bloco1' => array_merge($owner->bloco1 ?? [], ['verba_total' => MacroPlanBudgetSync::storeFormat($verba)])]);
                MacroPlanBudgetSync::sync($owner, $verba, 'ao editar o documento do planejamento');
                $changes[] = 'verba R$ ' . number_format($verba, 0, ',', '.');
            }
        }

        $key = fn ($title) => Str::lower(Str::squish((string) $title));
        $projects = $owner->projects()->get()->keyBy(fn ($p) => $key($p->title));
        $oldItems = $before === null ? null : collect($before['itens'] ?? [])->keyBy(fn ($i) => $key($i['nome'] ?? ''));
        foreach ($data['itens'] ?? [] as $item) {
            $project = $projects->get($key($item['nome'] ?? ''));
            if (!$project) {
                continue;
            }
            $itemUpdate = [];
            foreach (['inicio' => 'start_date', 'fim' => 'end_date'] as $field => $column) {
                $date = $this->date($item[$field] ?? null);
                if ($date && $touched($item, $oldItems?->get($key($item['nome'] ?? ''), []), $field) && $project->{$column}?->toDateString() !== $date) {
                    $itemUpdate[$column] = $date;
                }
            }
            if ($itemUpdate) {
                $project->update($itemUpdate);
                $changes[] = $project->title . ' ' . collect($itemUpdate)->map(fn ($d) => Carbon::parse($d)->format('d/m/Y'))->implode(' → ');
            }
        }

        return $changes;
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function log(MacroPlan|Project $owner, string $action, ?string $from, ?string $to, int $userId): void
    {
        $to = $to ? Str::limit($to, 250) : null;
        $owner instanceof MacroPlan
            ? MacroPlanActivity::log($owner, $action, $from, $to, $userId)
            : ProjectActivity::log($owner, $action, $from, $to, $userId);
    }
}

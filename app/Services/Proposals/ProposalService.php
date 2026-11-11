<?php

namespace App\Services\Proposals;

use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Opportunity;
use App\Models\Proposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida das Propostas: montar (state da tela → itens do catálogo → ProposalCalculator),
 * salvar, enviar (congela + atualiza a oportunidade), liberar desconto, nova versão, aceitar,
 * recusar, e registrar os produtos no cliente quando a oportunidade é ganha.
 *
 * "state" (o que a tela manda):
 *  package_id, lines [{catalog_item_id, quantity, unit_price?}], ad_budget, discount, installments,
 *  group {on, name, mode: sum|fixed, monthly, one_time}
 */
class ProposalService
{
    public function __construct(private ProposalCalculator $calc) {}

    /** Resolve o state contra o catálogo. Erros = itens que não podem estar juntos. */
    public function resolve(array $state): array
    {
        $ids = collect($state['lines'] ?? [])->pluck('catalog_item_id')->push($state['package_id'] ?? null)->filter()->unique();
        $catalog = CatalogItem::whereIn('id', $ids)->get()->keyBy('id');
        $errors = [];

        $package = null;
        $pkg = !empty($state['package_id']) ? $catalog->get($state['package_id']) : null;
        if ($pkg && $pkg->isPackage()) {
            $package = $this->packageInput($pkg);
        }

        $lines = [];
        foreach (array_values($state['lines'] ?? []) as $row) {
            $item = $catalog->get($row['catalog_item_id'] ?? null);
            if (!$item || $item->isPackage()) {
                continue;
            }
            $list = (float) ($item->kind === 'monthly' ? $item->monthly_price : $item->one_time_price);
            $price = $item->price_is_starting && isset($row['unit_price']) && $row['unit_price'] !== ''
                ? max(0, (float) $row['unit_price']) : $list;

            if ($item->rule('requires_closed_package') && ($package['type'] ?? null) !== 'closed') {
                $errors[] = "\"{$item->name}\" só pode ser vendido junto de um pacote fechado (PlaNo C).";
            }
            if ($package && in_array($package['id'], $item->rule('not_in_packages', []), true)) {
                $errors[] = "\"{$item->name}\": " . ($item->rule('not_in_reason') ?: "não combina com {$package['name']}.");
            }

            $lines[] = [
                'id'         => $item->id,
                'name'       => $item->name,
                'kind'       => $item->kind,
                'unit_price' => $price,
                'list_price' => $list,
                'quantity'   => max(1, (int) ($row['quantity'] ?? 1)),
                'rules'      => $item->rules ?? [],
                'starting'   => $item->price_is_starting,
            ];
        }

        $group = $state['group'] ?? [];
        $installments = (int) ($state['installments'] ?? 1);
        $input = [
            'package'      => $package,
            'lines'        => $lines,
            'ad_budget'    => ($package['type'] ?? null) === 'closed' ? (float) ($state['ad_budget'] ?? 0) : 0,
            'discount'     => max(0, (float) ($state['discount'] ?? 0)),
            'installments' => in_array($installments, config('proposals.installments', [1, 3, 6]), true) ? $installments : 1,
            'group'        => [
                'on'       => !empty($group['on']),
                'name'     => trim((string) ($group['name'] ?? '')) ?: 'Pacote personalizado',
                'mode'     => ($group['mode'] ?? 'sum') === 'fixed' ? 'fixed' : 'sum',
                'monthly'  => (float) ($group['monthly'] ?? 0),
                'one_time' => (float) ($group['one_time'] ?? 0),
            ],
        ];

        return ['input' => $input, 'errors' => $errors];
    }

    /** Cálculo completo pra tela: valores + sugestão de upgrade + avisos. */
    public function preview(array $state): array
    {
        ['input' => $input, 'errors' => $errors] = $this->resolve($state);
        $calc = $this->calc->calculate($input);

        $upgrade = null;
        $nextId = $input['package']['rules']['upgrade_to'] ?? null;
        if (($input['package']['type'] ?? null) === 'closed' && $nextId && !$errors) {
            $next = CatalogItem::active()->find($nextId);
            if ($next && $next->isClosedPackage()) {
                $upgrade = $this->calc->upgrade($input, $calc, $this->packageInput($next));
            }
        }

        $warnings = [];
        $d = $calc['deliverables'];
        if ($d && $calc['captured_videos'] > $d['videos']) {
            $missing = $calc['captured_videos'] - $d['videos'];
            $warnings[] = "A captação rende até {$calc['captured_videos']} vídeos, mas o pacote só tem {$d['videos']} por mês. Acrescente {$missing} vídeo(s) extra(s) ou combine com o cliente.";
        }

        return ['input' => $input, 'calc' => $calc, 'upgrade' => $upgrade, 'errors' => $errors, 'warnings' => $warnings];
    }

    public function save(Proposal $proposal, Opportunity $opportunity, array $state, ?string $title, ?int $userId): Proposal
    {
        if ($proposal->exists && !$proposal->isEditable()) {
            throw ValidationException::withMessages(['proposal' => 'Esta proposta já foi enviada. Crie uma nova versão para mudar.']);
        }

        $p = $this->preview($state);
        if ($p['errors']) {
            throw ValidationException::withMessages(['lines' => $p['errors']]);
        }
        if (!$p['input']['package'] && !$p['input']['lines']) {
            throw ValidationException::withMessages(['lines' => 'Adicione um pacote ou pelo menos um item.']);
        }
        if ($p['calc']['approval'] === 'blocked') {
            $max = config('proposals.max_discount_percent');
            throw ValidationException::withMessages(['discount' => "O desconto passa de {$max}% abaixo da tabela, o que não é permitido."]);
        }

        $in = $p['input'];
        $calc = $p['calc'];

        return DB::transaction(function () use ($proposal, $opportunity, $in, $calc, $p, $title, $userId) {
            $proposal->fill([
                'opportunity_id'   => $opportunity->id,
                'title'            => trim((string) $title) ?: $this->autoTitle($in),
                'status'           => 'draft',
                'package_id'       => $in['package']['id'] ?? null,
                'package_snapshot' => $in['package'],
                'ad_budget'        => ($in['package']['type'] ?? null) === 'closed' ? $in['ad_budget'] : null,
                'discount_percent' => $in['discount'],
                'installments'     => $in['installments'],
                'group_on'         => $calc['grouped'],
                'group_name'       => $calc['grouped'] ? $in['group']['name'] : null,
                'group_mode'       => $calc['grouped'] ? $in['group']['mode'] : null,
                'group_monthly'    => $calc['grouped'] ? $calc['group_monthly'] : null,
                'group_one_time'   => $calc['grouped'] ? $calc['group_one_time'] : null,
                'monthly_total'    => $calc['monthly'],
                'one_time_total'   => $calc['one_time'],
                'contract_total'   => $calc['total'],
                'calculation'      => ['calc' => $calc, 'upgrade' => $p['upgrade'], 'warnings' => $p['warnings']],
            ]);
            if (!$proposal->exists) {
                $proposal->version = (int) Proposal::where('opportunity_id', $opportunity->id)->max('version') + 1;
                $proposal->created_by = $userId;
            }
            $proposal->save();

            $proposal->items()->delete();
            foreach ($in['lines'] as $i => $l) {
                $proposal->items()->create([
                    'catalog_item_id' => $l['id'],
                    'name'            => $l['name'],
                    'kind'            => $l['kind'],
                    'quantity'        => $l['quantity'],
                    'list_price'      => $l['list_price'],
                    'unit_price'      => $l['unit_price'],
                    'subtotal'        => $calc['line_totals'][$i],
                    'rules'           => $l['rules'] ?: null,
                    'sort_order'      => $i,
                ]);
            }

            return $proposal;
        });
    }

    /** Envia (congela). Se o desconto pede liberação que ainda não foi dada, vai pra "aguardando liberação". */
    public function send(Proposal $proposal): string
    {
        if (!$proposal->isEditable()) {
            throw ValidationException::withMessages(['proposal' => 'Esta proposta já foi enviada.']);
        }

        $calc = $proposal->calculation['calc'] ?? [];
        $effective = (float) ($calc['effective_discount'] ?? 0);
        if (($calc['approval'] ?? 'free') === 'needs_release'
            && ($proposal->released_percent === null || $effective > (float) $proposal->released_percent)) {
            $proposal->update(['status' => 'pending_release']);
            return 'pending_release';
        }

        DB::transaction(function () use ($proposal) {
            $proposal->update(['status' => 'sent', 'sent_at' => now()]);
            $this->syncOpportunity($proposal, advanceStage: true);
        });

        return 'sent';
    }

    public function release(Proposal $proposal, int $userId, ?string $note): void
    {
        $proposal->update([
            'status'           => 'draft',
            'released_percent' => (float) ($proposal->calculation['calc']['effective_discount'] ?? $proposal->discount_percent),
            'released_by'      => $userId,
            'released_at'      => now(),
            'release_note'     => $note,
        ]);
    }

    /** Cópia editável da proposta (enviada ou recusada) com o próximo número de versão. */
    public function newVersion(Proposal $proposal, ?int $userId): Proposal
    {
        return DB::transaction(function () use ($proposal, $userId) {
            $copy = $proposal->replicate(['status', 'sent_at', 'accepted_at', 'rejected_at', 'released_percent', 'released_by', 'released_at', 'release_note']);
            $copy->fill([
                'parent_id'  => $proposal->id,
                'status'     => 'draft',
                'version'    => (int) Proposal::where('opportunity_id', $proposal->opportunity_id)->max('version') + 1,
                'created_by' => $userId,
            ]);
            $copy->save();
            foreach ($proposal->items as $item) {
                $copy->items()->create($item->only(['catalog_item_id', 'name', 'kind', 'quantity', 'list_price', 'unit_price', 'subtotal', 'rules', 'sort_order']));
            }
            return $copy;
        });
    }

    /** Aceita: as outras propostas abertas da mesma oportunidade viram recusadas. */
    public function accept(Proposal $proposal): void
    {
        if ($proposal->status !== 'sent') {
            throw ValidationException::withMessages(['proposal' => 'Só uma proposta enviada pode ser marcada como aceita.']);
        }

        DB::transaction(function () use ($proposal) {
            $proposal->update(['status' => 'accepted', 'accepted_at' => now()]);
            Proposal::where('opportunity_id', $proposal->opportunity_id)
                ->where('id', '!=', $proposal->id)
                ->whereIn('status', ['draft', 'pending_release', 'sent'])
                ->update(['status' => 'rejected', 'rejected_at' => now()]);
            $this->syncOpportunity($proposal, advanceStage: false);
        });
    }

    public function reject(Proposal $proposal): void
    {
        $proposal->update(['status' => 'rejected', 'rejected_at' => now()]);
    }

    /**
     * Quando a oportunidade é ganha, o que estava na proposta aceita fica registrado no cliente.
     * Pacote personalizado vira uma linha com o preço do conjunto + os itens dentro dele sem preço.
     */
    public function registerClientProducts(Opportunity $opportunity, Client $client): int
    {
        $proposal = Proposal::with('items')->where('opportunity_id', $opportunity->id)->where('status', 'accepted')->latest('accepted_at')->first();
        if (!$proposal || ClientProduct::where('proposal_id', $proposal->id)->exists()) {
            return 0;
        }

        $base = ['organization_id' => $proposal->organization_id, 'client_id' => $client->id, 'proposal_id' => $proposal->id, 'status' => 'active', 'started_at' => now()->toDateString()];
        $rows = [];
        if ($snap = $proposal->package_snapshot) {
            $rows[] = $base + ['catalog_item_id' => $snap['id'], 'name' => $snap['name'], 'kind' => 'package', 'quantity' => 1,
                'monthly_price' => $snap['monthly'] ?: null, 'one_time_price' => $snap['one_time'] ?: null];
        }
        if ($proposal->group_on) {
            $rows[] = $base + ['catalog_item_id' => null, 'name' => $proposal->group_name, 'kind' => 'package', 'quantity' => 1,
                'monthly_price' => (float) $proposal->group_monthly ?: null, 'one_time_price' => (float) $proposal->group_one_time ?: null];
        }
        foreach ($proposal->items as $item) {
            $priced = !$proposal->group_on;
            $rows[] = $base + ['catalog_item_id' => $item->catalog_item_id, 'name' => $item->name, 'kind' => $item->kind, 'quantity' => $item->quantity,
                'monthly_price' => $priced && $item->kind === 'monthly' ? $item->unit_price : null,
                'one_time_price' => $priced && $item->kind === 'one_time' ? $item->unit_price : null];
        }
        foreach ($rows as $row) {
            ClientProduct::create($row);
        }

        return count($rows);
    }

    public function packageInput(CatalogItem $pkg): array
    {
        $rules = $pkg->rules ?? [];
        if ($pkg->isOpenPackage()) {
            $pkg->loadMissing('components');
            $rules['includes'] = $pkg->components->map(fn ($c) => ($c->pivot->quantity > 1 ? $c->pivot->quantity . '× ' : '') . $c->name)->all();
        }

        return [
            'id'       => $pkg->id,
            'name'     => $pkg->name,
            'type'     => $pkg->package_type,
            'monthly'  => (float) $pkg->monthly_price,
            'one_time' => (float) $pkg->one_time_price,
            'rules'    => $rules,
        ];
    }

    private function syncOpportunity(Proposal $proposal, bool $advanceStage): void
    {
        $opp = $proposal->opportunity;
        $data = [
            'proposed_fee'       => $proposal->monthly_total,
            'proposed_ad_budget' => $proposal->ad_budget,
            'contract_months'    => (int) config('proposals.contract_months', 12),
        ];
        if ($advanceStage && in_array($opp->stage, ['novo_lead', 'qualificacao', 'diagnostico_reuniao'], true)) {
            $data['stage'] = 'proposta_enviada';
        }
        $opp->update($data);
    }

    private function autoTitle(array $in): string
    {
        if ($in['package']) {
            $extra = count($in['lines']) ? ' + ' . count($in['lines']) . ' item(ns)' : '';
            return $in['package']['name'] . $extra;
        }
        if ($in['group']['on'] && count($in['lines']) >= 2) {
            return $in['group']['name'];
        }
        return collect($in['lines'])->pluck('name')->take(2)->implode(' + ') . (count($in['lines']) > 2 ? ' e mais' : '');
    }
}

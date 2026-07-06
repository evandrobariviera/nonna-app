<?php

namespace App\Services;

use App\Models\Project;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Faz o parse de um HTML de macroplanejamento gerado pela skill de referência
 * (ver .claude/docs/templates/planejamento_farmagnus (2).html) e extrai os
 * dados equivalentes a MacroPlan.bloco1/bloco2 e Project (incluindo o brief
 * criativo de campanha). Parser determinístico via DOMXPath — depende da
 * estrutura de classes/ids que a skill sempre gera da mesma forma.
 */
class MacroPlanHtmlImporter
{
    private DOMXPath $xpath;
    private array $warnings = [];

    public function parse(string $html): array
    {
        $this->warnings = [];

        // Normaliza <br> para espaço antes de extrair texto (senão vira tudo colado)
        $html = preg_replace('/<br\s*\/?>/i', ' ', $html);

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);

        $titleNode = $dom->getElementsByTagName('title')->item(0);
        $rawTitle = $titleNode ? trim($titleNode->textContent) : '';
        $title = trim(explode('|', $rawTitle)[0]) ?: 'Macroplanejamento importado';

        return [
            'client_name' => $this->classText($dom->documentElement, 'cli-name'),
            'title'       => $title,
            'bloco1'      => $this->parseBloco1(),
            'bloco2'      => $this->parseBloco2(),
            'projects'    => $this->parseProjectsAndCampaigns(),
            'warnings'    => $this->warnings,
        ];
    }

    // ── Helpers de leitura ──────────────────────────────────────────────

    private function page(string $id): ?DOMNode
    {
        $nodes = $this->xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' page ') and @id='{$id}']");
        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    private function classText(?DOMNode $context, string $class): string
    {
        if (!$context) return '';
        $nodes = $this->xpath->query(".//*[contains(concat(' ',normalize-space(@class),' '),' {$class} ')]", $context);
        return ($nodes && $nodes->length > 0) ? trim($nodes->item(0)->textContent) : '';
    }

    private function classTextAll(?DOMNode $context, string $class): array
    {
        if (!$context) return [];
        $nodes = $this->xpath->query(".//*[contains(concat(' ',normalize-space(@class),' '),' {$class} ')]", $context);
        $out = [];
        if ($nodes) {
            foreach ($nodes as $n) {
                $out[] = trim($n->textContent);
            }
        }
        return $out;
    }

    private function classNodes(?DOMNode $context, string $class): array
    {
        if (!$context) return [];
        $nodes = $this->xpath->query(".//*[contains(concat(' ',normalize-space(@class),' '),' {$class} ')]", $context);
        return $nodes ? iterator_to_array($nodes) : [];
    }

    private function sectionNode(DOMNode $page, string $titleSubstring): ?DOMNode
    {
        $nodes = $this->xpath->query(".//*[contains(@class,'section-title') and contains(text(), '{$titleSubstring}')]", $page);
        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    private function nextElementSibling(DOMNode $node): ?DOMNode
    {
        $sibling = $node->nextSibling;
        while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE) {
            $sibling = $sibling->nextSibling;
        }
        return $sibling;
    }

    // Texto de um node, excluindo o texto de um filho-rótulo (ex: ".tb-label" dentro de ".tb-field")
    private function textExcludingLabel(?DOMNode $node, string $labelClass): string
    {
        if (!$node) return '';
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && str_contains($child->getAttribute('class'), $labelClass)) {
                continue;
            }
            $text .= $child->textContent;
        }
        return trim($text);
    }

    private function cardAfterTitle(DOMNode $page, string $titleSubstring): string
    {
        $titleNode = $this->sectionNode($page, $titleSubstring);
        if (!$titleNode) return '';
        $sibling = $this->nextElementSibling($titleNode);
        if (!$sibling || !str_contains($sibling->getAttribute('class'), 'card')) return '';
        return $this->classText($sibling, 'card-body') ?: trim($sibling->textContent);
    }

    private function paragraphsAfterTitle(DOMNode $page, string $titleSubstring): array
    {
        $titleNode = $this->sectionNode($page, $titleSubstring);
        if (!$titleNode) return [];
        $paras = [];
        $sibling = $titleNode->nextSibling;
        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                if (!str_contains($sibling->getAttribute('class'), 'prose')) break;
                $paras[] = trim($sibling->textContent);
            }
            $sibling = $sibling->nextSibling;
        }
        return $paras;
    }

    // ── Bloco 01 ─────────────────────────────────────────────────────────

    private function parseBloco1(): array
    {
        $page = $this->page('b01');
        if (!$page) {
            $this->warnings[] = 'Bloco 01 (Visão Geral e Metas) não encontrado no HTML.';
            return [];
        }

        $verbaTotal = $metaPct = $googlePct = '';
        foreach ($this->classNodes($page, 'verba-box') as $box) {
            $label = mb_strtolower($this->classText($box, 'verba-label'));
            $val = $this->classText($box, 'verba-val');
            if (str_contains($label, 'total'))        $verbaTotal = $val;
            elseif (str_contains($label, 'meta'))     $metaPct = $val;
            elseif (str_contains($label, 'google'))   $googlePct = $val;
        }

        $kpis = [];
        foreach ($this->classNodes($page, 'kpi-card') as $card) {
            $kpis[] = [
                'label' => $this->classText($card, 'kpi-label'),
                'title' => $this->classText($card, 'kpi-title'),
                'desc'  => $this->classText($card, 'kpi-desc'),
            ];
        }

        return [
            'foco_principal'    => $this->cardAfterTitle($page, 'Foco Principal'),
            'contexto_anterior' => $this->cardAfterTitle($page, 'Ponto de Partida'),
            'verba_total'       => preg_replace('/[^0-9,.]/', '', $verbaTotal),
            'meta_pct'          => preg_replace('/[^0-9]/', '', $metaPct),
            'google_pct'        => preg_replace('/[^0-9]/', '', $googlePct),
            'verba_obs'         => $this->textExcludingLabel($this->classNodes($page, 'tb-field')[0] ?? null, 'tb-label'),
            'kpis'              => $kpis,
        ];
    }

    // ── Bloco 02 ─────────────────────────────────────────────────────────

    private function parseBloco2(): array
    {
        $page = $this->page('b02');
        if (!$page) {
            $this->warnings[] = 'Bloco 02 (Contexto e Estratégia) não encontrado no HTML.';
            return [];
        }

        $pilares = [];
        foreach ($this->classNodes($page, 'pilar') as $p) {
            $pilares[] = [
                'nome' => $this->classText($p, 'pilar-name'),
                'desc' => $this->classText($p, 'pilar-desc'),
            ];
        }

        $antesBox = $this->classNodes($page, 'antes')[0] ?? null;
        $agoraBox = $this->classNodes($page, 'agora')[0] ?? null;

        $linhaTempo = [];
        foreach ($this->classNodes($page, 'tl-col') as $col) {
            $itens = [];
            foreach ($this->classNodes($col, 'tl-item') as $itemNode) {
                $tipo = str_contains($itemNode->getAttribute('class'), 'proj') ? 'projeto' : 'geral';
                $itens[] = ['texto' => trim($itemNode->textContent), 'tipo' => $tipo];
            }
            $linhaTempo[] = ['mes' => $this->classText($col, 'tl-month'), 'itens' => $itens];
        }
        if (!empty($linhaTempo)) {
            $this->warnings[] = 'Linha do Tempo importada — o template não distingue "campanha" de "geral" visualmente, revise os tipos de cada item.';
        }

        return [
            'desafio_atual'    => $this->cardAfterTitle($page, 'O Desafio Atual'),
            'o_que_muda_antes' => $antesBox ? $this->classText($antesBox, 'aa-text') : '',
            'o_que_muda_agora' => $agoraBox ? $this->classText($agoraBox, 'aa-text') : '',
            'estrategia'       => $this->cardAfterTitle($page, 'A Nossa Estratégia'),
            'pilares'          => $pilares,
            'linha_tempo'      => $linhaTempo,
        ];
    }

    // ── Projetos e Campanhas ─────────────────────────────────────────────

    private function parseProjectsAndCampaigns(): array
    {
        $pageNodes = $this->xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' page ')]");
        $projects = [];

        foreach ($pageNodes as $page) {
            $id = $page->getAttribute('id');
            if (!preg_match('/^(p|c)\d+$/', $id)) continue;

            $type = str_starts_with($id, 'c') ? 'campanha' : 'projeto';
            $title = trim($this->xpath->query('.//h1', $page)->item(0)?->textContent ?? '');

            $briefings = [];
            $disciplines = [];
            foreach ($this->classNodes($page, 'disc-block') as $db) {
                $label = $this->classText($db, 'disc-label');
                $text = $this->classText($db, 'disc-text');
                $key = $this->matchDiscipline($label);
                if (!$key) {
                    // fallback: usa o cabeçalho "Disciplina · X" mais próximo antes deste bloco, dentro da mesma página
                    $anchorNodes = $this->xpath->query(
                        "preceding::*[contains(concat(' ',normalize-space(@class),' '),' crea-anchor ') and ancestor::div[@id='{$id}']][1]",
                        $db
                    );
                    if ($anchorNodes && $anchorNodes->length > 0) {
                        $key = $this->matchDiscipline($anchorNodes->item(0)->textContent);
                    }
                }
                if ($key) {
                    $briefings[$key] = trim(($briefings[$key] ?? '') . (isset($briefings[$key]) ? "\n\n" : '') . $text);
                    $disciplines[$key] = true;
                } else {
                    $this->warnings[] = "\"{$title}\": bloco \"{$label}\" não reconhecido automaticamente — texto: \"{$text}\"";
                }
            }

            $contentIdeas = [];
            foreach ($this->classNodes($page, 'idea-card') as $ic) {
                $badgeNodes = $this->classNodes($ic, 'badge');
                $badgeClass = $badgeNodes[0]?->getAttribute('class') ?? '';
                $formato = 'outro';
                foreach (['video', 'card', 'carrossel', 'stories', 'reels'] as $f) {
                    if (str_contains($badgeClass, "badge-{$f}")) { $formato = $f; break; }
                }
                $contentIdeas[] = [
                    'formato' => $formato,
                    'titulo'  => $this->classText($ic, 'idea-title'),
                    'texto'   => $this->classText($ic, 'idea-desc'),
                ];
            }

            $data = [
                'title'         => $title,
                'type'          => $type,
                'status'        => $this->matchStatus($this->classText($page, 'status-pill')),
                'objective'     => $this->classText($page, 'obj-box') ?: $this->classText($page, 'subtitle'),
                'tags'          => $this->classTextAll($page, 't'),
                'disciplines'   => array_keys($disciplines),
                'content_ideas' => $contentIdeas,
                'brief_status'  => 'basico',
            ];
            foreach ($briefings as $key => $text) {
                $data["briefing_{$key}"] = $text;
            }

            if ($type === 'campanha') {
                $wrapNodes = $this->classNodes($page, 'campanha-conteudo');
                $estado = $wrapNodes[0]?->getAttribute('data-estado') ?? '';
                $data['brief_status'] = ($estado === 'detalhada') ? 'detalhado' : 'basico';

                if ($data['brief_status'] === 'detalhado') {
                    $data['big_idea_titulo']        = $this->classText($page, 'bi-idea');
                    $data['big_idea_manifesto']      = $this->classText($page, 'bi-manifesto');
                    $data['territorio_alternativo']  = $this->classText($page, 'terr-desc');
                    $data['racional_estrategico']    = implode("\n\n", $this->paragraphsAfterTitle($page, 'Racional Estratégico'));

                    $comTitleNode = $this->sectionNode($page, 'Linha de Comunicação');
                    $tomChips = [];
                    if ($comTitleNode) {
                        $chipsBox = $this->nextElementSibling($comTitleNode);
                        if ($chipsBox && str_contains($chipsBox->getAttribute('class'), 'chips')) {
                            $tomChips = $this->classTextAll($chipsBox, 'chip');
                        }
                    }
                    $data['tom_comunicacao'] = $tomChips;
                    $data['frase_voz']       = trim($this->classText($page, 'voice-example'), '" ');
                    $data['assinatura']      = $this->classText($page, 'ass-line');

                    $angulos = [];
                    foreach ($this->classNodes($page, 'angle') as $an) {
                        $angulos[] = ['titulo' => $this->classText($an, 'angle-num'), 'texto' => $this->classText($an, 'angle-text')];
                    }
                    $data['angulos'] = $angulos;

                    $mecanica = [];
                    foreach ($this->classNodes($page, 'step') as $sn) {
                        $mecanica[] = ['titulo' => $this->classText($sn, 'step-title'), 'texto' => $this->classText($sn, 'step-desc')];
                    }
                    $data['mecanica'] = $mecanica;

                    $data['ponto_atencao'] = $this->classText($page, 'alert-warn');

                    $refs = [];
                    foreach ($this->classNodes($page, 'mood-card') as $mn) {
                        $refs[] = ['label' => $this->classText($mn, 'mood-label'), 'texto' => $this->classText($mn, 'mood-text')];
                    }
                    $data['referencias_visuais'] = $refs;

                    $pecas = [];
                    $pieceListNodes = $this->classNodes($page, 'piece-list');
                    if (isset($pieceListNodes[0])) {
                        $liNodes = $this->xpath->query('.//li', $pieceListNodes[0]);
                        foreach ($liNodes as $li) {
                            $pecas[] = ['nome' => $this->classText($li, 'piece-name'), 'direcionamento' => $this->classText($li, 'piece-dir')];
                        }
                    }
                    $data['pecas'] = $pecas;
                }
            }

            $taskCount = count($this->classNodes($page, 'task-item'));
            if ($taskCount > 0) {
                $this->warnings[] = "\"{$title}\": {$taskCount} tarefa(s) sugerida(s) no documento não foram importadas — adicione manualmente pelo Kanban do projeto.";
            }

            $projects[] = $data;
        }

        return $projects;
    }

    private function matchStatus(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        $map = [
            'planejado'  => 'draft',
            'rascunho'   => 'draft',
            'contínuo'   => 'continua',
            'continuo'   => 'continua',
            'ativo'      => 'active',
            'concluído'  => 'completed',
            'concluido'  => 'completed',
            'cancelado'  => 'cancelled',
        ];
        foreach ($map as $needle => $status) {
            if (str_contains($raw, $needle)) return $status;
        }
        return 'draft';
    }

    private function matchDiscipline(string $label): ?string
    {
        $label = mb_strtolower(trim($label));
        if ($label === '') return null;
        foreach (Project::$disciplines as $key => $fullLabel) {
            $parts = array_map('trim', explode('/', mb_strtolower($fullLabel)));
            foreach ($parts as $part) {
                if ($part !== '' && str_contains($label, $part)) {
                    return $key;
                }
            }
            if (str_contains($label, $key)) {
                return $key;
            }
        }
        return null;
    }
}

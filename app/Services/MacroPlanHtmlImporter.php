<?php

namespace App\Services;

use App\Models\MacroPlan;
use App\Models\Project;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Faz o parse de um HTML de macroplanejamento gerado pela skill de referência
 * (ver .claude/docs/templates/planejamento_gomes.html) e extrai os dados
 * equivalentes a MacroPlan.bloco1/bloco2/bloco4/bloco5 e Project (incluindo o
 * brief criativo de campanha). Parser determinístico via DOMXPath — depende
 * da estrutura de classes/ids que a skill sempre gera da mesma forma.
 *
 * Formatos antigos (ex: planejamento_farmagnus, com `.cli-name`, `#b01`/`#b02`,
 * projetos `p1`/`p2` sem prefixo `pj`) não são mais suportados por este parser.
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

        $capaPage = $this->page('p00');
        $searchScope = $capaPage ?? $dom->documentElement;

        $clientName = $this->capaCellValue($searchScope, 'Cliente');
        $title = $this->extractTitle($dom, $capaPage, $clientName);
        $responsibleName = $this->capaCellValue($searchScope, 'Responsável Nonna');
        $version = $this->capaCellValue($searchScope, 'Versão do Documento');

        $disciplines = [];
        if ($capaPage) {
            $chipRows = $this->classNodes($capaPage, 'chip-row');
            foreach ($this->classTextAll($chipRows[0] ?? null, 'chip') as $chipLabel) {
                $key = $this->matchMacroPlanDiscipline($chipLabel);
                if ($key) $disciplines[] = $key;
            }
        }
        $disciplines = array_values(array_unique($disciplines));

        return [
            'client_name'      => $clientName,
            'title'            => $title,
            'responsible_name' => $responsibleName,
            'version'          => $version,
            'disciplines'      => $disciplines,
            'bloco1'           => $this->parseBloco1(),
            'bloco2'           => $this->parseBloco2(),
            'bloco4'           => $this->parseBloco4(),
            'bloco5'           => $this->parseBloco5(),
            'projects'         => $this->parseProjectsAndCampaigns(),
            'warnings'         => $this->warnings,
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
        // "contains(@class,'section-title')" pega tanto .section-title quanto
        // .subsection-title (o segundo contém o primeiro como substring) — proposital.
        $nodes = $this->xpath->query(".//*[contains(@class,'section-title') and contains(text(), '{$titleSubstring}')]", $page);
        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    // Acha um .card-title cujo texto contém $titleSubstring e lê o .card-body
    // que vive dentro do mesmo .card (título e corpo são irmãos, ambos filhos
    // do .card — não um heading solto antes de um .card irmão).
    private function cardBodyByTitle(DOMNode $page, string $titleSubstring): string
    {
        $nodes = $this->xpath->query(".//*[contains(concat(' ',normalize-space(@class),' '),' card-title ') and contains(text(), '{$titleSubstring}')]", $page);
        if (!$nodes || $nodes->length === 0) return '';
        $titleNode = $nodes->item(0);
        $parent = $titleNode->parentNode;
        return $parent ? $this->classText($parent, 'card-body') : '';
    }

    // Coleta o texto de todos os .card-body que vêm logo depois de um
    // .subsection-title (ex: "Racional Estratégico"), parando no primeiro
    // elemento que não seja .card-body.
    private function cardBodiesAfterTitle(DOMNode $page, string $titleSubstring): array
    {
        $titleNode = $this->sectionNode($page, $titleSubstring);
        if (!$titleNode) return [];
        $bodies = [];
        $sibling = $titleNode->nextSibling;
        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                if (!str_contains($sibling->getAttribute('class'), 'card-body')) break;
                $bodies[] = trim($sibling->textContent);
            }
            $sibling = $sibling->nextSibling;
        }
        return $bodies;
    }

    // Lê o valor de uma célula da grade da Capa (.capa-cell) a partir do
    // texto exato do seu .capa-cell-label (ex: "Cliente", "Versão do Documento").
    private function capaCellValue(DOMNode $context, string $label): string
    {
        $nodes = $this->xpath->query(".//*[contains(concat(' ',normalize-space(@class),' '),' capa-cell-label ') and normalize-space(text())='{$label}']", $context);
        if (!$nodes || $nodes->length === 0) return '';
        $parent = $nodes->item(0)->parentNode;
        return $parent ? $this->classText($parent, 'capa-cell-val') : '';
    }

    private function extractTitle(DOMDocument $dom, ?DOMNode $capaPage, string $clientName): string
    {
        if ($capaPage) {
            $capaTitle = $this->classText($capaPage, 'capa-title');
            if ($capaTitle !== '') return $capaTitle;

            $heroNodes = $this->classNodes($capaPage, 'capa-hero');
            if (isset($heroNodes[0])) {
                $pNodes = $this->xpath->query('.//p', $heroNodes[0]);
                if ($pNodes && $pNodes->length > 0) {
                    $tagline = trim($pNodes->item(0)->textContent);
                    if ($tagline !== '') return $tagline;
                }
            }
        }

        $titleNode = $dom->getElementsByTagName('title')->item(0);
        $rawTitle = $titleNode ? trim($titleNode->textContent) : '';
        $legacyTitle = trim(explode('|', $rawTitle)[0]);
        if ($legacyTitle !== '' && $legacyTitle !== $rawTitle) return $legacyTitle;

        return $clientName ?: 'Macroplanejamento importado';
    }

    // ── Bloco 01 ─────────────────────────────────────────────────────────

    private function parseBloco1(): array
    {
        $page = $this->page('p01');
        if (!$page) {
            $this->warnings[] = 'Bloco 01 (Visão Geral e Metas) não encontrado no HTML.';
            return [];
        }

        $verbaTotal = $metaPct = $googlePct = '';
        $verbaNotes = [];
        foreach ($this->classNodes($page, 'verba-box') as $box) {
            $label = mb_strtolower($this->classText($box, 'verba-label'));
            $val = $this->classText($box, 'verba-val');
            $note = $this->classText($box, 'verba-note');
            if ($note !== '') $verbaNotes[] = $note;
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
            'foco_principal'    => $this->cardBodyByTitle($page, 'Foco Principal'),
            'contexto_anterior' => $this->cardBodyByTitle($page, 'Ponto de Partida'),
            'verba_total'       => preg_replace('/[^0-9,.]/', '', $verbaTotal),
            'meta_pct'          => preg_replace('/[^0-9]/', '', $metaPct),
            'google_pct'        => preg_replace('/[^0-9]/', '', $googlePct),
            'verba_obs'         => implode(' ', $verbaNotes),
            'kpis'              => $kpis,
        ];
    }

    // ── Bloco 02 ─────────────────────────────────────────────────────────

    private function parseBloco2(): array
    {
        $page = $this->page('p02');
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

        $linhaTempo = [];
        foreach ($this->classNodes($page, 'tl-col') as $col) {
            $itens = [];
            $itemNodes = $this->classNodes($col, 'tl-item');
            if (!empty($itemNodes)) {
                foreach ($itemNodes as $itemNode) {
                    $tipo = str_contains($itemNode->getAttribute('class'), 'proj') ? 'projeto' : 'geral';
                    $itens[] = ['texto' => trim($itemNode->textContent), 'tipo' => $tipo];
                }
            } else {
                // Formato sem lista de itens — só um rótulo + descrição por mês.
                $label = $this->classText($col, 'tl-label');
                $desc  = $this->classText($col, 'tl-desc');
                $texto = trim($label . ($label && $desc ? ' — ' : '') . $desc);
                if ($texto !== '') {
                    $itens[] = ['texto' => $texto, 'tipo' => 'geral'];
                }
            }
            $linhaTempo[] = ['mes' => $this->classText($col, 'tl-month'), 'itens' => $itens];
        }
        if (!empty($linhaTempo)) {
            $this->warnings[] = 'Linha do Tempo importada — o template não distingue "campanha" de "geral" visualmente, revise os tipos de cada item.';
        }

        return [
            'desafio_atual'    => $this->cardBodyByTitle($page, 'O Desafio Atual'),
            'o_que_muda_antes' => $this->cardBodyByTitle($page, 'Antes'),
            'o_que_muda_agora' => $this->cardBodyByTitle($page, 'Agora'),
            'estrategia'       => $this->cardBodyByTitle($page, 'A Nossa Estratégia'),
            'pilares'          => $pilares,
            'linha_tempo'      => $linhaTempo,
        ];
    }

    // ── Bloco 04 (Rotina & Demandas Contínuas) ──────────────────────────

    private function parseBloco4(): array
    {
        $page = $this->page('p04');
        if (!$page) return [];

        return [
            'trafego_continuo' => $this->cardBodyByTitle($page, 'Tráfego Contínuo'),
            'social_continuo'  => $this->cardBodyByTitle($page, 'Social Media Contínuo'),
            'outras_demandas'  => $this->cardBodyByTitle($page, 'Outras Demandas Recorrentes'),
        ];
    }

    // ── Bloco 05 (Infraestrutura, Acessos & Responsabilidades) ──────────

    private function parseBloco5(): array
    {
        $page = $this->page('p05');
        if (!$page) return [];

        $acessos   = $this->cardBodyByTitle($page, 'Acessos e Integrações');
        $materiais = $this->cardBodyByTitle($page, 'Materiais e Insumos');
        $obs       = $this->cardBodyByTitle($page, 'Observações e Pendências');

        $reunioes = [];
        foreach ($this->classNodes($page, 'check-item') as $item) {
            $nome = $this->classText($item, 'check-name');
            $desc = $this->classText($item, 'check-desc');
            if ($nome !== '') {
                $reunioes[] = $desc !== '' ? "{$nome} — {$desc}" : $nome;
            }
        }

        $respostas = [];
        foreach ($this->classNodes($page, 'resp-block') as $block) {
            $blockTitle = $this->classText($block, 'resp-block-title');
            $itens = [];
            foreach ($this->classNodes($block, 'resp-item') as $item) {
                $texto = trim($item->textContent);
                if ($texto !== '') $itens[] = $texto;
            }
            if (!empty($itens)) {
                $respostas[] = $blockTitle . ":\n" . implode("\n", array_map(fn ($t) => "- {$t}", $itens));
            }
        }

        $pendencias = trim(implode("\n\n", array_filter([
            $obs,
            !empty($reunioes) ? "Reuniões/Alinhamentos Pendentes:\n" . implode("\n", array_map(fn ($r) => "- {$r}", $reunioes)) : '',
            implode("\n\n", $respostas),
        ])));

        return [
            'acessos'    => $acessos,
            'materiais'  => $materiais,
            'pendencias' => $pendencias,
        ];
    }

    // ── Projetos e Campanhas ─────────────────────────────────────────────

    private function parseProjectsAndCampaigns(): array
    {
        $pageNodes = $this->xpath->query("//div[contains(concat(' ',normalize-space(@class),' '),' page ')]");
        $projects = [];

        foreach ($pageNodes as $page) {
            $id = $page->getAttribute('id');
            if (!preg_match('/^(pj|c)\d+$/', $id)) continue;

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
            foreach ($this->classNodes($page, 'content-idea') as $ic) {
                $formato = 'outro';
                $spanNodes = $this->xpath->query('.//span', $ic);
                if ($spanNodes) {
                    foreach ($spanNodes as $span) {
                        $spanClass = $span->getAttribute('class');
                        foreach (['video', 'card', 'carrossel', 'stories', 'reels'] as $f) {
                            if (str_contains($spanClass, "badge-{$f}")) { $formato = $f; break 2; }
                        }
                    }
                }
                $h4Node = $this->xpath->query('.//h4', $ic)->item(0);
                $pNode = $this->xpath->query('.//p', $ic)->item(0);
                $contentIdeas[] = [
                    'formato' => $formato,
                    'titulo'  => $h4Node ? trim($h4Node->textContent) : '',
                    'texto'   => $pNode ? trim($pNode->textContent) : '',
                ];
            }

            $data = [
                'title'         => $title,
                'type'          => $type,
                'status'        => $this->matchStatus($this->classText($page, 'status-pill')),
                'objective'     => $this->classText($page, 'obj-box') ?: $this->classText($page, 'subtitle'),
                'tags'          => $this->classTextAll($page, 'tag'),
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
                    $data['big_idea_titulo']    = $this->classText($page, 'bigidea-phrase');
                    $data['big_idea_manifesto'] = $this->classText($page, 'bigidea-manifesto');

                    $territorioAlt = '';
                    $terrCards = $this->classNodes($page, 'territorio-card');
                    $altCard = null;
                    foreach ($terrCards as $tc) {
                        if (str_contains(mb_strtolower($this->classText($tc, 'territorio-rota')), 'alternativ')) {
                            $altCard = $tc;
                            break;
                        }
                    }
                    if (!$altCard && count($terrCards) > 1) {
                        $altCard = $terrCards[1];
                    }
                    if ($altCard) {
                        $territorioAlt = trim($this->classText($altCard, 'territorio-title') . "\n\n" . $this->classText($altCard, 'territorio-desc'));
                    }
                    $data['territorio_alternativo'] = $territorioAlt;

                    $data['racional_estrategico'] = implode("\n\n", $this->cardBodiesAfterTitle($page, 'Racional Estratégico'));

                    $tomChipsRows = $this->classNodes($page, 'tomdevoz-row');
                    $data['tom_comunicacao'] = $this->classTextAll($tomChipsRows[0] ?? null, 'tomdevoz-chip');
                    $data['frase_voz'] = trim($this->classText($page, 'tomdevoz-frase'), '" ');

                    $assinatura = '';
                    foreach ($this->classNodes($page, 'card-body') as $cb) {
                        $text = trim($cb->textContent);
                        if (stripos($text, 'Assinatura:') !== false) {
                            $parts = preg_split('/Assinatura:\s*/ui', $text, 2);
                            $assinatura = trim($parts[1] ?? '', '" ');
                            break;
                        }
                    }
                    $data['assinatura'] = $assinatura;

                    $angulos = [];
                    foreach ($this->classNodes($page, 'angle') as $an) {
                        $angulos[] = ['titulo' => $this->classText($an, 'angle-num'), 'texto' => $this->classText($an, 'angle-text')];
                    }
                    $data['angulos'] = $angulos;

                    $mecanica = [];
                    foreach ($this->classNodes($page, 'mecanica-step') as $sn) {
                        $mecanica[] = ['titulo' => $this->classText($sn, 'mecanica-fase-label'), 'texto' => $this->classText($sn, 'mecanica-text')];
                    }
                    $data['mecanica'] = $mecanica;

                    $data['ponto_atencao'] = $this->classText($page, 'mecanica-atencao');

                    $refs = [];
                    foreach ($this->classNodes($page, 'moodcard') as $mn) {
                        $refs[] = ['label' => $this->classText($mn, 'moodcard-label'), 'texto' => $this->classText($mn, 'moodcard-text')];
                    }
                    $data['referencias_visuais'] = $refs;

                    $pecas = [];
                    $pecasListNodes = $this->classNodes($page, 'pecas-list');
                    if (isset($pecasListNodes[0])) {
                        foreach ($this->classNodes($pecasListNodes[0], 'peca-item') as $pi) {
                            $pecas[] = ['nome' => $this->classText($pi, 'peca-nome'), 'direcionamento' => $this->classText($pi, 'peca-desc')];
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
            'planejado'  => 'em_planejamento',
            'rascunho'   => 'em_planejamento',
            'backlog'    => 'em_planejamento',
            'a definir'  => 'em_planejamento',
            'a iniciar'  => 'em_planejamento',
            'aprovação'  => 'aprovacao',
            'aprovacao'  => 'aprovacao',
            'execução'   => 'em_execucao',
            'execucao'   => 'em_execucao',
            'andamento'  => 'em_execucao',
            'ativo'      => 'em_execucao',
            'contínuo'   => 'em_execucao',
            'continuo'   => 'em_execucao',
            'stand by'   => 'stand_by',
            'standby'    => 'stand_by',
            'pausad'     => 'stand_by',
            'concluído'  => 'concluido',
            'concluido'  => 'concluido',
            'cancelado'  => 'cancelado',
        ];
        foreach ($map as $needle => $status) {
            if (str_contains($raw, $needle)) return $status;
        }
        return 'em_planejamento';
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

    private function matchMacroPlanDiscipline(string $label): ?string
    {
        $label = mb_strtolower(trim($label));
        if ($label === '') return null;
        foreach (MacroPlan::$disciplineOptions as $key => $fullLabel) {
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

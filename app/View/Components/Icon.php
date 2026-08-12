<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * <x-icon name="pencil" class="h-4 w-4" style="color:var(--muted)" />
 *
 * Ícones Lucide vendorizados em resources/icons/lucide/*.svg (não lidos de
 * node_modules — o Dockerfile deste projeto nunca copia node_modules pro
 * container de produção, só public/build; ler de lá quebraria em produção
 * mesmo funcionando em dev local). Pra adicionar um ícone novo: baixe o
 * .svg de node_modules/lucide-static/icons/{name}.svg (depois de
 * `npm install`) e copie pra resources/icons/lucide/{name}.svg.
 *
 * currentColor já vem embutido no SVG — a cor do ícone é sempre controlada
 * por CSS (color:...) no elemento ou em algum ancestral, igual aos SVGs à
 * mão que já existiam no app antes desse componente.
 *
 * Renderiza via view (resources/views/components/icon.blade.php) em vez de
 * devolver HtmlString direto do render() — um HtmlString/Htmlable faz o
 * Blade pular a etapa de popular $this->attributes antes do render()
 * rodar (a ordem real é: resolveView() chama render() primeiro, só depois
 * vem o withAttributes()), então `$this->attributes` sempre chegava null
 * ali dentro. Passando por uma view de verdade, `$attributes` chega pronto
 * e já filtrado (sem name/size/stroke) no momento em que o Blade a expõe.
 */
class Icon extends Component
{
    private static array $rawCache = [];

    public string $svg;

    public function __construct(
        public string $name,
        public string $size = '20',
        public string $stroke = '1.75',
    ) {
        $svg = self::$rawCache[$name] ??= $this->readSvg($name);

        $svg = str_replace(
            ['width="24"', 'height="24"', 'stroke-width="2"'],
            ['width="' . $size . '"', 'height="' . $size . '"', 'stroke-width="' . $stroke . '"'],
            $svg
        );

        // Remove o comentário de license do topo do arquivo e a classe
        // "lucide lucide-{name}" fixa que vem no SVG original — quem usa o
        // componente controla a classe via $attributes, não o arquivo fonte.
        $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg, 1);
        $svg = preg_replace('/\s*class="lucide[^"]*"/', '', $svg, 1);

        $this->svg = trim($svg);
    }

    public function render(): View
    {
        return view('components.icon');
    }

    private function readSvg(string $name): string
    {
        $path = resource_path("icons/lucide/{$name}.svg");

        abort_unless(
            file_exists($path),
            500,
            "Ícone Lucide '{$name}' não encontrado em resources/icons/lucide/ — vendorize antes de usar (ver comentário no topo de app/View/Components/Icon.php)."
        );

        return file_get_contents($path);
    }
}

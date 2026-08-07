// Helper compartilhado pras células "Monday fill" (Status/Situação/Responsável/Executor)
// aplicarem a mudança via PATCH sem recarregar a página. Sem resposta do servidor: cada opção
// do dropdown já carrega tudo que a UI precisa pra se atualizar sozinha (label/cor/nome/avatar),
// então só precisamos saber se o PATCH deu certo.
export function registerInlinePatch() {
    // Importante: os controllers precisam responder com JSON (não redirect()->back())
    // quando a chamada é AJAX — um 302 sem isso faria o fetch() seguir o redirect
    // mantendo o verbo PATCH (só GET/HEAD/POST viram GET automaticamente), e a página de
    // destino (ex: /filas) não aceita PATCH => 405.
    window.inlinePatch = async function (url, data) {
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data),
            });
            return res.ok;
        } catch (e) {
            return false;
        }
    };

    // applyFill(url, data, onSuccess) — dispara o PATCH e só roda o callback (que atualiza o
    // estado reativo local e fecha o dropdown) se a resposta vier OK. Em falha, avisa e deixa o
    // valor exibido como estava (sem "desfazer" nada, porque nada foi aplicado ainda).
    //
    // Depois do sucesso, também pede um refresh da lista (live-filter.js) em segundo plano —
    // a atualização local já mostra o novo valor na hora, mas quando a tela está agrupada por
    // Status/Executor/Responsável a tarefa só troca de grupo visualmente depois que o servidor
    // reagrupa de novo (grupo é calculado lá, não dá pra mover a linha certo só no JS). Sem
    // form[data-live-filter] na tela (ex: outro contexto futuro), é um no-op silencioso.
    window.applyFill = function (url, data, onSuccess) {
        window.inlinePatch(url, data).then((ok) => {
            if (ok) {
                onSuccess();
                window.refreshLiveFilter?.();
            } else {
                alert('Falha ao salvar. Atualize a página e tente novamente.');
            }
        });
    };
}

// Helper compartilhado pras células "Monday fill" (Status/Situação/Responsável/Executor)
// aplicarem a mudança via PATCH sem recarregar a página. Sem resposta do servidor: cada opção
// do dropdown já carrega tudo que a UI precisa pra se atualizar sozinha (label/cor/nome/avatar),
// então só precisamos saber se o PATCH deu certo.
export function registerInlinePatch() {
    // Importante: os controllers precisam responder com JSON (não redirect()->back())
    // quando a chamada é AJAX — um 302 sem isso faria o fetch() seguir o redirect
    // mantendo o verbo PATCH (só GET/HEAD/POST viram GET automaticamente), e a página de
    // destino (ex: /filas) não aceita PATCH => 405.
    // Retorna { ok, message } em vez de só um booleano — message vem do corpo JSON da
    // resposta quando o servidor bloqueia a mudança (ex: trava de carga do executor), pra
    // quem chama poder mostrar o motivo real em vez de um aviso genérico.
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
            // data = corpo JSON da resposta (quando há), pra quem precisa do valor recalculado
            // pelo servidor (ex: pontos de sprint da tarefa).
            if (res.ok) {
                let data = null;
                try { data = await res.json(); } catch (e) { /* sem corpo JSON */ }
                return { ok: true, message: null, data };
            }
            let json = null;
            try { json = await res.json(); } catch (e) { /* resposta não era JSON */ }

            // Trava de entrega (Revisão Interna): abre a janela de retorno. Entregou = o
            // servidor já mudou o status, então pra quem chamou é sucesso; desistiu =
            // cancelled, sem alerta (a pessoa sabe que cancelou).
            const delivered = await window.handleDeliveryRequired?.(json);
            if (delivered === true) return { ok: true, message: null, data: null };
            if (delivered === false) return { ok: false, message: null, cancelled: true };

            return { ok: false, message: json?.message ?? null };
        } catch (e) {
            return { ok: false, message: null };
        }
    };

    // applyFill(url, data, onSuccess) — dispara o PATCH e só roda o callback (que atualiza o
    // estado reativo local e fecha o dropdown) se a resposta vier OK. Em falha, avisa e deixa o
    // valor exibido como estava (sem "desfazer" nada, porque nada foi aplicado ainda).
    //
    // Não recarrega a lista sozinho — quem decide isso é quem chama (ver
    // refreshLiveFilterIfGroupedBy em monday-fill.js), porque só faz sentido re-buscar o HTML
    // (e com isso fechar/piscar a tabela inteira) quando o campo alterado é justamente o
    // agrupamento atual da tela; do contrário a atualização local já basta.
    window.applyFill = function (url, data, onSuccess) {
        window.inlinePatch(url, data).then(({ ok, message, cancelled }) => {
            if (ok) {
                onSuccess();
            } else if (!cancelled) {
                alert(message || 'Falha ao salvar. Atualize a página e tente novamente.');
            }
        });
    };
}

// Helper compartilhado pras células "Monday fill" (Status/Situação/Responsável/Executor)
// aplicarem a mudança via PATCH sem recarregar a página. Sem resposta do servidor: cada opção
// do dropdown já carrega tudo que a UI precisa pra se atualizar sozinha (label/cor/nome/avatar),
// então só precisamos saber se o PATCH deu certo.
export function registerInlinePatch() {
    window.inlinePatch = async function (url, data) {
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
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
    window.applyFill = function (url, data, onSuccess) {
        window.inlinePatch(url, data).then((ok) => {
            if (ok) {
                onSuccess();
            } else {
                alert('Falha ao salvar. Atualize a página e tente novamente.');
            }
        });
    };
}

# Treinamento Interno — Nonna App

> Rascunho iniciado em 2026-10-16. Continuar na semana seguinte.

## Contexto

O time **já usa o sistema no dia a dia** (processo, tarefas, fila). O treinamento é **aprofundado e complementar**, com foco principal na **cultura de cadastro no cliente**: setup, cadastro de cliente, senhas, contas de anúncio, liberação de portal. Começar pelo **Cliente como entidade e suas nuances**.

Princípio: não ensinar "onde clica", e sim **por que cadastrar certo e o que quebra quando não cadastra**. A ficha do cliente alimenta automaticamente quem recebe aprovação, quem recebe cobrança, o que o portal mostra e de onde vêm os números de anúncio. **Cadastro errado vira erro silencioso lá na frente.**

## Estrutura geral (rascunho inicial, ainda não validado)

- Trilha 0 — Base comum (navegação, tarefa, fila/sprint, reuniões/ATA, aprovações, ajuda). **Rebaixada:** o time já domina.
- Trilhas por setor: Comercial, Responsável de Conta/Estratégia, Criação, Tráfego, Web/Tech, Operações/Governança, Financeiro/Backoffice, Heads/Admin.
- Fora de propósito (ainda não liberado): Assistente de Lançamento, Cérebro de Campanhas, painel Financeiro com dados [DEMO].

## Bloco 1 — O Cliente como entidade (em construção, começar por aqui)

### 1.1 Cliente × Contato
- Cliente = empresa. Contato = pessoa. A mesma pessoa pode estar ligada a vários clientes: **buscar/vincular antes de cadastrar novo** (evita duplicata).
- Status Lead / Ativo / Inativo. Nunca excluir cliente com histórico: vira Inativo. Excluir só serve pra duplicata (`blockingAssociations()` vazio).
- Apelido = só uso interno (telas da equipe, nome do planejamento). **Nunca vai pra nada que o cliente vê nem pra documento fiscal.** Razão social = oficial.
- Cadastro público por link tokenizado (`/cadastro/{token}`): o cliente preenche os dados da empresa e do responsável.

### 1.2 Contatos — quem recebe o quê ⚠️ maior nuance
- Papel no cliente: Sócio/Fundador, Gestor/CEO, Financeiro, Ponto de Contato, Marketing, Operacional, Outros.
- Contato principal (um por cliente).
- **Comunicações** (`client_contact_subscriptions`): tipo × canal (WhatsApp/e-mail). Tipos: boas-vindas do onboarding, chamado aberto/concluído, lembrete de reunião, aprovação, lead capturado, financeiro (notas de anúncio), cobrança, pesquisa de satisfação, offboarding, feedback de campanhas.
- Analogia: é a lista de distribuição de correspondência do cliente. Sem ninguém marcado em "Aprovação", a aprovação não chega em ninguém.
- Exercício: auditar 3 clientes reais. Quem recebe cada mensagem hoje? Está certo?

### 1.3 Senhas (`client_credentials`)
- Plataformas: Site/WordPress, Hospedagem, Instagram, Facebook, LinkedIn, TikTok, YouTube, Google Meu Negócio, Google Ads, Meta BM, E-mail, Outros.
- "Solicitar senhas": gera um link tokenizado com prazo pro cliente preencher (`client_credential_requests`).
- Regra de cultura: **senha mora só no App.** Não pode ficar no WhatsApp, no grupo, no Drive nem em comentário de tarefa. Recebeu por outro canal? Cadastra no App e apaga de lá.

### 1.4 Links (`client_links`)
- Drive, Contrato, Briefing, Planilha, Dossiê, Apresentação, Outro. "Onde está o Drive?" não deveria virar pergunta no chat.

### 1.5 Contas de Anúncio (`client_ad_accounts`) ⚠️ segunda maior nuance
- **Senha ≠ Conta de anúncio.** A senha guarda o login. A conta guarda o **ID**, que é o que faz o sync puxar campanhas e números. O Meta BM aparece nas duas abas, cada uma com função diferente.
- Por conta: status (Ativo/Pausado/Suspenso), responsável, forma de pagamento (PIX/Cartão/Boleto), saldo (API ou calculado), automação de orçamento, metas de custo por resultado e ROAS.
- O que quebra: ID errado faz o sync puxar nada ou puxar campanha de outro cliente. Conta sem responsável deixa o aviso de saldo sem ninguém pra receber.

### 1.6 Portal do Cliente
- O acesso é liberado **pra um contato vinculado com e-mail** (o login é o e-mail). A ordem é obrigatória: vincular o contato e só depois liberar o portal.
- Senha: por link (o contato cria) ou manual. Se o contato já tem acesso por outro cliente, deixa em branco, porque é a mesma senha.
- Módulos do Portal (ex.: Central de Leads): só libera depois que a venda fechou.

### 1.7 Setup de produção (aba Geral)
- Serviços contratados, verba mensal em mídia, Responsável Criativo (`creative_lead_id`), cota de produção por tipo de peça (`production_quota`).
- Alimenta a carga, a Sprint e o "quanto esse cliente tem direito por mês". Cota vazia faz a equipe produzir sem régua.

### Checklist "Cliente bem cadastrado"
- [ ] Apelido e razão social preenchidos e corretos
- [ ] Status correto
- [ ] Contato principal definido, e todo contato com papel definido
- [ ] Cada tipo de mensagem automática usado tem pelo menos 1 destinatário
- [ ] Todas as senhas cadastradas, com zero senha fora do App
- [ ] Link do Drive e do contrato cadastrados
- [ ] Toda conta de anúncio ativa tem ID conferido, responsável e forma de pagamento
- [ ] Portal liberado pra quem aprova
- [ ] Serviços, Responsável Criativo e cota de produção preenchidos

Dinâmica sugerida: cada responsável de conta **audita a própria carteira** com o checklist (treina corrigindo cliente real).

## Pendências pra próxima sessão

1. **Decisão do Evandro:** quem é o dono de cada parte do cadastro? (sugestão: Contatos/Portal = Responsável de Conta; Senhas/Contas = Tráfego/Setup; Cobrança = Alisson)
2. Validar se o Bloco 1 está completo. Abas ainda não cobertas: Contratos, Leads (GTM/UUID), Onboarding, Atendimento, Dossiês, Briefing.
3. Escolher o formato: vídeo, aula ao vivo ou os dois.
4. Próximos passos possíveis: (a) escrever o bloco como artigos na Central de Ajuda (`HelpArticle`, por `category`); (b) relatório ou tela de "saúde do cadastro" aplicando o checklist em todos os clientes.
5. Depois do Bloco 1: definir os próximos blocos.

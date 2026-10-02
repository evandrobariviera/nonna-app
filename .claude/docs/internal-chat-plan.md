# Plano — Chat Interno do Nonna App

Estudo feito em **2026-10-02**.

**Status (2026-10-02): Etapa 1 implementada** — widget flutuante, tela `/chat`, conversa individual, canais de Setor, anexos, não lidas, silenciar, prévia + som. 41 verificações automatizadas passando (script em transação com rollback contra o banco). Falta: teste visual no navegador e deploy.

Decisões da Fase 0 tomadas com os padrões sugeridos (Evandro pode mudar): nome "Chat"; freelancer entra sozinho no canal do Setor; conversas de quem sai ficam como histórico; chat fora do Monitor de Trabalho; editar/apagar e grupos ficam pra Etapa 3.

Arquivos principais: `app/Services/Chat/ChatService.php`, `app/Http/Controllers/ChatController.php`, `app/Policies/ChatConversationPolicy.php`, `resources/js/chat-widget.js`, `resources/views/chat/` (`_widget`, `_panel`, `index`), migration `2026_10_02_100000_create_chat_tables.php`.

## Por que construir o nosso (e não Discord / Mattermost)

- Hoje a conversa interna está espalhada entre **WhatsApp** e **Merge** (CRM de atendimento) — nenhum dos dois serve bem pra uso interno.
- **Discord** não embute dentro do app (só widget de leitura ou robô espelhando) → a equipe continuaria fora do app.
- **Mattermost / Rocket.Chat** = mais um sistema pra manter no Hetzner (banco, atualizações, segurança), sem ligação com tarefas/clientes.
- **Nosso próprio** reaproveita o que já existe: usuários, Setores (`sectors` + `sector_user`), sino de notificações (`resources/js/browser-notify.js`), editor/sanitizador de texto (`RichTextSanitizer`), upload pro R2, miniatura/lightbox de anexos, avatar (`<x-user-avatar>`), modal de confirmação. E permite ligar conversa ↔ tarefa ↔ cliente, coisa que nenhuma opção pronta faz.

## Escopo definido com o Evandro

| Item | Decisão |
|---|---|
| Quem usa | Equipe interna. Freelancer entra como usuário normal do app |
| Tipos de conversa | Individual (1:1) e por Setor. Grupos personalizados numa etapa seguinte |
| Conteúdo | Texto + imagens + arquivos. **Sem** chamada de voz/vídeo |
| Celular | Não é prioridade agora — só notificação **dentro do app** (sem push com celular bloqueado) |
| Privacidade | **Ninguém lê conversa da qual não participa** — nem dono, nem admin, nem superadmin. Cada um vê só as suas individuais e os grupos/setores de que faz parte |

---

## Fase 0 — Decisões pendentes (antes de codar)

- [ ] **D1. Nome no menu:** "Chat", "Conversas" ou "Mensagens"?
- [ ] **D2. Editar/apagar mensagem:** pode? Sugestão: editar e apagar a própria mensagem por até 15 min; depois disso fica (mostra "mensagem apagada" em vez de sumir sem rastro)
- [ ] **D3. Freelancer nos canais de Setor:** entra automaticamente no canal do setor em que for cadastrado, ou só por convite?
- [ ] **D4. Grupos personalizados (Etapa 3):** qualquer um cria, ou só Admin/Gestor?
- [ ] **D5. Quando alguém sai da empresa:** as conversas individuais dele somem pra quem conversava com ele, ou ficam como histórico (só leitura)? Sugestão: ficam como histórico
- [ ] **D6. Mensagens no Monitor de Trabalho:** confirmar que **não** aparecem (nem contagem) — sugestão: não aparecer, coerente com a regra de privacidade

---

## Etapa 1 — Base (MVP)

Objetivo: dá pra largar o WhatsApp interno. Sem mudar infraestrutura.

### Banco de dados (migrations novas, tabelas `Tenantable` com `organization_id` no `$fillable` — ver [[feedback-organization-id-fillable-bug]])
- [ ] `chat_conversations` — `id` uuid, `organization_id`, `type` (`direct` | `sector` | `group`), `sector_id` (nullable), `name` (nullable), `created_by`, `last_message_at`, timestamps
- [ ] `chat_participants` — `conversation_id`, `user_id`, `last_read_at`, `muted` (bool), `joined_at`; único por (conversa, usuário)
- [ ] `chat_messages` — `id` uuid, `conversation_id`, `user_id`, `body` (HTML sanitizado), `reply_to_id` (nullable, uso na Etapa 3), `edited_at`, `deleted_at` (soft delete), timestamps; índice (`conversation_id`, `created_at`)
- [ ] `chat_message_attachments` — `message_id`, `disk`, `disk_path`, `filename`, `mime`, `size`

### Regras
- [ ] **Conversa individual:** uma só por par de pessoas (abrir de novo reaproveita a existente)
- [ ] **Canal de Setor:** criado automaticamente pra cada Setor; participantes sincronizados com os membros do Setor (entrou/saiu do setor → entra/sai do canal) — sincronizar no ponto onde o vínculo setor↔usuário é salvo + comando pra criar os canais dos setores que já existem
- [ ] **Privacidade (crítico):** toda rota de chat confere "o usuário logado é participante desta conversa?" → senão 403. Nenhum atalho pra admin/superadmin. Centralizar numa Policy (`ChatConversationPolicy`) e cobrir com teste automatizado
- [ ] Conteúdo da mensagem passa pelo `RichTextSanitizer` antes de salvar (evita código injetado)
- [ ] Limite de envio (throttle) por usuário pra evitar flood

### Interface principal: **widget flutuante** (decisão do Evandro, 2026-10-02)
Estilo ferramenta de atendimento de site — o chat acompanha a pessoa em qualquer tela do app.

- [ ] **Fechado:** botão redondo no canto inferior direito, com bolinha de não lidas. Mensagem nova → prévia rápida ("Fulano: …") saindo do botão + som
- [ ] **Aberto:** painel ~380px × ~560px. Primeiro mostra a lista de conversas (Setores primeiro, depois individuais por última mensagem, contador de não lidas, busca por nome, "Nova conversa"); clicou → abre a conversa no mesmo painel com botão "voltar"
- [ ] **Conversa:** mensagens agrupadas por dia, avatar/nome/hora; mensagens antigas carregam ao rolar pra cima
- [ ] **Caixa de envio:** Enter envia, Shift+Enter quebra linha; colar imagem (Ctrl+V) e arrastar arquivo pra dentro do painel
- [ ] **Anexos:** imagem em miniatura com lightbox (reaproveitar o existente); arquivo como cartão com nome/tamanho e download
- [ ] Componente único incluído no `layouts/app.blade.php` (Alpine store `chat`), presente em todas as telas internas — **nunca** no Portal do Cliente nem em páginas públicas

#### ⚠️ Cuidado principal: o app recarrega a página a cada navegação
Sem tratamento, o widget fecharia e perderia tudo a cada clique. Por isso:
- [ ] Guardar em `sessionStorage` (com try/catch): aberto/minimizado, conversa aberta, rascunho não enviado por conversa, posição de rolagem
- [ ] Ao carregar a página, reabrir no mesmo estado imediatamente (sem animação de abertura) e buscar só o que chegou de novo
- [ ] Rascunho não pode se perder — salvar enquanto digita

#### Convivência com o resto da tela
- [ ] Não colidir com o painel lateral de pré-visualização (`layouts/app.blade.php`, `fixed inset-y-0 right-0 z-40`, ocupa metade da tela): definir camadas (z-index) e, com o painel aberto, recolher o widget pro botão
- [ ] Não cobrir a barra inferior do celular nem os dropdowns do topo
- [ ] **Celular:** o widget abre em tela cheia (não flutua)

#### Tela cheia `/chat` (complemento do widget)
- [ ] Botão "expandir" no widget abre a mesma conversa em `/chat` (lista à esquerda, conversa à direita) — pra conversa longa, ver muitos arquivos e (Etapa 3) buscar
- [ ] Reaproveita os mesmos endpoints e partials do widget (sem duplicar lógica)
- [ ] Na tela `/chat` o widget flutuante fica escondido

#### Futuro (se sentirem falta)
- [ ] Várias conversas abertas lado a lado no rodapé (estilo Messenger antigo)

### Anexos
- [ ] Upload pro R2 privado, mesmo padrão dos outros anexos (link temporário assinado), pasta `chat/{conversation_id}/...`, `ContentType` com charset (ver [[project-upload-charset-mojibake-fix]])
- [ ] Limite sugerido: 50 MB por arquivo (acima disso, avisar pra usar Drive)
- [ ] Download/visualização só pra participante da conversa (mesma Policy)

### "Tempo real" da Etapa 1 = consulta frequente (polling)
- [ ] Conversa aberta: busca mensagens novas a cada **3 s** (`?after=<última>`)
- [ ] Widget fechado: busca o total de não lidas a cada **15 s** (endpoint leve, separado do sino — o sino usa 30 s, lento demais pra chat)
- [ ] Pausar consultas quando a aba do navegador estiver escondida
- [ ] Nova mensagem fora da conversa aberta: prévia no botão do widget + som + notificação do navegador quando a aba estiver em segundo plano (reaproveitar `browser-notify.js`); respeitar conversa silenciada
- [ ] **Não** gravar cada mensagem em `internal_notifications` (o sino ficaria entupido) — não lidas vêm de `chat_participants.last_read_at`

### Testes
- [ ] Não participante recebe 403 em: ver conversa, buscar mensagens, enviar, baixar anexo
- [ ] Admin/owner/superadmin **também** recebe 403 em conversa alheia
- [ ] Entrar/sair de Setor sincroniza participação no canal
- [ ] HTML malicioso na mensagem é limpo

---

## Etapa 2 — Tempo real de verdade

Fazer quando a Etapa 1 estiver em uso e fizer sentido.

- [ ] **Laravel Reverb** (servidor de tempo real oficial do Laravel) como novo processo no `docker/supervisord.conf`, dentro do mesmo container
- [ ] Rota de websocket no Traefik (labels no `docker-compose.prod.yml`) + ajuste no `docker/nginx.conf`
- [ ] Canais privados autenticados por conversa (mesma regra de participante)
- [ ] Mensagem chega instantânea; "fulano está digitando…"; bolinha de online
- [ ] Polling da Etapa 1 continua como reserva se o websocket cair
- ⚠️ Mudança de infraestrutura + dependência nova (exige rebuild da imagem) — testar com cuidado por causa do histórico de instabilidade de deploy (ver [[project-deploy-status]], [[feedback-swarm-overlay-network-incident]])

---

## Etapa 3 — Integração com o resto do app (o grande diferencial)

- [ ] **@menção** de pessoa (destaca e notifica mesmo em conversa silenciada)
- [ ] **Responder** mensagem específica (citação)
- [ ] **Cartão de tarefa/cliente:** colar link de tarefa, cliente, projeto ou reunião vira um cartão com status/responsável
- [ ] **"Transformar em tarefa"** a partir de uma mensagem (usa o `TaskDraftService`, ponto único de criação — ver [[project-task-launcher-assistant]])
- [ ] **Grupos personalizados** (conforme D4) — ex: "Squad Cliente X"
- [ ] **Busca** dentro das conversas
- [ ] Fixar mensagem; reações (👍)

## Etapa 4 — Opcional / futuro

- [ ] Agente de IA dentro da conversa (resumir conversa, sugerir tarefa) — ver [[project-ai-agents-vision]]
- [ ] Notificação no celular com tela bloqueada (PWA + Web Push)
- [ ] Freelancer com acesso restrito só ao chat

---

## Fora do escopo (decidido)

- Chamada de voz/vídeo
- Integração com Discord, WhatsApp ou Merge
- Leitura de conversas alheias por qualquer papel

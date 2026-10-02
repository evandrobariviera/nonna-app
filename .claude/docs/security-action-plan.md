# Plano de Ação — Segurança do Nonna App

Auditoria feita em **2026-10-02**. Nada foi alterado ainda — este é o roteiro pra executarmos.
Ordem sugerida: Fase 0 (decisões) → Fase 1 (urgente) → Fase 2 (reforço) → Fase 3 (verificações).

---

## Fase 0 — Decisões do Evandro (antes de começar)

- [ ] **D1. Reset Operacional** (`/superadmin/reset-operacional`): remover a tela de vez *(recomendado — migração do ClickUp já acabou)* ou manter protegida com senha + digitar "RESETAR"?
- [ ] **D2. Quem vê o quê:** hoje qualquer "Membro" acessa Financeiro, valores de Contratos e Senhas de clientes (aba Credenciais). Definir quais papéis podem ver:
  - Financeiro: ______
  - Contratos (valores): ______
  - Senhas/Credenciais de clientes: ______
- [ ] **D3. Cookie de sessão:** o app usa (ou vai usar) subdomínio por organização (`org.nonnaagenciadigital.com.br`)? Se **não**, restringimos o cookie só a `app.nonnaagenciadigital.com.br`.
- [ ] **D4. Verificação em 2 etapas (2FA) pra equipe:** fazer agora, depois, ou não fazer?

---

## Fase 1 — Urgente

### 1. Fechar o banco de produção pra internet *(infra — Evandro no painel, Claude passa o passo a passo)*
Hoje o `.env` local conecta em `178.156.137.95:5432` (IP público). Produção usa o IP privado `10.0.0.3`.
- [ ] Firewall Hetzner: porta 5432 liberada **só** pro servidor do app (rede privada) + IP fixo do Evandro — ou bloquear tudo e acessar via túnel SSH
- [ ] Trocar a senha do usuário do Postgres depois de fechar (atualizar no Portainer e no `.env` local)
- [ ] Passar a exigir criptografia: `DB_SSLMODE=require` (hoje é `prefer` — aceita conexão sem criptografia)
- [ ] Testar que o app em produção continua conectando

### 2. Imports do ClickUp: recusar quando não houver senha *(código)*
Hoje, se `IMPORT_SECRET` estiver vazio, os endpoints ficam abertos pra qualquer um.
- [ ] Trocar `if ($secret && !hash_equals(...))` por "se não tem segredo configurado OU não bate → 401" em:
  - `app/Http/Controllers/Api/ClickupImportController.php` (2 pontos: `import` e `syncFields`)
  - `app/Http/Controllers/Api/ClickupMacroPlanImportController.php`
  - `app/Http/Controllers/Api/ClickupProjectImportController.php`
- [ ] Conferir no Portainer se `IMPORT_SECRET` está preenchido (senão o n8n para de funcionar após o fix)

### 3. Webhook do WhatsApp (uazapi): exigir token *(código)*
- [ ] `app/Services/Whatsapp/UazapiMessageIngestor.php` — se `token` vier vazio, rejeitar antes da busca (hoje `where('external_id', null)` vira "busca integração sem token")

### 4. Atualizar bibliotecas com falhas conhecidas *(código + rebuild da imagem)*
- [ ] `composer update guzzlehttp/guzzle guzzlehttp/psr7 league/commonmark league/flysystem laravel/framework --with-dependencies`
- [ ] `npm audit fix` (tiptap — 1 alta, ~24 moderadas)
- [ ] Rodar testes + testar editor de texto (comentários, briefing) e uma sync Meta/Google depois
- [ ] Deploy (precisa rebuild da imagem — dependência nova de Composer)

### 5. Reset Operacional *(conforme decisão D1)*
- [ ] Remover rota + controller + view **ou** proteger com `password.confirm` + confirmação digitada
- Arquivo: `app/Http/Controllers/SuperAdmin/ResetOperacionalController.php`, rotas em `routes/web.php` (bloco SuperAdmin)

---

## Fase 2 — Reforço

### 6. Permissões por papel *(código — depende de D2)*
- [ ] Middleware `org.admin:...` (ou checagem equivalente) nas rotas de Financeiro, Contratos e Credenciais
- [ ] Esconder os itens correspondentes na sidebar (`resources/views/layouts/sidebar-links.blade.php`) e a aba Credenciais na ficha do cliente pra quem não tem acesso

### 7. Cookie de sessão *(config — depende de D3)*
- [ ] Portainer: `SESSION_SECURE_COOKIE=true`
- [ ] Se D3 = não usa subdomínio: `SESSION_DOMAIN` = `app.nonnaagenciadigital.com.br` (atenção: todo mundo será deslogado uma vez)

### 8. Cabeçalhos de segurança no nginx *(código — `docker/nginx.conf`, nos 2 blocos `server`)*
- [ ] `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- [ ] `X-Frame-Options: SAMEORIGIN` (impede o app de ser embutido em site falso)
- [ ] `X-Content-Type-Options: nosniff`
- [ ] `Referrer-Policy: strict-origin-when-cross-origin`
- [ ] `server_tokens off;`

### 9. Tokens da API (n8n)
- [ ] Definir expiração dos tokens Sanctum (`config/sanctum.php` → `expiration`) **ou** rotina de rotação manual (ex: a cada 6 meses)
- [ ] Gerar token novo, trocar no n8n, apagar o antigo em Configurações → API
- Motivo: o token dá acesso a `/api/integrations/{provider}`, que devolve as credenciais Meta/Google descriptografadas

### 10. Links públicos *(código)*
- [ ] Limite de tentativas (`throttle`) nas rotas `/cadastro/{token}`, `/aprovar/{token}`, `/credenciais/{token}`, `/portal/definir-senha/{token}` e no webhook WhatsApp
- [ ] Avaliar validade dos links de aprovação (hoje nunca expiram) — ex: 90 dias

### 11. Senhas da equipe *(código — depende de D4)*
- [ ] `Password::defaults()` mais forte no `AppServiceProvider` (ex: mínimo 10 + letras e números + checagem de senha vazada)
- [ ] Usar a mesma regra em `OrganizationMemberController` (hoje só `min:8`)
- [ ] 2FA, se aprovado em D4

---

## Fase 3 — Verificações fora do código (Evandro)

- [ ] **R2 (Cloudflare):** confirmar se o bucket tem acesso público ligado. Se não for necessário, desligar (o app já usa link temporário assinado)
- [ ] **Backups do Postgres:** existem? Com que frequência? Já testamos restaurar?
- [ ] **n8n e Portainer:** acessíveis pela internet? Senhas fortes + 2FA?
- [ ] **Notebook:** o `.env` local tem a senha de produção — disco criptografado (BitLocker) e senha no Windows

---

## Já está bem feito (não mexer)

Isolamento do Portal do Cliente; checagem de dono nas rotas aninhadas; senhas de clientes e credenciais de integração criptografadas; nenhum segredo no Git; arquivos via link assinado do R2; sanitização de HTML (comentários/briefing); bloqueio após tentativas erradas de login (equipe e Portal); CSRF ativo; sem SQL injection nem atribuição em massa perigosa encontrados; `APP_DEBUG=false` em produção.

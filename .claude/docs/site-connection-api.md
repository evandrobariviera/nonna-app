# Conexão de site com o Nonna App (contrato v1)

Como **qualquer site** entrega leads e rastreamento ao Nonna App: o plugin **Nonna** do WordPress, o **Nonna CMS** ou outro sistema. O contrato é o mesmo para todos. O plugin é só uma das implementações.

Código no App:
- `app/Http/Middleware/VerifySiteSignature.php`: confere a assinatura;
- `app/Http/Controllers/Api/SiteConnectionController.php`: entrada pelo servidor;
- `app/Http/Controllers/Api/NonnaTagController.php`: tag e entrada pelo navegador;
- `app/Services/Tracking/SiteLeadIntake.php`: entrada única usada pelas duas.

## Visão geral

São duas camadas, e o site pode usar uma delas ou as duas:

| Camada | O que faz | Quem usa |
|---|---|---|
| **Navegador: Nonna Tag** | Carrega Pixel, GA4, tag do Google Ads e Clarity com a configuração do App. Dispara o Lead no Pixel com o `event_id`. Sem servidor conectado, guarda a origem e capta os formulários. | Qualquer site (`<script>` no `<head>`) |
| **Servidor: conexão assinada** | Entrega o lead do servidor do site ao App, sem passar pelo n8n e com fila de reenvio no site. | Plugin Nonna (WP), Nonna CMS, sistema próprio |

A mesma identidade vale para as duas: a **chave do site** (`site_key`, 24 caracteres `[a-z0-9]`) de `client_trackings`. A camada do servidor usa também a **senha do site** (`site_secret`, 40 caracteres, guardada criptografada).

O que se cola no site é o **código de conexão**, `chave.senha`, copiado em **Central de Campanhas → Rastreamento → cliente → Conexão do site**. O botão "Gerar novo código" troca a senha, e a antiga para de funcionar na hora.

**Por que não usar o token da organização (Sanctum):** esse token abre a API inteira. Site é invadido com frequência. A senha do site só entrega lead daquele cliente e se troca com um clique.

## Camada do servidor

### Assinatura (todas as rotas `/api/site/v1/*`)

```
X-Nonna-Site:      {site_key}
X-Nonna-Timestamp: {segundos Unix}
X-Nonna-Signature: hex( HMAC-SHA256( "{timestamp}.{corpo cru}", site_secret ) )
X-Nonna-Platform:  wordpress/2.0.0 | nonna-cms/1.4.0 | ...   (opcional, aparece na aba Rastreamento)
X-Nonna-Site-Url:  https://www.cliente.com.br                 (opcional, idem)
Content-Type: application/json
Accept: application/json
```

- A senha não vai na mensagem.
- O App aceita até **5 minutos** de diferença de relógio. Fora disso, responde `401` com `{"status":"clock_skew","server_time":...}`. O site aprende a diferença com o `server_time` e tenta de novo.
- No `GET`, o corpo é vazio: assina-se `"{timestamp}."`.
- Limites: 120 por minuto por IP e **60 por minuto por site**. Acima disso, `429` com `Retry-After: 60`.

Exemplo em PHP:

```php
$body = json_encode($lead);
$ts   = (string) time();
$sig  = hash_hmac('sha256', $ts . '.' . $body, $secret);
// POST {APP}/api/site/v1/leads com os cabeçalhos acima e $body
```

### `GET /api/site/v1/ping`

É o botão "Testar conexão" do site. Responde `200`:

```json
{ "status": "ok", "protocol": "1", "client": { "name": "Gomes Máquinas" },
  "tag": { "url": "https://app.nonnaagenciadigital.com.br/api/t/CHAVE.js", "pixel": true, "ga4": true, "ads": false, "clarity": true },
  "conversions_enabled": true, "server_time": 1763300000 }
```

O ping também marca "Site conectado" na aba Rastreamento, com a plataforma e o domínio.

### `POST /api/site/v1/leads`

```json
{
  "event_id": "gomesmaqui_9f2c1a7b3e4d5f60",
  "name": "Maria Souza",
  "email": "maria@email.com",
  "phone": "5549999990000",
  "city": "Videira", "state": "SC",
  "form_name": "Orçamento",
  "page_url": "https://www.cliente.com.br/contato",
  "page_title": "Contato",
  "fields": { "interesse": "Trator", "mensagem": "..." },
  "attribution": {
    "first": { "utm_source": "facebook", "utm_campaign": "120200001", "fbclid": "IwAR...", "landing_page": "https://...", "referrer": "https://l.facebook.com/", "ts": 1763200000 },
    "last":  { "gclid": "Cj0...", "landing_page": "https://...", "ts": 1763300000 }
  },
  "fbp": "fb.1.1700000000.111", "fbc": "fb.1.1700000000.IwAR...",
  "ga_client_id": "987654321.1700000000",
  "consent": true, "consent_text": "Li e concordo…",
  "ip": "200.150.10.20",
  "user_agent": "Mozilla/5.0 …",
  "platform": "wordpress/2.0.0",
  "site_url": "https://www.cliente.com.br",
  "received_at": "2026-11-16T14:03:00-03:00"
}
```

| Campo | Regra |
|---|---|
| `email` ou `phone` | Pelo menos um. Sem nenhum dos dois, o App responde `422 ignored`. |
| `event_id` | Único por envio, de 120 caracteres no máximo (`[A-Za-z0-9_-]`). O site gera o ID **antes** de enviar e manda o mesmo ID para o navegador (ver "Navegador + servidor juntos"). Repetido, o App responde `200 duplicate`, então a fila pode reenviar sem medo. |
| `attribution` | `first` e `last` com `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `gclid`, `fbclid`, `gbraid`, `wbraid`, `referrer`, `landing_page` e `ts`. O App usa o `last` (ou o `first`, se o `last` estiver vazio) nas colunas do lead. O `gclid` e o `fbclid` também são buscados no `first`. O `first` inteiro fica no `raw_payload.first_touch`. |
| `ip`, `user_agent` | São os do **visitante**, informados pelo site. Como o envio é assinado, o App confia nesses valores. A API de Conversões da Meta exige os dois. O site só os tem na hora do envio, por isso eles vão gravados no pacote da fila. |
| `fbp`, `fbc`, `ga_client_id` | Cookies `_fbp`, `_fbc` e `_ga` (no formato `GA1.1.X.Y`, vai só o `X.Y`). Valor `"undefined"` deve ser descartado. |
| `received_at` | Hora em que a pessoa enviou, em ISO 8601 com fuso. A fila pode entregar horas depois. Data no futuro ou de mais de 30 dias atrás vira "agora". |
| `fields` | Até 40 campos livres. Lista vira texto separado por vírgula. |

Respostas:

| Código | `status` | O site deve |
|---|---|---|
| `201` | `created` (oportunidade nova) | Marcar como entregue |
| `200` | `existing` (a pessoa já tinha cartão aberto e voltou para ele) ou `duplicate` (`event_id` já recebido) | Marcar como entregue |
| `422` | `ignored` / `invalid` | **Não** tentar de novo. Marcar como falha e avisar |
| `401` | `unauthorized` (código errado ou trocado) / `clock_skew` | Guardar o lead e tentar de novo mais tarde. Avisar que falta o código novo |
| `429` / `5xx` / sem resposta | — | Tentar de novo com intervalos crescentes |

O corpo de `201` e `200` traz `client_lead_id` e `opportunity_id`.

Depois de receber, o App segue o caminho de sempre: Central de Leads, aviso "lead capturado" e, se o cliente tem rastreamento ligado, a conversão vai para Meta CAPI, Google Ads e GA4, com o mesmo `event_id`.

### O que o site precisa ter (checklist de implementação)

1. **Cookie `nonna_attr`**, gravado **pelo servidor** (`Set-Cookie`, 12 meses, `SameSite=Lax`, `Secure`, **sem** `HttpOnly`, porque a tag lê). O conteúdo é o JSON `{"first":{…},"last":{…}}`. O `first` nunca é sobrescrito. O `last` muda quando chega UTM, ID de clique ou site de origem externo, e a navegação interna não mexe nele. Se a página vier de cache, o navegador precisa pedir ao servidor para gravar (o plugin faz isso via `admin-ajax`).
2. **Gravar o lead no banco do site antes de enviar** e manter uma fila com novas tentativas. Nenhum lead depende do App estar no ar.
3. **Assinar** cada envio como descrito acima.
4. **Avisar a tag** antes de carregá-la (ver abaixo) e publicar `nonna_lead_captured` no `dataLayer` depois de captar.

## Camada do navegador: Nonna Tag

```html
<script>window.NONNA_SITE = {platform:'nonna-cms', version:'1.4.0', serverForms:'[data-nonna-form]', captureOthers:false};</script>
<script async src="https://app.nonnaagenciadigital.com.br/api/t/CHAVE.js"></script>
```

- **Sem `window.NONNA_SITE`:** a tag grava o cookie `nonna_attr` pelo navegador e capta os formulários com e-mail ou telefone, mandando para `POST /api/t/{chave}/lead`. Essa entrada sempre responde `204` e aceita só a origem dos domínios do cliente.
- **Com `window.NONNA_SITE`** (o servidor cuida da origem):
  - a tag **não** grava cookie;
  - formulários que casam com `serverForms` (seletor CSS) ficam por conta do servidor;
  - os demais ela capta só se `captureOthers: true`.

  No WordPress, o plugin usa `.elementor-form, #nonna-whatsapp-form` com `captureOthers: true`, e assim o Contact Form 7 e o WPForms também entram.
- **Navegador + servidor juntos (dedup na Meta):** depois de captar no servidor, a página publica:

  ```js
  dataLayer.push({ event: 'nonna_lead_captured', nonna_event_id: '<o mesmo event_id enviado ao App>', nonna_form_name: 'Orçamento' });
  ```

  A tag dispara o `Lead` no Pixel com esse `eventID`, e a CAPI do App manda o mesmo ID. A Meta conta um lead só.
- Também existem `window.nonnaTag.lead({...})` (lead à mão), `window.nonnaTag.consent(true|false)` e o clique em link de WhatsApp, que vira `Contact` no Pixel.
- Os sinais antigos `window.NONNA_LEADS` e `NONNA_LEADS_PLUGIN` (Nonna Leads ≤ 1.2) continuam valendo como "servidor cuida de tudo", e nesse caso a tag não capta nada.

## Implementações

| Plataforma | Onde | Observações |
|---|---|---|
| WordPress | Plugin **Nonna 2.0** (`.claude/docs/nonna-wp/`, zip `nonna-2.0.0.zip`) | Substitui o Nonna Leads e o Nonna WhatsApp. Elementor e WhatsApp pelo servidor, outros formulários pela tag. Migra sozinho dos plugins antigos e, enquanto o código não é colado, continua mandando ao n8n. |
| Nonna CMS | Repositório `nonna-cms` | `.env`: `NONNA_APP_URL`, `NONNA_SITE_KEY`, `NONNA_SITE_SECRET` (ou `NONNA_SITE_CONNECTION=chave.senha`). Ação "Enviar ao Arkanno/App" com assinatura. `NONNA_SITE` com `serverForms` dos formulários do CMS. |
| Site sem servidor | Só a tag | Formulários captados pela tag (entrada do navegador). |

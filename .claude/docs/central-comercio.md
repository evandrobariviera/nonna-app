# Central de Comércio — viabilidade e termos do Google

Projeto: varrer empresas de uma cidade via Google Places, gerar "raio-x" de presença digital e usar como primeiro contato comercial (prospecção do OnMap). Escopo original no HTML `projeto_nonna_central-de-comercio.html` (início 02/11/2026, término 04/12/2026).

## Termos do Google Maps Platform (lidos em 30/10/2026)

Fontes: https://cloud.google.com/maps-platform/terms · https://cloud.google.com/maps-platform/terms/maps-service-terms · https://developers.google.com/maps/documentation/places/web-service/policies

A Nonna fatura no Brasil (fora do EEA), então valem os termos gerais abaixo.

### O que pode
- **Guardar `place_id` para sempre** (Service Terms, "Google ID values"). Recomendação da doc: renovar IDs com mais de 12 meses.
- **Guardar lat/lng por até 30 dias** (Places API).
- **Mostrar dados do Places sem mapa** (Places API) — desde que com logo/texto "Google Maps" e atribuições de terceiros.

### O que não pode (ToS 3.2.3 "Restrictions Against Misusing the Services")
- (a) "export, extract, or otherwise scrape" — inclui **(ii) bulk download de places information** e **(iii) copiar e salvar nomes de empresas, endereços ou avaliações**.
- (b) cache além do permitido nos Service Terms.
- (c) "No Creating Content" — (vii) usar o conteúdo pra treinar/testar/validar modelos de IA.
- (d) "No Re-Creating" — (iii) usar em serviço de listagem/diretório ou produto de publicidade.
- (e) usar Places junto com mapa não-Google.

### Obrigações
- Atribuição: logo/texto "Google Maps" visível; se mostrar avaliação, mostrar autor + link.
- 3.2.2(a): os termos do app devem avisar que há conteúdo do Google Maps e linkar os termos adicionais do Google Maps.

## Impacto no desenho

| Ponto | Decisão técnica |
|---|---|
| Tabela de empresas mapeadas | Guarda **só** `place_id` + o que é da Nonna (nota calculada, componentes da nota, segmento, cidade, status, datas). **Sem** nome, endereço, telefone, nota/avaliações do Google. |
| Lista na tela | Nome/nota buscados ao vivo (Text Search com field mask; custa centavos por página de 20). |
| Página do raio-x | Dados do Google ao vivo + logo "Google Maps" + rodapé com aviso/links dos termos. Limitar views por token (cada view = até 4 chamadas). |
| Varredura "cidade inteira" | Zona cinza com (a)(ii) bulk download. Preferir varredura sob demanda por segmento/nicho que vai ser abordado. |
| IA | Não mandar texto de avaliações do Google pro LLM. Pontos fracos do raio-x por regra, não por IA lendo conteúdo do Google. A rodada "aparece nas IAs" é independente (pergunta à IA, não usa dado do Google). |
| CRM | **Decidido 30/10:** a empresa só entra no CRM quando **responde** → vira Contato + Oportunidade em `novo_lead` com o raio-x anexado. Nome/número vêm da conversa do WhatsApp (dado nosso). Antes disso, o envio é registrado só pelo `place_id`. |
| Google Cloud | **Projeto novo** (`nonna-central-comercio`), mesma conta de faturamento. Places API (New) só. 2 chaves (prod restrita ao IP da Hetzner, dev ao IP local), cota diária (Text Search 500, Place Details 1000 no piloto), orçamento R$ 500 com escopo só no projeto. |
| Lista de saída (opt-out) | Guardar hash do telefone (`PhoneKey`), não o número. |

Risco prático de descumprir: suspensão do projeto/chave no Google Cloud (3.2.4 permite revisão a pedido do Google).

## Anúncios (Meta / Google) — decidido 30/10/2026

Não existe API oficial pra anúncio comercial no Brasil: Meta Ad Library API só devolve anúncio comum veiculado na UE/Reino Unido (fora disso, só político/causa social); Google Ads Transparency Center não tem API pública. Raspagem (Apify/SerpApi) = mesma zona cinza de termos → não usar.

Desenho aprovado:
- **Automático (na checagem do site):** Pixel Meta (`fbq(`/`connect.facebook.net`), tag Google Ads (`AW-`), GTM (`GTM-`, inclusive tags dentro do container público), GA4 (`G-`).
- **Conferência manual em 1 clique:** botões abrem Biblioteca de Anúncios da Meta e Central de Transparência do Google já buscando o nome; operador marca Sim/Não/Não sei (registra quem e quando). Só pras empresas que vão receber raio-x.
- **Fora da nota 0-100.** Vira "sinal de oportunidade": *Anuncia sem medir* (anuncia + sem Pixel/tag), *Não anuncia, concorrente sim*, *Pixel pronto, sem anúncio*, *Anúncio a conferir*, *Anuncia e mede*. Filtro na lista + pode entrar na mensagem de abordagem.
- Encaixa na etapa 3 (coleta + site + nota).

Protótipo das telas: `.claude/docs/prototipos/central-comercio.html`.

## LGPD (não é parecer jurídico)
- Base: legítimo interesse (art. 7º IX + art. 10), com teste de balanceamento registrado por escrito.
- Muitos profissionais (dentistas) cadastram celular pessoal → dado de pessoa física.
- Mensagem identifica a Nonna, diz de onde veio o contato e oferece saída clara; saída respeitada para sempre.

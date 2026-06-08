📚 DOCUMENTAÇÃO DA ARQUITETURA DO BANCO DE DADOS
SISTEMA OPERACIONAL NONNA AGÊNCIA DIGITAL
Esta documentação detalha a modelagem física e lógica do banco de dados relacional e vetorial PostgreSQL que sustenta a operação, automações e inteligência artificial da Nonna Agência Digital.

🗺️ VISÃO GERAL DO ECOSSISTEMA
O banco de dados é estruturado de forma híbrida e modular, dividindo-se em quatro grandes ecossistemas:
Ecossistema ClickUp (clickup_): Funciona como o "Gêmeo Digital" (Digital Twin) do ClickUp da agência. Sincroniza em tempo real Espaços, Pastas, Listas, Tarefas, Status, Usuários, Campos Personalizados e o histórico completo de transição de status (SLA de produção).
Ecossistema de IA e Mensageria (agent_): Controla os agentes conversacionais (System Prompts, Configurações de API, logs de chat no n8n) e possui a Base de Conhecimento Vetorial (RAG) indexada com pgvector para consultas semânticas das marcas dos clientes.
Ecossistema de Tráfego Pago (traffic_): Controla o histórico de métricas diárias (Meta/Google Ads) e armazena os Diários de Otimização (Logs de Otimizações) preenchidos pelos gestores de tráfego.
Ecossistema de Gamificação (programs_): Armazena as regras e o registro de pontuações de produtividade da equipe interna.

🔑 A CHAVE RELACIONAL MESTRE: client_task_id
O pilar de conexão de todo o banco de dados é a tabela de clientes do CRM: clickup_tasks_clients.
Todas as campanhas de tráfego, diários de bordo de otimização, vetores de inteligência artificial (RAG) e dados de performance se conectam e são filtrados utilizando o client_task_id (que é o ID da tarefa física do cliente na lista de Cadastros do ClickUp).

📂 PARTE 1: ECOSSISTEMA CLICKUP (Gêmeo Digital)
Esta camada espelha a arquitetura plana e os dados do ClickUp, permitindo consultas relacionais complexas sem bater na API do ClickUp (evitando limites de requisições).
1.1 clickup_workspaces
Finalidade: Armazena as instâncias de Workspaces (Ambientes de Trabalho) do ClickUp.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
workspace_id
character varying
NO (PK)
ID único do Workspace no ClickUp
workspace_name
character varying
NO
Nome do Workspace
last_synced_at
timestamptz
YES
Data/Hora da última sincronização
created_at
timestamptz
YES
Data de criação no banco
updated_at
timestamptz
YES
Data de atualização no banco


1.2 clickup_spaces
Finalidade: Armazena os Spaces do ClickUp (ex: #CRM, #OPERACIONAL).
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
space_id
character varying
NO (PK)
ID único do Space no ClickUp
workspace_id
character varying
YES (FK)
ID do Workspace pai
space_name
character varying
NO
Nome do Space (ex: OPERACIONAL)
is_private
boolean
YES
Se o espaço é privado
archived
boolean
YES
Se está arquivado no ClickUp
last_synced_at
timestamptz
YES
Data da última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização


1.3 clickup_folders
Finalidade: Armazena as Pastas que dividem os fluxos de trabalho.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
folder_id
character varying
NO (PK)
ID único da Pasta
space_id
character varying
NO (FK)
ID do Space pai
folder_name
character varying
NO
Nome da Pasta
archived
boolean
YES
Se está arquivada
last_synced_at
timestamptz
YES
Última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização


1.4 clickup_lists
Finalidade: Armazena as Listas físicas de tarefas (ex: ### Sprints, ### Campanhas, ### Cadastros).
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
list_id
character varying
NO (PK)
ID único da Lista
folder_id
character varying
YES (FK)
ID da Pasta pai (pode ser nulo se for lista avulsa)
space_id
character varying
NO (FK)
ID do Space pai
list_name
character varying
NO
Nome da Lista
archived
boolean
YES
Se está arquivada
last_synced_at
timestamptz
YES
Última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização
logical_id
character varying
YES
ID lógico interno usado em rotas n8n


1.5 clickup_statuses
Finalidade: Armazena todas as opções de Status válidos de cada lista para controle de workflow.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial do Status
list_id
character varying
NO (FK)
ID da Lista relacionada
status_name
character varying
NO
Nome do Status (ex: em produção, concluído)
status_type
character varying
YES
Tipo do Status (custom, open, closed)
status_order_index
integer
YES
Ordem do status no pipeline
status_color
character varying
YES
Cor hexadecimal do status
last_synced_at
timestamptz
YES
Última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização


1.6 clickup_custom_fields
Finalidade: Cadastro de todos os campos personalizados da agência (ex: "Sentimento do Gestor", "Custo Diário").
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
custom_field_id
character varying
NO (PK)
ID único do campo personalizado
field_name
character varying
NO
Nome do campo (ex: ❤️ Sentimento do Gestor)
field_type
character varying
NO
Tipo do campo (drop_down, labels, currency, short_text)
last_synced_at
timestamptz
YES
Última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização
type_config
jsonb
YES
Configurações de opções de dropdown, cores e regras em JSON


1.7 clickup_list_custom_field_settings
Finalidade: Tabela relacional de ligação (Muitos para Muitos) entre as listas e os campos personalizados ativos nelas, indicando se o campo é obrigatório na lista.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial de relação
list_id
character varying
NO (FK)
ID da Lista relacionada
custom_field_id
character varying
NO (FK)
ID do Campo Personalizado
is_required
boolean
YES
Se o campo é obrigatório para aquela lista
last_synced_at
timestamptz
YES
Última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização


1.8 clickup_tasks
Finalidade: A tabela principal do ecossistema ClickUp. Armazena os registros individuais de todas as tarefas da agência com seus campos padrão e personalizados em formato de objeto JSON plano (jsonb).
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
task_id
character varying
NO (PK)
ID único da tarefa no ClickUp (Ex: 86adp72n3)
task_name
character varying
YES
Nome/Título da tarefa
description
text
YES
Descrição em texto rico ou Markdown
list_id
character varying
YES (FK)
ID da Lista onde reside a tarefa
status
character varying
YES
Status atual do fluxo (ex: em produção)
priority
character varying
YES
Prioridade (urgente, high, normal, low)
date_created
timestamp
YES
Data de criação no ClickUp
date_updated
timestamp
YES
Data de atualização no ClickUp
related_client_task_id
character varying
YES
ID relacional da tarefa do cliente (antigo mapeamento)
custom_fields
jsonb
YES
Campos personalizados populados em JSONB (onde extraímos as variáveis)
last_synced_at
timestamp
YES
Última sincronização com o banco
solicitante_nome
character varying
YES
Nome de quem solicitou (para tickets externos)
solicitante_email
character varying
YES
E-mail do solicitante externo
solicitante_whatsapp
character varying
YES
WhatsApp do solicitante externo


1.9 clickup_task_status_history
Finalidade: Armazena o registro de auditoria em milissegundos de toda mudança de status que uma tarefa teve, quem mudou e quanto tempo ela ficou na fase anterior. Fundamental para os relatórios de SLA e gargalos de produção (tempo de aprovação do cliente vs tempo de execução).
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial do log
task_id
character varying
NO (FK)
ID da Tarefa relacionada
list_id
character varying
YES (FK)
ID da Lista relacionada
status_from
text
YES
Status de origem (de onde saiu)
status_to
text
NO
Status de destino (para onde foi)
changed_at
timestamptz
NO
Data e Hora exata da mudança
changed_by_user_id
character varying
YES (FK)
ID do usuário que efetuou a alteração
time_in_previous_status_minutes
integer
YES
Tempo exato (em minutos) que a tarefa ficou no status anterior


1.10 clickup_tasks_clients
Finalidade: Armazena os dados dos clientes ativos na agência mapeados da lista de CRM do ClickUp.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
client_task_id
character varying
NO (PK)
ID único do cliente no CRM
company_name
text
NO
Razão Social / Nome Fantasia da empresa do cliente
website
character varying
YES
Link do site oficial do cliente
cnpj
character varying
YES
CNPJ para fins fiscais e faturamentos
last_synced_at
timestamp
YES
Última sincronização


1.11 clickup_tasks_contacts
Finalidade: Armazena os dados das pessoas físicas de contato (decisores, gerentes, parceiros) mapeados do CRM.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
contact_task_id
character varying
NO (PK)
ID único do contato no CRM
contact_name
text
YES
Nome completo do contato
email
character varying
YES
E-mail do contato
phone
character varying
NO
Telefone / WhatsApp do contato
job_title
character varying
YES
Cargo (Sócio, Gestor, Financeiro)
last_synced_at
timestamp
YES
Última sincronização


1.12 clickup_tasks_client_contacts_relation
Finalidade: Tabela relacional (Muitos para Muitos) que liga contatos físicos às empresas (clientes jurídicos) correspondentes.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
client_task_id
character varying
NO (PK/FK)
ID do Cliente jurídico
contact_task_id
character varying
NO (PK/FK)
ID do Contato físico


1.13 clickup_users
Finalidade: Cadastro de todos os colaboradores, sócios e usuários cadastrados no ClickUp da agência.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
user_id
character varying
NO (PK)
ID único do usuário no ClickUp
username
character varying
YES
Nome de exibição do usuário
email
character varying
YES
E-mail do usuário
initials
character varying
YES
Iniciais (ex: EB para Evandro Bariviera)
color
character varying
YES
Cor hexadecimal do usuário no sistema
profile_picture_url
text
YES
Link da foto de perfil
last_synced_at
timestamptz
YES
Última sincronização
created_at
timestamptz
YES
Data de criação
updated_at
timestamptz
YES
Data de atualização


🧠 PARTE 2: ECOSSISTEMA DE IA E BASE DE CONHECIMENTO (RAG)
Esta seção controla o funcionamento dos agentes de IA no n8n e armazena os conhecimentos vetoriais para buscas semânticas ultrarrápidas através da extensão pgvector.
2.1 agent_businesses
Finalidade: Armazena os dados das empresas de clientes que utilizam serviços de agentes inteligentes da Nonna.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial interno da empresa
business_identifier
character varying
NO
Identificador único de texto da empresa
agent_service_status
character varying
NO
Status do serviço de IA (ativo, inativo, suspenso)
api_configs
jsonb
YES
Configurações customizadas de chaves de API, limites de tokens e parâmetros específicos
created_at
timestamptz
NO
Data de criação
updated_at
timestamptz
NO
Data de atualização


2.2 agent_agents
Finalidade: Armazena os dados cadastrais dos agentes virtuais especialistas da Nonna (ex: "Sniper de Ads", "Arquiteto de LP").
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial do agente
business_id
integer
NO (FK)
ID da empresa relacionada
name
character varying
NO
Nome do agente (ex: Sniper de Ads)
phone_number_assigned
character varying
YES
Número de WhatsApp atrelado ao agente (para automações de chat)
status
character varying
NO
Status de deploy (active, development, maintenance)
system_prompt
text
YES
System Prompt (A Instrução Mestre e regras rígidas de comportamento do agente)
knowledge_base_identifier
character varying
YES
Identificador da partição de RAG vinculada a este agente
additional_config
jsonb
YES
Configurações extras de temperatura, modelo (GPT-4o, Claude 3.5), etc.
created_at
timestamptz
NO
Data de criação
updated_at
timestamptz
NO
Data de atualização


2.3 agent_business_knowledge (A Tabela Vetorial de RAG)
Finalidade: O cérebro de RAG (Retrieval-Augmented Generation) da agência. Armazena fatias de texto (chunks) de manuais, tom de voz, regras de marca, sites e estratégias dos clientes com as suas respectivas representações em vetores matemáticos para buscas semânticas de alta performance.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial do conhecimento
business_id
integer
NO (FK)
ID da empresa atrelada
text
text
NO
O pedaço de texto bruto (chunk) do conhecimento
metadata
jsonb
YES
Dados extras de origem (ex: {"client_task_id": "...", "tipo": "feedback_visual"})
embedding
vector
YES (pgvector)
Vetor de 1536 dimensões contendo o embedding matemático gerado pela OpenAI


2.4 agent_conversations_metadata
Finalidade: Rastreia os metadados de sessões de chat abertas entre clientes e agentes de IA nos diversos canais.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID único da conversa
contact_id
integer
NO (FK)
ID do contato físico relacionado
agent_id
integer
NO (FK)
ID do agente envolvido
business_id
integer
NO (FK)
ID da empresa relacionada
channel_type
character varying
NO
Canal de origem (whatsapp, clickup, slack, web)
status
character varying
NO
Status da sessão (open, ended, handoff)
started_at
timestamptz
NO
Hora de início da conversa
last_activity_at
timestamptz
YES
Hora do último envio de mensagem
ended_at
timestamptz
YES
Hora de encerramento da conversa
created_at
timestamptz
NO
Data de criação
updated_at
timestamptz
NO
Data de atualização


2.5 agent_n8n_chat_histories
Finalidade: Armazena o histórico do banco de dados de sessões de chat que passam pelo n8n, permitindo que a IA mantenha a memória das conversas ativas no Whatsapp ou nos canais de atendimento.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID único do registro
session_id
character varying
NO
ID da sessão ativa de chat
message
jsonb
NO
A mensagem enviada/recebida formatada em JSON
created_at
timestamptz
NO
Data de criação


2.6 agent_handoff_routing_rules
Finalidade: Regras de "Handoff" (passagem de bastão de IA para Humano). Define quais palavras-chave de transbordo (ex: "falar com humano", "suporte", "financeiro") acionam canais específicos.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial da regra
business_id
integer
NO (FK)
ID da empresa relacionada
department_keyword
character varying
NO
Palavra-chave disparadora (ex: financeiro)
notification_channel_id
integer
NO (FK)
Canal de destino da notificação
destination_address
text
YES
Destinatário da notificação (ex: telefone do WhatsApp ou ID do canal do Slack)
is_active
boolean
YES
Se a regra de handoff está ligada
notes
text
YES
Observações extras da regra
created_at
timestamptz
NO
Data de criação


2.7 agent_message_buffer
Finalidade: Buffer de mensagens temporário usado para evitar o "choque" de disparos e concorrência no WhatsApp quando o usuário envia múltiplos áudios ou textos em sequência rápida.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id
integer
NO (PK)
ID sequencial do buffer
conversation_id
integer
NO (FK)
ID da conversa relacionada
message_text
text
YES
Texto acumulado
created_at
timestamptz
NO
Data de criação no buffer


2.8 agent_execution_locks
Finalidade: Travas lógicas de concorrência que garantem que o n8n não processe a mesma mensagem de usuário duas vezes caso ocorra atraso na rede ou duplicidade de requisições Webhook.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
conversation_id
integer
NO (PK)
ID da conversa
execution_id
character varying
NO (PK)
ID de execução do n8n
locked_at
timestamptz
NO
Data e Hora do travamento


📊 PARTE 3: ECOSSISTEMA DE TRÁFEGO PAGO (Performance & Analytics)
Esta seção foi desenhada para amarrar os dados numéricos de campanhas do Facebook Ads e Google Ads, além de estruturar o histórico de mudanças manuais (diário de bordo) feitas pelos gestores humanos.
3.1 traffic_metrics_daily
Finalidade: Armazena as métricas numéricas consolidadas agregadas diariamente para fins de análise histórica de ROI e CPL por parte dos Agentes de IA.
Estrutura Física:
codeSQL
CREATE TABLE traffic_metrics_daily (
    id SERIAL PRIMARY KEY,
    client_task_id CHARACTER VARYING NOT NULL REFERENCES clickup_tasks_clients(client_task_id),
    platform CHARACTER VARYING NOT NULL, -- e.g., 'Meta Ads', 'Google Ads'
    record_date DATE NOT NULL,
    spend NUMERIC(10,2) DEFAULT 0.00,
    leads INT DEFAULT 0,
    cpl NUMERIC(10,2) DEFAULT 0.00,
    roas NUMERIC(10,2) DEFAULT 0.00,
    ctr NUMERIC(5,2) DEFAULT 0.00,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

3.2 traffic_optimizations_log (Diário de Bordo do Gestor)
Finalidade: Registra as modificações e o contexto qualitativo das otimizações manuais aplicadas por Marlon ou Marielen nas campanhas (ex: mudança de orçamento, alteração de criativo, fadiga de público). Fundamental para que os agentes de IA saibam "o que mudou" nas contas ao longo das semanas.
Estrutura Física:
codeSQL
CREATE TABLE traffic_optimizations_log (
    id SERIAL PRIMARY KEY,
    client_task_id CHARACTER VARYING NOT NULL REFERENCES clickup_tasks_clients(client_task_id),
    clickup_task_id CHARACTER VARYING, -- Link opcional com a tarefa exata da Sprint no ClickUp
    manager_name CHARACTER VARYING NOT NULL, -- e.g., 'Marlon', 'Marielen'
    optimization_date DATE NOT NULL,
    action_taken TEXT NOT NULL, -- Ex: 'Pausei ad set de público frio e subi criativo de vídeo'
    reason_for_change TEXT, -- Ex: 'CPL estava 40% acima da meta do roadmap'
    sentiment_status CHARACTER VARYING, -- Ex: 'Positive', 'Negative', 'Neutral'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

3.3 traffic_campaigns
Finalidade: Tabela relacional que faz a indexação em tempo real (mapa de espelho) entre os IDs reais gerados no Facebook/Google e as respectivas tarefas criadas no ClickUp, prevenindo duplicações de tarefas e reduzindo gargalos de requisições de API no ClickUp.
Estrutura Física:
codeSQL
CREATE TABLE traffic_campaigns (
    campaign_id CHARACTER VARYING PRIMARY KEY, -- O ID real do Meta/Google Ads (Ex: 12024867760)
    clickup_task_id CHARACTER VARYING NOT NULL REFERENCES clickup_tasks(task_id), -- ID da tarefa no ClickUp
    client_task_id CHARACTER VARYING NOT NULL REFERENCES clickup_tasks_clients(client_task_id), -- ID do cliente no CRM
    platform CHARACTER VARYING NOT NULL, -- 'FACEBOOK' ou 'GOOGLE'
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

🏆 PARTE 4: ECOSSISTEMA DE GAMIFICAÇÃO INTERNA
Mapeia as metas de produtividade internas e pontuações da equipe da Nonna baseadas em finalizações de entregas no prazo e regras de qualidade configuradas no ClickUp.
4.1 programs_regras_pontuacao
Finalidade: Armazena as regras do programa de produtividade, descrevendo os pontos concedidos por ação bem-sucedida (ex: entregar arte no prazo, sem refatoração).
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id_regra
integer
NO (PK)
ID sequencial da regra
descricao
text
NO
Descrição detalhada da regra
pontos
integer
NO
Valor de pontos concedidos (positivo ou negativo)
tipo
character varying
NO
Tipo (bonificação, penalização)
ativa
boolean
NO
Se a regra está atualmente ligada


4.2 programs_registros_pontuacao
Finalidade: Histórico de auditoria de todos os lançamentos de pontuação feitos para os colaboradores da agência baseados nas tarefas concluídas no ClickUp.
Estrutura Física:
Coluna
Tipo de Dado
Nullable
Descrição
id_registro
integer
NO (PK)
ID sequencial do lançamento
id_clickup_colaborador
character varying
NO (FK)
ID do ClickUp do colaborador pontuado
nome_colaborador
character varying
NO
Nome do colaborador pontuado
email_colaborador
character varying
NO
E-mail do colaborador
id_regra
integer
NO (FK)
ID da regra aplicada
id_clickup_lider
character varying
NO (FK)
ID do ClickUp do líder que aprovou/registrou
nome_lider
character varying
NO
Nome do líder responsável
data_registro
timestamptz
NO
Data e Hora do registro da pontuação
observacao
text
YES
Comentários ou justificativa do lançamento
id_tarefa_clickup
character varying
NO (FK)
ID da Tarefa do ClickUp que gerou a pontuação


Conclusão e Diretrizes de Engenharia para o NotebookLM:
Esta estrutura relacional permite que qualquer LLM conectada ao banco via n8n possa extrair o contexto geral dos clientes (através de clickup_tasks_clients e agent_business_knowledge) e responder de forma analítica e preditiva sobre a performance das campanhas (cruzando traffic_metrics_daily com o diário qualitativo de bordo traffic_optimizations_log).
O espelhamento de dados estruturado do ClickUp na tabela clickup_tasks garante escalabilidade infinita sem sofrer bloqueios de tráfego por limites de API do ClickUp.


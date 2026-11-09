# AIA-R01 G01/G02 — proposta técnica de confiança serviço-serviço

**Versão documental v0.1 — 09/11/2026.** **Não homologada, não implementada e sem seleção definitiva de identidade ou fornecedor.** Base no laboratório FT01/F1-11 integrado na dev, mas **os identificadores sintéticos não podem virar credenciais reais**.

## 1. Objetivo e limites

Permitir no futuro que o Imagine AI Agents receba pedidos do CRM **somente leitura**, sabendo (a) qual serviço autenticado emitiu a chamada, (b) qual empresa, operador, finalidade e recurso foram efetivamente autorizados pelo produto dono, (c) se a ação/escopo/versão permanece válido, e (d) como negar e auditar tentativas cruzadas.

Não pressupor SSO Portal↔CRM. O CRM mantém usuários, empresas, permissões, leads e autoridade final do recurso. O Agents coordena apenas os trabalhos de IA e não acessa tabelas do CRM diretamente.

## 2. Contrato lógico proposto (não é payload JWT utilizável)

| Campo lógico | Emissor/autoridade | Regra técnica |
| --- | --- | --- |
| `service_issuer` | Identidade de serviço verificada | Nunca usar valor vindo exclusivamente do corpo HTTP ou do prompt |
| `audience` | Servidor validador | Só a audiência exata do serviço destinatário; nenhuma reutilização entre produtos |
| `tenant_key` | CRM autenticado | Vínculo canônico do usuário com a empresa, verificado pelo CRM |
| `subject_key` | CRM autenticado | Usuário ou conta de serviço; IDs no texto não comprovam identidade |
| `resource_key` | CRM, dono do registro | Escopo de um lead/atendimento específico; negação uniforme para ausente e proibido |
| `purpose` | Contrato da operação | Finalidade exata, separada da permissão de usar ferramentas |
| `operation_scope` | Produto dono | Início apenas com leitura mínima; sem criar tarefa, alterar lead, campanha ou cobrança |
| `issued_at` e `expires_at` | Infraestrutura confiável | Janela curta e relógios sincronizados; tolerância definida tecnicamente |
| `request_id` / `nonce` | Emissor + política de replay | Registro persistente/compartilhado, não mapa em memória local |
| `policy_version` | Produto e segurança | Versão do contrato e regras ativas, sujeita a invalidação |
| `evidence_ref` | Produto dono | Referência rastreável da autorização final, sem expor dados sensíveis no token |

**Campos não podem ser definidos pela IA** para elevar privilégios. Qualquer discrepância entre serviço autenticado, claims, produto e recurso retorna negação. Nenhuma informação sobre a existência de recursos de outras empresas deve vazar na mensagem de erro.

## 3. Fronteiras de confiança propostas

1. O serviço emissor autentica usuário/empresa e monta um contexto mínimo autorizado por operação. Não enviar tokens internos ao prompt ou logs de conteúdo.
2. A comunicação Agents↔CRM usa **mecanismo de autenticação de serviço forte a ser homologado** (opções candidatas: mTLS, tokens de serviço assinados com rotação de chaves, ou combinação). Não escolher concretamente até avaliar topologia da VPS, gestão de chaves e contratos do CRM.
3. O Agents verifica emissor, audiência, validade, revogação e compatibilidade com escopo e finalidade; registra apenas metadados mínimos de auditoria.
4. **O CRM revalida autorização em sua API**, no momento do acesso, usando identidade/empresa/recurso atuais. Uma assinatura ou aprovação na camada Agents **não** substitui essa checagem.
5. Em recusa, expiração, indisponibilidade de serviços de autorização, resposta indeterminada ou timeout: **negar ou manter pendente sem repetir ação mutável**.
6. Logs e rastreio preservam correlação da solicitação sem armazenar tokens ou conteúdo de leads por padrão.

## 4. Casos eliminatórios mínimos da futura homologação

- Token de serviço inexistente, assinatura inválida, algoritmo inesperado, issuer/audience errados e chave revogada.
- Empresa, usuário, recurso ou finalidade divergentes; recurso ausente e de outro tenant com erro indistinguível.
- Tentativa de trocar conteúdo da requisição após autorização, incluir escopo extra ou replay de `nonce` em múltiplas instâncias.
- Operador sem permissão atual, desligado ou movido de empresa; mudança de política após emissão.
- Cache de permissões desatualizado e produto dono indisponível: negar em vez de assumir `ALLOW`.
- Logs e métricas sem payload privado, sem tokens e sem exposição entre empresas.

O F1-11 cobre **apenas cenários sintéticos**, não esses ataques em infraestrutura real.

## 5. Gate de aprovação necessário (ainda aberto)

**G01:** decidir autoridade canônica de identidade e empresa, formato e verificador do token de serviço, emissor/audiência, TTL/rotação/revogação e trust boundaries.

**G02:** homologar matriz de escopos/recurso e API de autorização final do CRM, incluindo isolamento e testes negativos.

Também dependem de G05 (LGPD), G08 (versionamento e contrato CRM), G10 (infraestrutura, segredos, observabilidade, backups) e G11 (escopo/orçamento/aceite). Os gates CORE/SEG/AUT CRM têm precedência sobre qualquer permissão ou acesso efetivo.

## 6. Próxima tarefa segura sem aprovação crítica

Preparar OpenAPI/JSON Schema **somente ilustrativos** e fixtures sintéticas de mensagens rejeitadas, com tabelas de `ALLOW/DENY` e motivos fechados; nenhum endpoint, token, segredo, banco ou serviço real deve ser conectado.

**Condição para fase de integração:** aprovação expressa do responsável para um piloto CRM somente leitura, após revisão formal dos contratos e gates acima. Sem autorização por silêncio; `main`, VPS e produção permanecem inalteradas.

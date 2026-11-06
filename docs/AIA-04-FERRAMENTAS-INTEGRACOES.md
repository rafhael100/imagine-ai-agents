# AIA-04 — Ferramentas, Integrações e Execução Autorizada | Proposta funcional v0.1

> **Situação:** diretrizes AIA04-D01 a D10 APROVADAS FUNCIONALMENTE em 2026-11-06; critérios técnicos e implementação continuam bloqueados.  
> **Data:** 2026-11-06.  
> **Escopo:** Imagine AI Agents, serviço compartilhado sem acesso irrestrito a bases ou infraestrutura dos produtos.  
> **Precedência:** AIA-01 D01–D08, AIA-02 D01–D10 e AIA-03 D01–D10 aprovadas funcionalmente; gates técnicos e implementação continuam bloqueados.

## 1. Objetivo

Disponibilizar aos agentes da Imagine **ferramentas oficialmente homologadas, versionadas e autorizadas** para consultar ou realizar operações nos produtos, sem conceder acesso direto ao banco de dados, privilégios administrativos nem controle de regras comerciais pelo modelo.

A IA pode selecionar e solicitar uma ferramenta; o serviço de execução interpreta a solicitação, valida política e parâmetros, e o **produto proprietário do dado ou da operação** mantém a autorização final e a decisão de executar.

**Diferença fundamental:** "o agente sabe que existe a ferramenta" não significa "o agente tem direito a executá-la".

## 2. Conceitos e limites

| Conceito | Definição |
| --- | --- |
| Ferramenta (tool) | Operação bem definida, com finalidade, schema de entrada/saída, efeito, política, versão e auditoria. |
| Conector | Adaptador para um serviço/produto autorizado, sem transferir a titularidade de seus dados. |
| Capability | Capacidade declarada no template de agente, condicionada à autorização real. |
| Catálogo | Registro interno de ferramentas homologadas que podem ser atribuídas a templates oficiais. |
| Execução | Solicitação autenticada e auditada de uma ferramenta, permitida somente após validações sucessivas. |
| Escopo | Conjunto de produto, empresa, identidade, ação, finalidade, recurso e permissões aplicáveis à execução. |
| Ação sensível | Operação externa, financeira, irreversível, com dados regulados ou elevação de acesso, submetida a políticas e alçadas de AIA-08. |
| Adapter | Implementação do contrato técnico com o sistema destino, sujeita a teste, homologação e versionamento. |

## 3. Exemplos e propriedade dos dados

| Produto | Ferramentas candidatas | Domínio dono da autorização |
| --- | --- | --- |
| CRM Imagine | Consultar lead, consultar funil, registrar resumo, sugerir classificação, criar tarefa | Backend CRM |
| Portal Imagine | Consultar contrato elegível, consultar status de chamado, preparar solicitação de suporte | Backend Portal |
| Performance AI | Consultar indicadores, criar rascunho de recomendação, sugerir ajuste de campanha | Backend Performance AI |
| Universidade Imagine | Consultar catálogo de conteúdos, recuperar aula elegível, salvar progresso autorizado | Backend Universidade |

**Não autorizamos nesta fase** APIs externas, consultas a dados reais, alteração de campanhas, disparos de mensagens ou mutações em produtos. Os nomes acima são apenas exemplos de capacidade futura.

Para propostas de modificação de orçamento ou campanhas do Performance AI, a ferramenta precisa verificar direitos e alçadas do produto, inclusive a separação entre gasto de mídia e serviços Imagine.

No CRM, o motor nativo de automações permanece no próprio produto. AIA-04 oferece capacidades para chamadas autorizadas, não um novo construtor de automações de clientes.

## 4. Jornada de cadastro e homologação da ferramenta

1. Equipe Imagine registra ferramenta candidata com identificador estável, owner, produto-destino, operação e justificativa de negócio.
2. Documenta contrato de entrada e saída, limites, efeitos, classificação de risco, escopos, requisitos de autorização e exemplos **fictícios**.
3. Estabelece princípio do menor privilégio: preferir somente leitura e escopo estrito por recurso.
4. Define adaptador, protocolos, tratamento de falhas, timeout, quotas, idempotência e compensação quando aplicável.
5. Exige revisão funcional, segurança, privacidade, compliance/licenciamento e teste de contrato antes de homologação.
6. Publica versão imutável com compatibilidade de produtos e agentes, rollout gradual, auditabilidade e reversão.
7. Suspende ou revoga ferramenta/versão em caso de incidente, sem editar silenciosamente versões já utilizadas.

Versão de ferramenta deve ser independente de versão do template AIA-01, porém compatível e registrada na execução.

## 5. Processo de execução segura (proposta)

```text
Produto autentica usuário/serviço e empresa
  -> verifica produto, finalidade, permissão, plano e limites
  -> envia requisição mínima com identidade/contexto verificáveis
Imagine AI Agents monta contexto e decide se precisa de ferramenta
  -> valida se template/versão e instância têm a capacidade
  -> valida schemas, escopos e política de risco/limites
  -> se necessário, cria solicitação de aprovação AIA-08
  -> chama adaptador versionado com identidade delegada de escopo limitado
Produto destino revalida empresa, usuário, ação, recurso e regras de negócio
  -> executa ou nega e retorna resultado tipado
Imagine AI Agents registra eventos de execução, versão, custo e decisão
  -> devolve resultado e status ao produto, sem expor segredos
```

**Nunca confiar em** `tenant_id`, papel de administrador, status de aprovação ou URL de callback recebidos do texto do agente ou do usuário sem validação contra o contexto autenticado. A autorização deve ser recalculada antes de qualquer efeito externo.

## 6. Classificação de risco

| Classe | Exemplos | Controle padrão candidato |
| --- | --- | --- |
| R0 — Leitura limitada | FAQ pública, catálogo não sensível | Leitura autorizada, limite e auditoria básica |
| R1 — Leitura privada | Consultar dados de lead autorizado | Autorização contextual por empresa/recurso, trilha auditável |
| R2 — Mutação reversível | Criar tarefa no CRM | Permissão específica, validação de schema, idempotência, auditoria |
| R3 — Comunicação ou mutação sensível | Enviar mensagem, alterar campanhas, publicar conteúdo | Revisão/aprovação conforme política do produto e alçada de AIA-08 |
| R4 — Crítica/privilegiada | Excluir dados, movimentar fundos, aumentar privilégio | Negado por padrão; fluxo excepcional com autorização expressa e política especializada |

A classificação será refinada por operações concretas na homologação e não pode ser rebaixada por um cliente ou prompt.

## 7. Contratos e versões (AIA-12)

Cada ferramenta deve ter:
- Identificador, versão, descrição curta e finalidade.
- Definição de entradas/saídas estruturadas, campos obrigatórios e limites de tamanho.
- Erros padronizados (não autorizado, quota, conflito, inválido, não encontrado, indisponível, aprovação pendente).
- Direitos requeridos, propriedade dos dados, destino, tempo máximo e política de retry.
- Categoria de dados pessoais, criptografia em trânsito, retenção e minimização.
- Garantia de não executar outra empresa por ID informado, sem verificação.
- Requisitos de idempotência/reconciliação para mutações.
- Eventos de telemetria e correlação, sem registrar tokens ou dados pessoais desnecessários.
- Data de homologação, changelog, compatibilidade e plano de descontinuação.

Exemplo de contrato **conceitual**:
```json
{
  "tool_id": "crm.criar_tarefa",
  "version": "1.0",
  "operation": "write",
  "risk": "R2",
  "input_schema": {
    "lead_ref": "identificador de recurso validado pelo CRM",
    "descricao": "texto com limite e validação"
  },
  "requires": ["crm.task:create", "tenant-context", "idempotency-key"],
  "approval": "conforme política de risco AIA-08"
}
```
Isto não é uma API, código executável nem uma definição autorizada de escopos de produção.

## 8. Isolamento e credenciais

- Cada chamada tem contexto confiável e verificável de empresa, produto, usuário/serviço, permissões, finalidade, operação e recurso.
- Segredos e credenciais são armazenados em serviço seguro e nunca incorporados ao prompt, código-fonte ou logs.
- Tokens de serviço têm escopo e validade mínimos, com rotação e revogação.
- Aplicar controles contra SSRF, payload malicioso, resposta de ferramenta com instruções não confiáveis, abuso de conexões e retorno de dados indevidos.
- Limitar domínios/serviços de destino a allowlists versionadas; não permitir ferramentas arbitrárias definidas por URL do cliente.
- Auditoria precisa diferenciar requisição, autorização, aprovação, tentativa, execução e confirmação.
- Segregação multiempresa precisa resistir a concorrência, filas, retentativas, cache, eventos atrasados e migração de versão.

## 9. Filas, idempotência e falhas

- Para mutações, exigir chave de idempotência adequada à operação, empresa e janela contratada.
- Uma resposta perdida ou timeout **não significa** que o efeito não ocorreu. Antes de repetir, consultar o status da operação e reconciliar.
- Retentativas apenas nas classes e condições homologadas, com backoff, limite e prevenção de efeitos duplicados.
- Ações com gasto ou comunicação externa exigem confirmação do serviço proprietário antes de marcar concluídas.
- Controlar circuit breaker, fila de incidentes e encerramento seguro; eventos assíncronos devem ter IDs de correlação.
- Possíveis compensações são responsabilidade do domínio que executou a ação e devem ser explícitas.
- Se não houver confirmação de resultado, sinalizar estado indeterminado, sem inventar sucesso.

## 10. Interfaces previstas

**Central Imagine (interna):**
- Catálogo de ferramentas e conectores.
- Homologação, políticas, escopos, versões, rollout e suspensão.
- Validação de contratos, testes isolados e risco.
- Visão por produto/agente/tenant de chamadas, erros, negativas, aprovações e custo técnico.
- Auditoria, incidentes, credenciais referenciadas e políticas de descontinuação.

**Produto do cliente:**
- Exibir ferramentas/capacidades habilitadas pelo seu plano, sem acesso a segredos.
- Solicitar ativação somente de recursos previamente liberados pela Imagine e pelo produto.
- Visualizar status, motivos de restrição e aprovações pendentes.
- Não oferecer editor livre de código, URLs ou conectores sem homologação.

## 11. Dependências técnicas documentais

| Módulo | Dependência |
| --- | --- |
| AIA-01 | Agente e versão possuem capacidades elegíveis e ciclo de vida válido |
| AIA-02 | Prompt orienta, mas não substitui autorização |
| AIA-03 | Modelo tem capacidade de tool calling compatível, homologada e versionada |
| AIA-07 | Execução, orquestração, timeouts e filas |
| AIA-08 | Alçadas, risco, fluxo de aprovação e recusa |
| AIA-10 | Métricas, custos, incidentes e rastreamento |
| AIA-11 | Políticas de retenção, isolamento e auditoria |
| AIA-12 | APIs/eventos, esquemas, compatibilidade e identidade técnica |

## 12. Diretrizes AIA04-D01 a D10 — aprovadas funcionalmente

| ID | Tema | Opção A — Recomendada | Alternativa B |
| --- | --- | --- | --- |
| AIA04-D01 | Catálogo de ferramentas | Central homologada, por produto, com versões e escopos | Ferramentas livres por agente/empresa |
| AIA04-D02 | Autoridade final | Backend dono do recurso valida cada operação | Agente assume autorização com base no prompt |
| AIA04-D03 | Credenciais | Identidade de serviço de privilégio mínimo, segredos protegidos | Chave administrativa única compartilhada |
| AIA04-D04 | Risco e aprovação | Classes de risco com alçadas e bloqueio de ações críticas | Autonomia livre definida pelo cliente |
| AIA04-D05 | Integrações | Contratos padronizados e adaptadores por produto | Acesso direto e não versionado às bases |
| AIA04-D06 | Execução resiliente | Idempotência, confirmação, retry seguro e reconciliação | Reenvio irrestrito após falha |
| AIA04-D07 | Isolamento | Verificação obrigatória por empresa, produto, recurso e finalidade | Confiar em IDs fornecidos pelo modelo |
| AIA04-D08 | Interface | Gestão Imagine; cliente vê e configura só ferramentas habilitadas | Editor livre de URLs e código pelos clientes |
| AIA04-D09 | Mudanças | Versionamento, testes contratuais, auditoria, suspensão e rollback | Atualizar ferramenta em produção sem validação |
| AIA04-D10 | MVP | Ferramentas CRM somente leitura e criação controlada de tarefa no piloto | Todas as integrações simultaneamente |

**AIA04-D01 a D10: APROVADAS FUNCIONALMENTE em 2026-11-06 pelo responsável.** Esta aprovação não libera stack, endpoints, credenciais, acesso real, programação, integrações ou deploy.

## 13. Casos de teste documentais e critérios de aceite

Cenários: lead de outra empresa, instância bloqueada por plano, escopo ausente, ferramenta descontinuada, schema inválido, conteúdo malicioso em resposta de ferramenta, duplicidade de evento, timeout após execução real, aprovação expirada, indisponibilidade de API, mudança de versão e consumo de créditos excedido.

- [x] AIA04-D01 a D10 aprovadas funcionalmente em 2026-11-06.
- [ ] Matriz de riscos e alçadas conciliada com AIA-08 e regras de cada produto.
- [ ] Contratos de entrada/saída e autorização definidos com AIA-12.
- [ ] Políticas de segredos, autorização, tenants e retenção alinhadas a AIA-11.
- [ ] Mecanismos de idempotência, retentativas, confirmações e reconciliação definidos.
- [ ] Escopo do piloto CRM homologado e aceite documental concluído.
- [ ] Gates técnicos, orçamento, licenciamento e desenvolvimento aprovados em ato posterior.

**Bloqueios mantidos:** sem código de produto, testes reais, despesas, provisionamento, migrações, mudanças em outros repositórios ou deploy.

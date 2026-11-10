# CRM-AIA-RO-01 — proposta de contrato bilateral somente leitura (não homologada)

**Data:** 09/11/2026. **Base CRM DEV:** `314141874669e070c9b915b3b3715c97a737cbaf`. **Escopo: 100% documental / somente revisão.** Nenhuma API, rota, controller, grant, migration, teste Laravel ou dado real foi criado por este documento.

## 1. Origem e autoridade

O repositório Imagine AI Agents **integrou exclusivamente à sua dev** o [PR #106 — FT01/F1-12](https://github.com/rafhael100/imagine-ai-agents/pull/106), squash `562896763f900a8a264ea46aa35e2aeacd5e0903`, com o contrato sintético `SYNTHETIC_CRM_AIA_READ_V1`. O contrato lógico correlaciona-se com AIA-R04 OP-01 (`crm.lead.read_minimal`), mas **não** presume que o CRM disponha dessa API ou que os campos representem o schema comercial efetivo.

Fontes do Agents para revisão conjunta:
- [Esquema JSON sintético proposto](https://github.com/rafhael100/imagine-ai-agents/blob/dev/ft01-f1-12-crm-read-contract-synthetic-2026-11-09/contracts/crm-aia-readonly.v1.synthetic.schema.json).
- [Especificação e testes](https://github.com/rafhael100/imagine-ai-agents/blob/dev/ft01-f1-12-crm-read-contract-synthetic-2026-11-09/docs/AIA-FT01-F1-12-CRM-READ-ONLY-CONTRACT-OFFLINE.md).
- [Parser/limites da requisição](https://github.com/rafhael100/imagine-ai-agents/blob/dev/ft01-f1-12-crm-read-contract-synthetic-2026-11-09/src/MockBridge/MockCrmReadRequest.php).

**O CRM conserva propriedade sobre empresa, membership, permissões, leads, campos, alçadas, RLS e a decisão final de autorizar cada consulta.** O Agents não pode declarar ACL por conta própria.

## 2. Escopo da proposta ilustrativa

| Campo fictício | Finalidade em laboratório | Condição para contrato real |
| --- | --- | --- |
| `contract` | Versão exata `SYNTHETIC_CRM_AIA_READ_V1` | Política de versão e depreciação homologada entre times |
| `request_ref` | Correlação opaca do teste | IDs gerados por serviço autenticado, não autorizadores |
| `operation` | Única OP-01, `crm.lead.read_minimal` | Operação formal aprovada por SEG/CORE/Produto |
| `tenant_ref` | Organização fictícia | Resolução de empresa/membership em backend confiável |
| `principal_ref` | Solicitante fictício | Identidade verificada, MFA/delegação quando aplicável |
| `resource_ref` | Lead fictício | Propriedade do lead verificada pelo CRM e sem enumeração |
| `purpose_ref` | Finalidade fictícia | Escopo/finalidade e base legal estabelecidos |
| `fields` | Apenas `ref` e `stage` ilustrativos | Classificação de campos, minimização e ACL por campo |

**Nenhum dos campos anteriores é JWT, token, certificado, credencial de serviço ou `ALLOW`**. O esquema não contém dados de cliente e rejeita propriedades inesperadas. Campos `ref/stage` são exemplos sintéticos: só o CRM decidirá equivalentes operacionais.

## 3. Matriz de testes negativos que o CRM deve exigir no futuro

1. Emissor de serviço não autenticado, chave expirada/revogada ou `audience` divergente: negar uniformemente.
2. Tenant ou membership inexistente/suspenso, usuário sem direito ou vinculação inconsistente: negar.
3. Lead de outro tenant e lead inexistente: respostas sem diferenciação útil para enumeração.
4. `fields` inclui campo sigiloso, contato pessoal, anexo ou histórico não autorizado: negar por campo e não transferir payload à IA.
5. Contrato de versão desconhecida, operação de escrita, campo adicional `role/is_admin`, requisição com mutações: negar.
6. Suspensão de plano/contrato, revogação durante fila e mudanças de política: revalidar na operação, não confiar em snapshot antigo.
7. Replay entre múltiplas instâncias, não apenas memória de processo: exigir armazenamento de nonce/revogação protegido e idempotência adequada.
8. Falha no autorizador, rede ou CRM: **negar**, sem fallback automático para acesso ampliado.
9. Verificar separação de logs/auditoria por tenant sem tokens ou payload de leads.

Esses são **critérios futuros**, não testes já executados no CRM.

## 4. Evidências disponíveis no Agents (não substituem aceite CRM)

Laboratório PHP offline F1-12: **144 testes PHP passaram, 60 arquivos PHP sem erros de sintaxe** e teste ilustrativo JSON Schema com 2 entradas válidas e 18 inválidas rejeitadas, usando apenas dados fictícios. A implementação chama `MockCrmReader`, **não a API do CRM**. Hash de conteúdo não autentica origem/identidade. Nenhum teste Laravel/PostgreSQL integrado CRM↔Agents foi realizado por essa proposta.

## 5. Decisões e gates ABERTOS

Antes de qualquer endpoint operacional, requer revisão explícita:
- **SEG/IAM + CORE:** G01 identidade e autenticação de serviço; G02 ACL por tenant, membership, lead, ação e campo; CORE01/02/04/09, AUT e RLS.
- **Produto/Arquitetura:** G08 contrato/versões do CRM, ownership, nomes reais e resposta segura.
- **Privacidade/LGPD:** G05 classificação, finalidade, retenção, provedor e minimização.
- **Infra/Operação:** G03 replay/distribuição/filas, G10 segredos, rede, observabilidade e recuperação.
- **Direção:** G11 recorte piloto, custos, responsáveis, aprovação técnica e autorização específica da integração funcional.

**Regra vigente do repositório:** `DenyByDefaultAuthorizer` continua negando efeitos de negócio. `identity.experimental=false` por padrão. Não modificar `main`, PRs #2/#21, rotas, migrations, SQL/ALLOW, VPS, clientes nem produção. A autorização para estudar contratos com dados fictícios não aprova código funcional.

## 6. Próximo passo sugerido aos revisores do CRM

Atribuir responsável técnico CRM, SEG, Produto e Privacidade para validar o *contrato conceitual* OP-01; decidir campos reais, erro indistinguível, origem autenticada, tratamento de revogação, política de versão e suíte de homologação. Registrar parecer e gates por escrito. **Somente depois** abrir tarefa executiva de implementação em branch nova, com aceite explícito, CI e ambientes isolados.

**Status desta proposta:** `DRAFT / NO MERGE / NO DEPLOY`; a diretoria autorizou estudo e desenvolvimento controlado com dados sintéticos, não há aprovação deste contrato específico pelo CRM.


---

## 7. Atualização bilateral de revisão — 10/11/2026 (PROPOSTA, NÃO RATIFICADA)

**Atenção:** as evidências da seção 4 (144 casos PHP / 60 lint) registram a **F1-12 histórica** e não são o baseline atual do Agents. O repositório Agents incorporou subsequentemente [EVID-16](https://github.com/rafhael100/imagine-ai-agents/blob/dev/ft01-aia-core-mocks-2026-11-06/docs/AIA-R01-EVID-16-SEGURANCA-TOOL-397.md): **397 casos PHP, lint em 82 arquivos e verificadores Python** no laboratório, com [CI de revisão #42753478173](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753478173) e [CI pós-merge #42753536675](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42753536675) aprovadas para a última alteração funcional. Isso não prova que o CRM possua API, identidade de serviço ou permissões reais.

### 7.1 Requisição e resposta: fronteira conceitual

O contrato sintético de **pedido** F1-12 descreve os campos `contract/request_ref/operation/tenant_ref/principal_ref/resource_ref/purpose_ref/fields`. Ele não define quem autenticou o solicitante. Em Agents, o contrato sintético de **resposta pública** (EVID-10, [PR #159](https://github.com/rafhael100/imagine-ai-agents/pull/159)) usa `contract/state/projection/real_authorization/external_calls`, somente com os estados ilustrativos `READ_SYNTHETIC_ONLY` ou `DENIED_OR_NOT_FOUND`. A projeção, quando presente, só admite os campos fictícios `ref` e/ou `stage`; resposta negada devolve `projection=null`. Os marcadores `real_authorization=false` e `external_calls=0` confirmam **ausência** de API real; **jamais** devem virar atributos de autorização num contrato operacional.

O [PR Agents #173](https://github.com/rafhael100/imagine-ai-agents/pull/173) criou caminho de ferramenta read-only através da borda OP-01 sintética, preservando a recusa do leitor direto. [SEC-13/14/15](https://github.com/rafhael100/imagine-ai-agents/blob/dev/ft01-aia-core-mocks-2026-11-06/docs/AIA-R01-EVID-14-CHECKPOINT-LEITURA-OP01.md) exige owner policy no laboratório, antes do nonce, e não permite ler diretamente do despachante. Essas verificações são **simulações locais**, sem confiança distribuída, HTTP, JWT, RLS real ou autorização de recursos.

**Decisão G08 que falta ao CRM:** definir o contrato de resposta real, tipos, campos físicos permitidos, condição de ausência ou recusa, versão, transporte autenticado e responsabilidade pela correlação. Não presumir que o JSON sintético nem `ref/stage` sejam aprovados como API.

### 7.2 Matriz de decisões pendentes — nenhum item aprovado por este documento

| ID de revisão | Pergunta que o dono competente precisa resolver | Evidência mínima para fechar |
| --- | --- | --- |
| OP01-R01 / G01 / AUT | Qual identidade de serviço assinada/autenticada, emissor, audiência, tenant e principal delegados? Como expira e é revogada? | Desenho IAM, rotação e testes negativos por emissor/audience/tenant, com SEG |
| OP01-R02 / G02 / CORE/SEG | Qual autorizador do backend CRM governa lead, membership, recurso, finalidade e **campo**, independentemente de claims enviados pelo Agents? | Matriz deny-by-default, testes entre empresas, recurso inexistente/estrangeiro indistinguíveis |
| OP01-R03 / G03 / AUT/Infra | Como garantir rejeição de replay entre instâncias, conflito de nonce e rechecagem após revogação? | Protocolo de concorrência/TTL/armazenamento durável e testes multiworker |
| OP01-R04 / G05 / Privacidade | Quais dados e finalidades são realmente necessários, base legal, política de logs, retenção, IA e subprocessadores? | Classificação e avaliação de privacidade assinada; minimização aferível |
| OP01-R05 / G08 / Arquitetura | Qual é o contrato de saída HTTP/erros/versionamento e a regra de evolução/depreciação? | Schema revisado, testes positivos/negativos, owner explícito e rollback |
| OP01-R06 / G07/G10 / Segurança/Operações | Como executar homologação isolada, auditoria, incidentes e rollback sem credencial ou cliente real? | Relatório de testes em ambiente autorizado, revisão independente e restauração documentada |
| OP01-R07 / G11 / Direção | Qual o recorte do piloto, responsável, limites financeiros e gatilhos de suspensão? | Aprovação específica, sem confundir piloto com produção |

**Critério único:** nenhuma resposta de revisão, CI offline ou comentário neste PR transforma item ABERTO em GO. Cada dono competente precisa registrar decisão e evidência separadamente.

### 7.3 Estado do PR e risco de defasagem

Esta branch documental nasceu de uma DEV CRM anterior. **Antes de eventual integração documental**, reconciliar a DEV atual, revisar diff completo e checks e obter aceite independente. Manter o PR #57 **DRAFT / SEM MERGE**, como instruído pelo `AGENTS.md` CRM. A documentação não substitui o desenvolvimento funcional autorizado em tarefa futura. `DenyByDefaultAuthorizer` continua negando; sem endpoints, rotas, migrations, `ALLOW`, dados reais, segredos, VPS, custos ou produção.

**Próximo ato verificável:** parecer bilateral itemizado sobre OP01-R01..R07 e, só depois da autorização específica, proposta de implementação da OP-01 real em branch isolada com CI e plano de rollback.

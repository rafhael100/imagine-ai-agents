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

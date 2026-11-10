# AIA-R01/EVID-14 — Checkpoint canônico da fronteira de leitura OP-01

**Data de revisão:** 09/11/2026 · **Estado:** laboratório offline funcional; **integração com CRM real NÃO HOMOLOGADA / NO-GO**.

## Evidência executável mais recente

| Entrega | Resultado implementado | CI e commit |
| --- | --- | --- |
| [SEC-13 / PR #168](https://github.com/rafhael100/imagine-ai-agents/pull/168) | `MockCrmReadBoundary` sempre exige `MockCrmOwnerPolicy`; remove opção `null` e bypass anterior | [CI pós-merge #42743951923](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42743951923), dev `422bb277ecccf80f45b243a94903b63a305cd069` |
| [SEC-14 / PR #170](https://github.com/rafhael100/imagine-ai-agents/pull/170) | Política de owner verificada **antes** do consumo de nonce. Falha por grant/revogação/outage/campo não consome prova sintética | [CI de revisão #42746265495](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42746265495), dev `31ca96adcb161a0aff3d7b3764703501215cc7ce` |
| [SEC-15 / PR #171](https://github.com/rafhael100/imagine-ai-agents/pull/171) | Despachante legado não pode mais usar `MockCrmReader` diretamente; `READ` exige borda OP-01 e recusa com `READ_BOUNDARY_REQUIRED` se usado pelo atalho legado | [CI de revisão #42746731074](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42746731074) e [CI pós-merge #42746791090](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42746791090), dev `960b137ca19d2029df1bfe80ecd8d1b5f1e23669` |

**Gate atual:** 370 testes PHP, 82 arquivos PHP com lint e testes Python de contrato/isolamento/demonstração. Essas verificações atestam exclusivamente código fictício em memória; não provam IAM, transação, RLS, disponibilidade, segurança criptográfica, LGPD operacional ou aprovação de produção.

## Como continuar a revisão bilateral sem confundir escopo

A proposta [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue **OPEN/DRAFT**, sem merge e sem aceite do CRM CORE/SEG/AUT. Foi incluído [comentário técnico de revisão](https://github.com/rafhael100/imagine-crm/pull/57) sobre as diferenças entre o baseline antigo F1-12 e o atual SEC-15. Não considerar o comentário um parecer aprovado.

A integração real necessita que CRM e AIA validem:

1. Emissor/audience, identidade serviço-serviço, vínculo de empresa/usuário, expiração/revogação, anti-replay **entre instâncias** — R01-G01/G03 e CRM AUT/SEG.
2. Autorizar **no backend CRM** cada tenant/principal/lead/finalidade/campo com comportamento deny-by-default e resultados não enumeráveis — R01-G02 e CRM CORE/SEG.
3. Fixar campos reais, versionamento do contrato OP-01, retorno/erro, limites de retenção e compartilhamento de dados — R01-G05/G08 e Privacidade.
4. Executar casos de homologação real OP01-N01..N11 e OP01-P01/P02, incluindo corrida entre revogação e leitura, indisponibilidade do autorizador, segredos e telemetria; evidenciar resultado e rollback — R01-G07/G10.
5. Registrar responsáveis, limite de custo e autorização expressa de **piloto**; autorização de produção é checkpoint separado — R01-G11.

## Próxima decisão
**NO-GO para integração operacional e produção.** Continuar apenas testes e refatorações sem dados reais, credenciais, rede, SQL, API efetiva, VPS ou novos custos. As regras e resultados de SEC-13/14/15 não substituem a homologação independente. Sem modificar `main` ou considerar o CRM PR #57 aprovado automaticamente.

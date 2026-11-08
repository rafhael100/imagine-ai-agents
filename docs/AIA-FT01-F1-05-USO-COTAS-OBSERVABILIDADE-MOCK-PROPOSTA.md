# AIA-FT01/F1-05 — Medição técnica, cotas e observabilidade sintéticas | Proposta documental v0.1

> **ESTADO: F105-D01 a D10, alternativas A, RATIFICADAS EXCLUSIVAMENTE DOCUMENTALMENTE em 2026-11-08.**
>
> Data de referência: 2026-11-08. Repositório: `rafhael100/imagine-ai-agents`. Branch apenas documental: `docs/ft01-f1-05-consumo-cotas-observabilidade-mock-2026-11-08`. Base: `dev/ft01-aia-core-mocks-2026-11-06`.
>
> O responsável primeiro respondeu **“sim”** ao pedido de iniciar **somente o planejamento documental F1-05** e, depois de apresentada a matriz F105-D01 a D10 no PR #85, respondeu **“sim”** à pergunta específica de **ratificação documental das dez alternativas A**. Esta segunda aprovação é **apenas documental**: **NÃO AUTORIZA** criação de branch de código, PHP, testes F1-05, merge de documentação ou código, implantação, credenciais, compras ou integrações reais.
>
> **Gates imutáveis:** R01-G01 a G11 e CORE/SEG/AUT CRM **TODOS ABERTOS**; `main`, demais projetos, CRM/Portal, VPS/Cloudflare/n8n e infraestrutura inalterados. Sem SQL/ALLOW, API/LLM real, uso de dados pessoais, contratação, GitHub Actions, instalação de PHP/Composer, gastos ou deploy. F1-06 não autorizado.

## 1. Ponto de partida e motivação

F1-01 entregou domínio e mocks PHP puros; F1-02, coordenação supervisionada; F1-03, injeção de falhas e releitura conservadora de rastros; F1-04, catálogo versionado, concessões e despacho de ferramentas fictícias. Foram incorporados **somente na branch de desenvolvimento** (PRs #71, #76, #80, #84).

O fechamento do PR #84 incorporou a F1-04 por squash em `66006d093a878703b7dd6e41a07668280f101de5`. Evidência anterior restrita ao laboratório: **46/46 testes unitários sintéticos** e **26/26 arquivos PHP no lint** em PHP 8.4.24, com hashes conferidos. Esta evidência não comprova segurança, concorrência, autenticação, integração, custos ou operação reais.

AIA-10 (observabilidade e custos) e AIA-R03/S04 (provedores, custos, licenças e contingência), já aprovados como diretrizes nos níveis próprios, exigem separar **uso técnico**, **custo estimado/medido/conciliado**, **limites de operação**, **créditos comerciais** e **origem da prova**. O modelo `Domain/Usage.php` já representa unidades e estados técnicos, **sem saldo comercial**.

**Problema para simular:** impedir que a passagem de uma sugestão, ferramenta ou tentativa fake possa gastar unidades sem limite, confirmar custos sem evidência, duplicar contagem em eventos repetidos ou misturar consumidores de empresas diferentes. Nenhum teste local poderá afirmar garantia de quota atômica entre processos.

**Recorte recomendado:** pequeno laboratório PHP em memória de *quota, uso e alertas referenciados*, sem tarifas reais, sem moeda comercial, sem cartões, Pix, faturamento, reserva transacional ou API. Alternativas e números ilustrativos são **fixtures exclusivamente sintéticas**, não políticas de produto.

## 2. Separação de autoridades: contratos que NÃO se confundem

| Conceito | Fonte/autoridade futura | Limite de F1-05 |
| --- | --- | --- |
| Consumo técnico por tentativa `Usage` | AIA, após evidência mensurável do executor/provedor | Somente contadores e estados fictícios em memória |
| Custo estimado | Política/tabela de custo versionada, homologada futuramente | Usar unidades monetárias **fictícias** ou índices abstratos, não reais |
| Uso medido/conciliado | Fonte técnica e reconciliação confirmadas | Simular divergência de evidências, sem produzir fatura |
| Elegibilidade, créditos, saldo, cobrança, plano e excedentes | **CRM/produto proprietário** | Proibido mexer em saldo ou calcular cobrança do cliente |
| Limite técnico/alerta | Política aprovada para produto/tenant/operação | Fixture local, sem configurar quotas de clientes reais |
| Autorização de ferramenta | Produto dono + contratos reais; gates abertos | Reutilizar grants e Approval **mockados** anteriores; quota nunca autoriza |
| Auditoria/telemetria | Governança de dados e privacidade homologadas | Códigos fechados, referências sintéticas e números mínimos |

**Precedência:** F1-05 NÃO reinterpreta preço/credito comercial como tokens ou contagem técnica; não define percentuais de alerta, teto em reais, preço de fornecedor, câmbio, SLA ou ciclo de faturamento.

## 3. Componentes FUTUROS candidatos, sujeitos a autorização executiva distinta

| Componente candidato | Capacidade simulada | Não implementa |
| --- | --- | --- |
| `MockUsagePolicy` | Fixture versionada de orçamento de unidades técnicas por contexto completo, categoria e período lógico explícito | Plano CRM, cota comercial, rate limiting de produção |
| `MockUsageLedger` | Registro volátil de tentativas/uso estimado, medido e conciliado, com identidade material estável | Banco, transações, fila, deduplicação entre processos |
| `MockQuotaDecision` | Avaliar limite antes de uma **tentativa fake**: permitir condicionalmente, negar, indeterminar | Conceder permissão, reservar créditos reais, prover saldo |
| `MockUsageReconciliation` | Reconciliação sintética de eventos duplicados, tardios, faltantes e contraditórios | Fatura real, liquidação, compensação externa |
| `MockUsageAlert` | Emitir somente classificação/código mínimo de evento ou limite em fixtures, sem envio | E-mail, WhatsApp, cron, notificação real |
| `tests/Unit/usage_quota.php` | Casos negativos e regressões locais previsíveis | Actions, rede, PostgreSQL, Redis, provedor, IA paga |

**Compatibilidade obrigatória:** preferir composição sobre `Domain/Usage`, `TenantContext`, `Attempt`, `MockToolDispatcher`, `SupervisedCoordinator` e `ReadOnlyTraceReview`; **não** alterar suas assinaturas nem substituir as autoridades existentes. Diante de necessidade de modificar classe/contrato estável, interromper para ampliar escopo em autorização própria.

## 4. Invariantes de segurança para eventual implementação

1. **Quota não concede acesso:** uma decisão de orçamento fictícia `OK` jamais constitui capability, Approval ou permissão de escrita.
2. **Isolamento total:** produto, empresa, principal, finalidade, recurso, agente/versão lógica e operação devem corresponder à fixture; nenhum débito de outra empresa pode ser considerado saldo.
3. **Medição não é cobrança:** registrar utilização técnica sem produzir valor a pagar, débito Pix/cartão, crédito comercial ou chamada ao CRM.
4. **Idempotência de evento:** mesmo ID + mesma identidade material não conta duas vezes; mesmo ID com material divergente sinaliza conflito fechado.
5. **Evidência de custo:** distinguir `ESTIMADO`, `OBSERVADO`, `AJUSTADO` e `CONCILIADO` e nunca inferir conciliação pelo fim da run, status do modelo ou timeout.
6. **Cotas conservadoras:** excesso, falta de medição, plano ausente, quota desconhecida, versão inválida ou inconsistência impedem nova autorização de consumo fictício; não apagar um consumo anterior presumindo ausência de efeito.
7. **Tentativa e efeito independentes:** timeout pode ocorrer após uso técnico observado; falha de transporte não zera uso, nem o torna confirmado sem prova.
8. **Relógio e ciclo explícitos:** janelas e limites fictícios determinados por argumentos de teste; nenhuma data global, timer, fila, corrida real ou reset administrativo.
9. **Overflow e limites:** unidades negativas, grandes demais, tipos errados, parâmetros extras e estouro aritmético geram erro tipado, não contagem silenciosa.
10. **Rastro mínimo:** IDs fictícios referenciados e códigos fechados, sem prompt, lead, token real, exceção bruta, preço contratado, metadados de cliente ou stack trace.
11. **Falha de reconciliação permanece aberta:** eventos ausentes ou contraditórios retornam `INDETERMINADO`; não gerar confirmação nem reserva fictícia retroativa.
12. **Sem garantias não demonstradas:** laboratório em memória não é quota atômica entre processos, consistência durável, segurança real, auditoria legal ou conformidade operacional.

## 5. Cenários futuros candidatos U05-01 a U05-12 — NÃO EXECUTADOS

Após futura autorização executiva específica: **12 novos casos F1-05 + 46 regressões existentes = 58 testes planejados**, sem resultados de F1-05 nesta etapa.

| Caso | Entrada/cenário sintético | Aceite proposto |
| --- | --- | --- |
| U05-01 | Política de unidades válida para mesmo tenant/produto/operação | Decisão técnica permitida, sem ferramenta/Approval implícitos |
| U05-02 | Limite insuficiente ou zero para tentativa fictícia | Negação antes de novo consumo |
| U05-03 | Repetição exata do evento de uso | Nenhuma duplicação de contagem |
| U05-04 | Mesmo ID de evento com quantidade/material divergente | Conflito tipado, sem sobrescrita |
| U05-05 | Contexto de outro tenant, principal, produto ou finalidade | Negar sem expor nem somar dados de outro escopo |
| U05-06 | Ausência, revogação lógica, expiração de política ou versão errada | Bloqueio conservador |
| U05-07 | Estimativa seguida de medição técnica fictícia | Estados distintos, sem contar duas vezes |
| U05-08 | Medição tardia após timeout/falha do mock | Preservar uso observado, não inferir efeito CRM |
| U05-09 | Evidência contraditória ou incompleta em conciliação | INDETERMINADO, sem declaração de custo confirmado |
| U05-10 | Valores negativos, desproporcionais, overflow ou campos desconhecidos | Negação tipada, sem mutação de saldo |
| U05-11 | Alertas de aproximação/esgotamento simulados e deduplicados | Código mínimo, sem canal de envio |
| U05-12 | Prompt adversarial e marcadores de segredo sintéticos em fixture | Nenhum vazamento no trace, nenhuma elevação de quota |

As **46 regressões** dos lotes F1-01–F1-04 devem permanecer intactas e serem reexecutadas somente após autorização explícita de testes. A meta **58/58** é apenas critério futuro, **não evidência**.

Caso PHP CLI 8.1+ esteja previamente disponível no ambiente executante, os comandos candidatos são `php -v`, lint de todos os arquivos PHP e `php tests/Unit/run.php` no checkout exato validado por SHA. Caso não esteja, declarar testes **NÃO EXECUTADOS** no executor: sem instalação, Composer, Actions ou despesa. Um ambiente independente pode ser autorizado em ato posterior para validar hashes e execução.

## 6. Alternativas F105-D01 a D10 — ALTERNATIVAS A RATIFICADAS DOCUMENTALMENTE

| Decisão | Alternativa A RATIFICADA (somente documental) | Alternativa B NÃO adotada |
| --- | --- | --- |
| **F105-D01 — recorte** | Medição, cotas e alertas **técnicos mockados**, não cobrança | Implantar orçamento comercial/produtor real |
| **F105-D02 — autoridade** | CRM conserva créditos, planos e cobranças; AIA só simula uso técnico | AIA débita créditos ou presume plano ativo |
| **F105-D03 — isolamento** | Contexto completo e versões vinculados à política fictícia | Cota global por conveniência entre empresas |
| **F105-D04 — idempotência** | Evento material estável, duplicação sem nova contagem e conflito explícito | Registrar novamente em retries ou sobrescrever divergência |
| **F105-D05 — custos/estados** | Estimado/observado/ajustado/conciliado separados com evidências fake | Declarar custo confirmado pelo fim da run |
| **F105-D06 — falhas** | Negar novos consumos em limite/medição incertos; preservar indeterminação | Retry cego, orçamento irrestrito ou contar silêncio como zero |
| **F105-D07 — alertas/auditoria** | Códigos fechados e rastros minimizados, sem avisos reais | Notificações e payloads privados no laboratório |
| **F105-D08 — regressões** | Propor 12 U05 e preservar 46 anteriores; testes somente se autorizados | Presumir 58/58 PASS sem execução |
| **F105-D09 — tecnologia/custo** | PHP puro in-memory e relógio explícito, nenhuma dependência/infra | Banco, filas, provider, API, Actions e custos |
| **F105-D10 — governança** | Ratificação somente documental; ordem executiva e merge independentes | Interpretar aprovação documental como comando para Codex |

**F105-D01 a D10: alternativas A RATIFICADAS SOMENTE DOCUMENTALMENTE em 2026-11-08.** As alternativas B não foram adotadas. Essa decisão não autoriza criar branch de código, executar Codex, testar F1-05, fazer merge, alterar gates ou iniciar F1-06.

## 7. Branch, limites e fluxo FUTUROS

- **Base:** `dev/ft01-aia-core-mocks-2026-11-06` (F1-01 a F1-04).
- **Branch documental:** `docs/ft01-f1-05-consumo-cotas-observabilidade-mock-2026-11-08` — somente este plano e `STATUS-ATUAL.md`.
- **Branch candidata de código NÃO CRIADA/NÃO AUTORIZADA:** `dev/ft01-f1-05-usage-quotas-mocks-2026-11-08`.
- **Superfícies candidatas futuras:** `src/MockUsage/`, `tests/Unit/`, `README.md` e `STATUS-ATUAL.md`; tipos adicionais em `src/Domain/` somente após justificativa e autorização própria. Não tocar `src/Contracts/`, `AGENTS.md`, `SupervisedCoordinator`, `MockToolDispatcher`, `ReadOnlyTraceReview` nem documentos ratificados sem aprovação específica.
- Nova branch, commits PHP, execução de testes e PR de código **dependem de autorização executiva separada**. Futuro PR de código deve ser **draft, exclusivamente filha → dev, sem merge automático**. Merge pede nova autorização expressa após revisão dos SHAs, testes e riscos.
- **Sem alteração de `main`, infraestrutura, contas ou demais repositórios**. Sem SQL, ALLOW, dados pessoais reais, credenciais, APIs, integração com CRM, acesso externo, pagamentos, contratos, n8n, GitHub Actions, cloud ou deploy. F1-06 não autorizado.

## 8. Critérios de aceite DOCUMENTAL

- [x] Responsável **ratificou exclusivamente documentalmente** F105-D01 a D10 alternativas A em 2026-11-08.
- [ ] Preparar minuta executiva **separada** com escopo de arquivos, SHA de base, condições de parada e meta futura de 58 testes, sem executá-la nem presumir autorização.
- [ ] Exigir aprovação específica para programação e testes F1-05, e outra aprovação para merge exclusivo em dev.
- [ ] Manter `main`, outros produtos, infraestrutura e todos os gates R01-G01–G11 e CORE/SEG/AUT CRM **ABERTOS**.
- [ ] Não declarar custos reais nem aptidão operacional a partir de simulação.

**Próximo ato:** preparar e apresentar **minuta executiva F1-05 separada**, para decisão posterior. A ratificação documental não constitui ordem executiva. Nenhum código ou teste F1-05 foi autorizado ou executado.

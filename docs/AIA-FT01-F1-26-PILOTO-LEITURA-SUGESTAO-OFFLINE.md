# FT01/F1-26 — Fluxo integrado de leitura mínima e sugestão, exclusivamente sintético

**Data:** 09/11/2026. **Estado:** laboratório PHP offline / NO-GO operacional.

## Escopo executável
O `MockReadOnlyPilot` conecta **em memória**, em um único processo e sem rede, componentes já existentes do Agents:

1. Verifica a igualdade com um `TenantContext` **fictício**, vinculado ao piloto. Não usa o corpo da requisição como prova de identidade.
2. Consulta o `MockCrmReadEdgeBoundary`, que aplica schema fechado, envelope/owner sintéticos, política do produto dono, auditoria, telemetria e retorno público minimizado.
3. **Somente após leitura permitida**, abre um `Run` sintético, cria passo, repassa exclusivamente `ref`/`stage` da projeção autorizada ao provedor e gera sugestão fixa.
4. Verifica a estrutura da sugestão, marca apenas a Run como concluída e devolve resposta pública sem identificadores, metadados sensíveis ou conteúdo recebido.
5. Qualquer falha resulta em `DENIED_OR_NOT_FOUND` uniforme, sem iniciar Run para leitura negada. Não chama `execute`, `submit`, `status`, banco, CRM ou provedor de IA real.

**Fronteira reforçada:** o `MockAiSuggestionProvider` deixou de chamar `MockCrmReader` internamente. A camada de sugestão deve apenas consumir a projeção previamente filtrada pelo produto dono; não fazer uma segunda leitura independente que contorne a política da borda.

## Regressões de ponta a ponta U30-01..12

- Autorização mínima e projeção de dois campos na ordem solicitada, sem escrita.
- Ausência de grant, envelope ou evidência do owner e substituição de empresa/contexto.
- Nonce usado duas vezes, campo não permitido, identificador de execução inválido.
- Auditoria cheia nega um segundo pedido antes de produzir qualquer nova sugestão.
- Duas leituras independentes com nonces e Runs distintos, sem reutilização de execução e sem chamadas mutáveis.

**Gate oficial revisado:** 351 casos PHP e 81 arquivos PHP com lint, incluindo o novo módulo e a suíte. A CI de contratos Python, incluindo [EVID-10](AIA-R01-EVID-10-CONTRATO-RESPOSTA-PUBLICA-OP01.md), continua obrigatória. Estas metas só são evidência após CI `success` no SHA exato.

## Limites e dependências críticas

- `TenantContext`, envelope, owner, ACL e dados são **100% falsos**. Isto não é um produto operável e não demonstra IAM, token, revogação distribuída, ACL/RLS reais ou conformidade LGPD.
- O piloto real OP-01 continua dependente do [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) e aceites formais de G01/G02/G05/G08/G10/G11 e CRM CORE/SEG/AUT, além dos demais gates R01.
- Proibida a inferência de que uma `Run` concluída confirme efeito CRM. O piloto é **somente leitura e sugestão**, sem OP-05/OP-06.
- Nenhum endpoint, rede, fornecedor, banco, dado pessoal real, credencial, `main`, VPS, compra ou produção foi ativado por este fluxo.

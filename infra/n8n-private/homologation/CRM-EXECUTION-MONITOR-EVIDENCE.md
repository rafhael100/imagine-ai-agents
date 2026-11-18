# Evidência do n8n no modelo de acompanhamento do CRM

## Objetivo

A [PR CRM #654](https://github.com/rafhael100/imagine-crm/pull/654) acrescenta a
tela privada de acompanhamento e um leitor específico do histórico persistido.
Sua CI usa sessão, MFA, PostgreSQL e navegador reais com fixtures explícitas de
apresentação. Este incremento liga o leitor e o modelo PHP dessa candidata às
observações produzidas pelo ensaio real de execução da
[PR Agents #472](https://github.com/rafhael100/imagine-ai-agents/pull/472).

O ensaio permanece sintético e descartável. A execução nativa, a troca HTTPS
assinada e a persistência são reais dentro dele; empresas, leads, identidades,
certificados e decisões do responsável são fixtures de CI.

## Fontes exatas

| Componente | Commit de referência |
| --- | --- |
| Base Agents #472 | `8e7f1f21a2927bde3e337edaace0e1e6dffdc29a` |
| Domínio Agents #468 | `05e4ea7942e530055f4e455544d82f5d3edbd56d` |
| CRM #654 | `e1b3b7f118eff0350a00789aecc7bb2bb2331464` |

O workflow verifica as referências antes de criar a árvore temporária e as
imagens. O novo probe compara os arquivos PHP instalados com a origem pinada;
não recebe uma cópia simplificada do parser. Os resultados efetivos pertencem
à HEAD e à árvore registradas no run da PR, sem antecipar aprovação da CI.

## Duas provas complementares

| Prova | O que verifica |
| --- | --- |
| CRM #654 | Jornada humana privada: sessão/MFA, conta e vínculo ativos, escopo da empresa, acesso HTTP, Blade, idiomas e navegador |
| Este ensaio Agents | Compatibilidade do SQL e `ExecutionMonitor::read` com o histórico obtido por mTLS após a execução nativa do n8n |

O banco CRM deste laboratório escuta na porta interna 5433 para permanecer
separado do banco Agents. O ensaio usa o parser puro e a função SQL; não modifica
o perfil de identidade do CRM, que continua exigindo sua configuração privada
própria. Um contexto de empresa definido pelo harness não é apresentado como
autenticação humana nem como autorização para consultar um cliente real.

## Leitura e recuperação

O probe de acompanhamento recebe somente a credencial sintética
`crm_homologation_app`. A conexão e a função são fixas, e a leitura ocorre em
transação `READ ONLY`, com contexto de empresa local à transação. Certificados,
chaves, senhas do emissor/reconciliador e credenciais do Agents não são montados.
A role web conserva seus direitos comerciais anteriores; a alegação de somente
leitura refere-se à transação e à nova função, não a toda a role.

O mesmo ciclo já existente produz o evento, o retorno assinado e as seis
observações nominais. O novo leitor deve acompanhar a ausência de retorno,
`NOT_STAGED`, os dois estados de fila de `NOT_STARTED`, `STARTED`, `RESULT` e
`ACKED`. O ramo `UNKNOWN` permanece sem repetição automática. A leitura não
acrescenta chamada nativa, reconciliação ou escrita aos registros do ensaio.

Revogar o grant de acompanhamento S2S deve bloquear novas consultas remotas e
preservar a leitura histórica local no contexto sintético autorizado. O modelo
retornado contém somente os campos de apresentação; recibos, assinaturas,
hashes e chaves continuam fora dele.

A restauração em outro cluster PostgreSQL 16 conserva o perfil opcional
`synthetic-private-s2s-execution-monitor-v1`, os 33 conjuntos de registros,
owners, grants, funções, FORCE RLS e roles `NOLOGIN`. A leitura restaurada usa
`SET LOCAL ROLE` dentro de transação de leitura; não reativa logins de serviço.
O modelo deve corresponder ao original, desconsiderando apenas o instante de
geração da consulta, inclusive depois das revogações posteriores ao snapshot.

## Limites

**DRAFT / NO-MERGE / NO-DEPLOY.** A evidência complementar não constitui login
humano, teste HTTP da nova tela, aceite visual de uma pessoa, autorização
operacional, backup externo ou instalação na VPS. O ciclo continua com uma
execução nativa no ensaio de retorno; as regressões anteriores têm suas próprias
execuções e não são somadas como se fossem uma única chamada. `PREPARED` mantém
o significado técnico, sem alteração comercial do lead ou chamada de IA paga.

Contrato de retorno:
[CRM-EXECUTION-FEEDBACK](CRM-EXECUTION-FEEDBACK.md).
Critérios operacionais:
[#414](https://github.com/rafhael100/imagine-ai-agents/issues/414).

# P26 — execução nativa e conciliação do outbox no laboratório

O driver `infra/n8n-private/homologation/check_p26_native_outbox_execution.py`
exercita o domínio Agents RC65 na mesma árvore exportada da Central. Requer o
arquivo histórico `_auto_ia_pinned_source` que o runner combinado já exporta.
Só executa no GitHub Actions hospedado, repositório esperado, Docker local,
n8n fixado por digest, PostgreSQL 16 do Agents sem rede e PostgreSQL 17 do n8n
em rede interna. Reutiliza `Stack` de P24 e `AgentsRecovery` de P25.

## Operação e autoridade

A entrada é o contrato original `SYNTHETIC_AIA_AUTOMATION_V1`, evento
`mock.run.completed`, mapeado pelo adaptador original para `run.status.project`.
P42/P44 fazem staging com decisão Ed25519 do proprietário sintético e P45
reserva a lease. P26 não modifica o domínio original nem interpreta
`lead.ingest` como autorização dessa operação. O P19 continua devolvendo
recebimento durável com `dispatch: DISABLED`.

O fluxo inativo tem apenas Manual Trigger → Set → Set. Projeta estado técnico
`COMPLETED` e devolve as referências de tenant, evento, run, recurso, worker,
attempt, hash do evento e hash do template. Não carrega payload comercial,
credenciais, nós Code/HTTP, mensagens, provider ou regras do CRM. P26 verifica
o template fixado, reconstrói o grafo materializado também em PHP, verifica o
export real do n8n e confere cada resultado nativo antes de assinar V2.

O registro P7 desta prova usa a função original `admit_nonce` com dados
sintéticos: é fixture de admissão no banco, não uma nova prova de mTLS. As
chaves de proprietário e executor são geradas só para a execução da CI e
entram no PHP por stdin protegido. A chave privada nunca entra no n8n ou no
banco. O resultado é observado pelo runner confiável; não há atestado
independente de identidade/custódia de chave de um executor de produção.

## Conciliação durável

| Estado | Significado e próxima ação |
| --- | --- |
| Ausente | Uma lease sozinha não inicia n8n. `begin` revalida tenant, empresa, grant atual, evento e attempt sob locks. |
| `STARTED` | Intenção gravada antes da chamada. Outro `begin` não pode executar. Recuperação sem resultado torna `UNKNOWN`. |
| `RESULT` | Resultado verificado e recibo V2 assinado já persistidos. Recuperação chama somente o ACK original, usando a lease e autoridade atuais. |
| `ACKED` | ACK do outbox e evidência P38 já confirmados na transação original. Repetição apenas confirma esse fato histórico. |
| `UNKNOWN` | Resultado ausente/inválido depois da intenção. Exige conciliação explícita; nunca há retry automático. |

O vínculo persistido inclui empresa, tenant, evento, hash do evento, worker,
attempt, revisões de autoridade, signer, template, identificador e hash exato
do grafo. A lease permanece de 60 segundos. O recibo V2 dura 60 segundos,
dentro do limite original de 120; todos usam `time()` atual. O código não
renova a lease, o recibo ou o grant para fazer uma recuperação tardia passar.
Uma negativa de ACK conserva `RESULT` sem nova execução. Se o processo morrer
depois do commit atômico ACK+evidência e antes de atualizar o ledger auxiliar,
a conciliação reconhece a evidência exata e completa apenas esse ledger.

Revogação depois da lease e antes de `begin` impede a chamada. Revogação após
`STARTED` não cancela atomicamente o processo externo: pode haver resultado
real observado com ACK negado, que fica preservado para auditoria. Nenhum
campo de sucesso é fabricado para fechar essa lacuna.

As classes históricas retornam razões como `NO_REAL_N8N_EXECUTION_OR_IAM` e
contadores de efeito zero. Esses valores descrevem o laboratório original e
continuam intactos. O relatório P26 registra separadamente a execução real
observada do n8n e mantém `real_owner_iam=false`, custódia real não verificada
e `go_for_installation=false`.
O Compose preserva `EXECUTIONS_DATA_SAVE_ON_SUCCESS=none`. A contagem de duas
execuções representa retornos reais verificados da CLI pelo runner, não um
contador durável de execuções no banco do n8n. O relatório explicita
`execution_observation_source=trusted_ci_cli_output` e
`n8n_durable_execution_count_verified=false`; a evidência durável desta prova
é o resultado/recibo no ledger P26 e o ACK/evidência atômicos P38.

## Verificação e interfaces

O driver importa seis workflows fechados e executa dois: o primeiro produz
um ACK/evidência únicos; o segundo produz resultado real cujo ACK é negado
após revogação. Os demais comprovam resultado de outro evento negado,
falha/perda após intenção em `UNKNOWN`, revogação pós-lease com zero chamadas
e attempt antigo negado após nova reserva. Uma injeção explícita de expiração
da lease verifica preservação do resultado sem retry; não altera o relógio
do runtime. Repetições e reinício após resultado não executam n8n novamente.
Também são injetados o atraso do ledger auxiliar depois do ACK/evidência já
confirmados e um V2 assinado corretamente com validade expirada. A conciliação
repara o primeiro caso pela evidência exata e nega o segundo, preservando o
resultado sem nova chamada. Esperas por locks são limitadas e `begin` verifica
novamente o relógio depois de adquirir os locks, antes de gravar a intenção.

```sh
python3 runtime/central-auto/lab-executor/test_native_contract.py
php -l runtime/central-auto/lab-executor/PgNativeExecutionLedgerLab.php
php -l runtime/central-auto/lab-executor/probe.php
# Somente no job isolado com os digests/variáveis do runner combinado:
python3 infra/n8n-private/homologation/check_p26_native_outbox_execution.py
```

O probe é CLI, não um endpoint. `init`, `stage`, `lease`, `begin`,
`record-result`, `reconcile` e `stats` recebem/retornam objetos JSON. Os modos
`revoke-owner` e `fixture-*` são exclusivamente injeções desta
prova, dentro do cluster descartável. Nenhum arquivo daqui integra a imagem
ou rota do receiver P19 por padrão.

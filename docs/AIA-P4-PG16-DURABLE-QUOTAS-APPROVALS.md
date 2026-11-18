# Agents P4 — cota e aprovações duráveis em PostgreSQL16 de laboratório

**16/11/2026 — Desenvolvimento supervisionado, sem GO para produção ou dados reais.**

A bancada comercial P0/P1 tem uma interface navegável e o domínio PHP P2 funciona em memória; o Docker P3 distribui somente HTML estático. O pacote P4 resolve um problema técnico concreto sem ativar integrações: **provar que consumo de cota, idempotência e decisões humanas continuam corretos quando são feitos por processos PHP independentes usando um banco compartilhado real**.

A classe `tools/labs/PgSalesWorkbenchLab.php` é propositalmente um adaptador **somente de laboratório**, fora de `src` e inacessível como serviço. Requer `GITHUB_ACTIONS=true`, `GITHUB_REPOSITORY=rafhael100/imagine-ai-agents`, `DB_HOST=127.0.0.1`, `DB_DATABASE=imagine_agents_ci` e flag explícita. O banco PostgreSQL16 do GitHub Actions é criado e eliminado pelo runner. Sem MySQL produtivo, Laravel, usuário real, serviços de terceiros ou endpoint.

## Provas físicas

- Cada empresa fictícia possui linha de cota sob `SELECT ... FOR UPDATE`, impedindo que duas transações concedam o mesmo número de execução.
- `request_ref` e hash SHA-256 do contexto sintético preservam idempotência: repetição da mesma solicitação devolve `REPLAY`, sem consumir novo crédito; tentativa de reutilizar o ID com payload diferente é recusada.
- Foram preparados **12 processos PHP independentes simultâneos** disputando 7 posições restantes após uma primeira execução (limite total 8). É obrigatório obter exatamente 7 commits, 5 negativas e índices de execução únicos.
- O evento e a aprovação `PENDENTE` são inseridos na mesma transação que incrementa a quota. Falhas geram rollback, sem reenvio e sem chamadas a IA.
- Uma decisão `APROVADO_EM_ENSAIO` ou `REJEITADO_EM_ENSAIO` só pode concluir uma vez a aprovação, rejeitando reenvio, troca de empresa e decisão inválida.
- O mesmo identificador de solicitação na empresa B tem orçamento e aprovação próprios. Falha/ausência de tabelas resulta em negativa, sem fallback para contador em memória.
- Todas as entradas/empresas/recursos possuem nomes explicitamente sintéticos; nenhum dado de lead real, observação ou credencial de cliente é armazenado.

## Limites e passos posteriores

Este recorte **não é código de produção**: a conexão do teste usa o superusuário do PostgreSQL16 efêmero; roles operacionais de mínimo privilégio, isolamento RLS por empresa, autenticação do executor, renovação de tokens, consentimento, auditoria LGPD, backup/restore, filas e liberação do provedor de IA continuam pendentes. Um hash de contexto fictício não autentica identidade; a presença de uma decisão aprovada não autoriza alterar CRM/n8n. A quota de oito execuções é apenas limite do laboratório, **não representa a grade comercial de créditos de IA**.

É vedado ligar a classe a uma rota pública ou implantar na VPS sem revisão SEG/CORE/DBA/IAM e validação das decisões e testes da [PR CRM #57](https://github.com/rafhael100/imagine-crm/pull/57). Nenhum merge ou deploy é parte desta entrega.

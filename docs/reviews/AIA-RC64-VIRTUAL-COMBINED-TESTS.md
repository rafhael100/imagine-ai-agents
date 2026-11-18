# AIA-RC64 — Ensaios de código combinado sem merge no repositório

16/11/2026. Continuação da RC63 (#453), apoiada em RC62 (#448) e CENTRAL-AUTO-P20 (#451).

A RC63 confirmou, no SHA revisado, que as trilhas de Agents e Central não modificavam os mesmos caminhos e que um ensaio git merge-tree não detectou conflitos textuais. A RC64 executa a etapa seguinte **sem criar branch/commit de merge**, usando o objeto Git de árvore virtual resultante apenas em um diretório temporário do GitHub Actions, apagado ao fim.

## O que o job faz de fato

1. Checkout read-only da RC64 com histórico completo e do Central P20 em SHA fixo 3cf31b78f745d539425b46b754edbe3c48e9d088.
2. Transfere objetos Git apenas entre os dois checkouts locais e executa git merge-tree --write-tree --name-only. Conflitos geram falha fechada, não resolução silenciosa.
3. Exporta a árvore virtual pelo git archive para um diretório efêmero; confirma presença dos arquivos de domínio Agents P48–P60 e infraestrutura Central P19/P20.
4. No **mesmo conjunto de fontes**, executa a bateria original Central P20 de materiais mTLS e empacotamento criptográfico de snapshot fictício, e os testes PHP/Python dos motores Agents P48, P49, P50, P53, P60, além de lint do receiver original.
5. Elimina a árvore temporária via trap, inclusive se algum teste falhar.

**Escopo e limites:** não há uma requisição CRM real, chamadas S2S, credenciais, PostgreSQL operacional, n8n ativo ou integração da autorização real. Os checks selecionados não substituem a CI RC62 com PostgreSQL16 nem o ensaio real P19 #636 das aplicações isoladas. O objetivo é provar que as ferramentas e os contratos de domínio podem ser carregados e executados conjuntamente no mesmo snapshot Git antes de iniciar uma integração supervisionada.

Este teste não implanta nenhum Compose na VPS, não cria contas, não baixa dados de clientes, não modifica main/dev, não cria merge commit nem autoriza custos. A cadeia operacional continua sujeita às issues de homologação, revisão independente e aceites.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**

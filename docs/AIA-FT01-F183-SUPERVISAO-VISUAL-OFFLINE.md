# FT01/F183 — supervisão de filas integrada ao laboratório navegável

**11/11/2026. Visualização exclusivamente sintética, offline e sem integração operacional. NO-GO.**

## Avanço de produto

A prévia já existente `examples/f108-laboratorio-navegavel.html` ganhou uma quinta página acessível pela navegação lateral: **Supervisão da fila**. Em vez de abrir mais um produto fictício separado, esta aba reúne a distribuição de tarefas da fila e as etapas de revisão de incertezas usando um **único JSON incorporado** ao HTML com o contrato `SYNTHETIC_QUEUE_SUPERVISION_V1`, gerado como fixture a partir da saída real do exemplo PHP F182.

A visão oferece métricas de concluídas, indeterminadas, revisões pendentes e revisões em conflito, além das tabelas de seis estados de fila e quatro categorias de revisão. A interface responde à navegação existente, conserva foco acessível nos botões e colunas de tabela com `th scope=row`. Layout adapta-se aos mesmos breakpoints já usados no laboratório F108. Nenhum botão autoriza integração, reenvio, mutação ou alteração de revisão.

**É um snapshot local estático, NÃO uma consulta ao CRM nem dashboard em tempo real.** As contagens só refletem três casos de teste indeterminados e uma conclusão de laboratório. `REVIEWED_UNRESOLVED` não reduz `indeterminate`; a página exibe o aviso NO-GO. Não há PII, referências de Runs, claims ou nomes de revisores **no novo JSON de supervisão**.

## Como testar

```bash
# Navegue localmente pelo arquivo HTML, sem servidor:
# examples/f108-laboratorio-navegavel.html

# Com runtimes disponíveis, sem instalar nada:
node tests/Browser/f108_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f183_supervision_tab.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=97 EXPECTED_CASES=776 bash tools/run_r01_offline_quality_gate.sh
```

O gate offline executa **obrigatoriamente** seis testes Python: paridade exata da fixture JSON HTML com o CLI F182, estrutura/navegação, IDs das métricas, ausência de conexões e armazenamento, preservação de incerteza e dados minimizados. Se Node já estiver instalado, também executa o **smoke DOM F108** (sem navegador externo) com nova aba, navegação, NO-GO e as métricas. Se Node não estiver instalado localmente, a prova DOM opcional é declarada como *skipped*, sem instalação automática; os testes Python continuam obrigatórios.

**Baseline PHP permanece 776 casos e lint 97 arquivos PHP**, já que o incremento é HTML/JS, Node smoke e Python. A CI no SHA exato do PR e a CI pós-merge devem passar antes de consolidar evidências; não equivale a teste de navegador real.

## Limites e dependências

O laboratório F108 conserva seus próprios dados fictícios históricos; nenhuma interpretação de teste visual ou JSON com valores estáticos demonstra acesso do cliente, uso seguro em produção, IAM, autenticação de revisor, ACL/RLS, persistência, fila distribuída, autorização CRM ou revisão de segurança independente. CRM PR #57 OPEN/DRAFT, 13 homologações reais OP-01 `NAO_EXECUTADO`; R01-G01..G11/CORE/SEG/AUT **NO-GO**. Não tocar `main`, VPS, produção, credenciais, dados pessoais reais ou despesas.

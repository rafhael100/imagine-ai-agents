# AIA-R01/EVID-05 — Painel OP-01 determinístico a partir de CSV

**Situação vigente (09/11/2026):** [PR #127](https://github.com/rafhael100/imagine-ai-agents/pull/127) integrado somente à dev, squash `44bab6e8a1c01bc6b30761018fe6271b94374bd6`. [CI #42628509823](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42628509823) PASS em head exato `5e3f0485d03a47d084332837d39f7fafe3169f75`, incluindo regressão 228/228 PHP, 73/73 lint oficiais, probe e testes negativos do gerador. **NÃO autoriza acesso real, homologação CRM ou produção.**

## Problema que esta entrega resolve

O painel EVID-04 armazenava uma cópia HTML das 14 linhas de gates e 13 linhas de cenários OP-01. Editar o CSV exigia alterar manualmente o HTML, com risco de esquecimento ou de divergência documental. A verificação anterior detectava diferenças, mas não fornecia geração reproduzível.

## Solução sem rede e sem dependências

- `examples/r01-op01-readiness-offline.template.html` preserva a interface existente com marcadores `{{GATE_ROWS}}` e `{{CASE_ROWS}}`; não contém linhas inventadas.
- `tools/generate_op01_dashboard.py` usa exclusivamente a biblioteca padrão Python. **Antes de renderizar**, chama o validador `check_r01_readiness.audit`, que mantém 14 gates documentais e 13 cenários sem alegar encerramento ou execução real.
- Campos CSV são tratados como texto não confiável e escapados para HTML, inclusive atributos. Um `<img>`, evento JavaScript ou conteúdo interpolado não vira elemento executável.
- Com `--check` (padrão) compara os bytes gerados com a página publicada e falha se houver divergência. Com `--write`, regenera somente o arquivo HTML local; não edita CSV, contrato, aceite ou repositório CRM.
- O painel não consulta internet, API, usuários, tokens ou banco e mantém `NO-GO` por projeto.

## Comandos reprodutíveis

```bash
python3 tools/check_r01_readiness.py
python3 tools/generate_op01_dashboard.py --check
python3 -m unittest discover -s tests/Contracts -p test_op01_dashboard_generator.py -v
# Regenerar apenas depois de editar os CSVs e revisar os valores:
python3 tools/generate_op01_dashboard.py --write
python3 tools/check_op01_readiness_dashboard.py
```

O workflow `r01-op01-generated-dashboard-offline.yml` exige esses comandos, as suítes anteriores de prontidão e paridade, além da regressão PHP 228/228 e 73 arquivos lint, mais o probe individual. Testes exigem rejeição de gates autoaprovados, linhas ausentes, marcadores inválidos, HTML/atributos não confiáveis, células com fórmulas e snapshot desatualizado.

**A CI de SHA exato do PR foi aprovada; novo workflow unificado EVID-06 também passou na dev (run #42629209058).** EVID-05 não fecha R01-G01..G11 ou CRM CORE/SEG/AUT; PR CRM #57 continua rascunho. Nenhum gasto, servidor, cliente, API, SQL, `main` ou deploy.

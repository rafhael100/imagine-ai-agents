# AIA-R01/EVID-05 — Painel OP-01 determinístico a partir de CSV

**Situação:** revisão técnica em branch isolada; sujeito à CI do commit exato. **Não autoriza acesso real, homologação CRM ou produção.**

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

**O status da CI permanece pendente até validação do SHA do PR.** EVID-05 não fecha R01-G01..G11 ou CRM CORE/SEG/AUT; PR CRM #57 continua rascunho. Nenhum gasto, servidor, cliente, API, SQL, `main` ou deploy.

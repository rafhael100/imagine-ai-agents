# FT01/F186 — coleção local de até cinco rascunhos de agentes

**11/11/2026. Só navegador, dados fictícios, sem API, armazenamento permanente ou execução real. NO-GO.**

## Funcionalidade

A prévia F185 agora permite **adicionar o rascunho validado à lista da sessão**, reabrir um item da lista, excluir um item e zerar toda a coleção. As cinco vagas são **estritamente em memória**; atualizar a página perde todas as alterações. O formulário, catálogo e cenários permanecem fechados (CRM, Performance, Universidade).

- Capacidade **5 rascunhos**, sem apagar o mais antigo quando o limite é atingido. Um sexto item é negado e o contador permanece 5/5.
- Nomes são comparados sem diferenciação de maiúsculas/minúsculas em `pt-BR`. Repetir o mesmo nome e o mesmo modelo é idempotente, sem segundo registro. Reutilizar o nome para modelo diferente é recusado, sem sobrescrever o primeiro.
- A coleção salva apenas `{name, templateKey, lookup}` em um array JavaScript e congela cada registro. **Não salva prompt, credencial, token, tenant, lead ou texto de clientes.**
- Para salvar, o rascunho precisa ter sido pré-visualizado e os valores ainda precisam corresponder **exatamente** ao nome e modelo normalizados da prévia. Isso também impede alterações silenciosas no DOM sem disparar eventos de `input/change`.
- **Retomar** reconstrói a prévia a partir do catálogo permitido; o botão da lista só funciona enquanto aquele registro ainda existe. **Excluir** descarta a entrada; caso esteja sendo visualizada, a prévia também é invalidada. **Zerar lista** limpa os cinco itens e a prévia ativa.
- As linhas criadas dinamicamente usam apenas `document.createElement` e `textContent`; não há `innerHTML`, código dinâmico, rede, cookies, localStorage ou escrita em arquivos.
- Abrir o cenário vinculado **não executa** nenhuma operação. Um rascunho na coleção não pode ser publicado, aprovado ou enviado a terceiros.

## Verificações

`tests/Browser/f108_smoke.cjs` agora testa criação, replay idempotente, conflito case-insensitive, capacidade 5, sexta tentativa negada, alteração de campo sem evento, reabertura, remoção, impossibilidade de reutilizar botão de uma linha removida, vaga liberada, limpeza total e auditoria não alterada.

`tests/Contracts/test_f186_draft_collection.py` reúne sete testes Python obrigatórios de capacidade, conflitos, estado antigo, acessibilidade e renderização segura, cobertura no DOM smoke, isolamento de I/O e NO-GO.

```bash
node tests/Browser/f108_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f186_draft_collection.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=97 EXPECTED_CASES=776 bash tools/run_r01_offline_quality_gate.sh
```

**Baseline PHP inalterado: 776 testes / lint 97.** O incremento é inteiramente de UX, JavaScript, Python e documentação; requer CI exata de PR e pós-merge.

## Limites não resolvidos

O registro não representa um cadastro multiempresa persistente no SaaS, não cria agentes em um banco, não tem IDs autoritativos, versionamento, roles, autenticação, revisão humana nem deploy. Não é isolamento de tenant real; collation/nome Unicode entre cliente e servidor precisará de um contrato formal antes do cadastro operacional.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT; 13 cenários OP-01 reais `NAO_EXECUTADO`; gates R01-G01..G11/CORE/SEG/AUT **NO-GO**. `main`, VPS, segredos, produção, clientes e despesas intocados.

# FT01/F184 — ensaio interativo e descartável de segunda observação

**11/11/2026. Apenas protótipo HTML/JS offline e dados fictícios. Operação real: NO-GO.**

## Produto implementado

A aba **Supervisão da fila** da prévia navegável F108 (F183) passa a oferecer um pequeno **ensaio interativo**, separado do relatório estático F182. O cenário inicia com três jobs `INDETERMINADO` e um caso fictício aguardando segunda observação.

- **Simular concordância:** uma segunda observação concorda com a primeira. O contador hipotético `awaiting_second` passa de 1 para 0; `reviewed_unresolved` de 1 para 2. O total de resultados `INDETERMINADO` permanece **3**.
- **Simular divergência:** `awaiting_second` passa a 0 e `conflict` de 1 para 2; `indeterminate` continua **3**.
- **Reiniciar ensaio:** limpa a opção e repõe as contagens simuladas originais, sem reiniciar qualquer Run real.
- Após uma escolha, ambos os botões de decisão ficam desabilitados. Tentativas repetidas, mesmo por chamada direta do listener, não adicionam outra observação fictícia. A mensagem de estado é acessível com `aria-live=polite`.

**Separação importante:** o JSON `SYNTHETIC_QUEUE_SUPERVISION_V1` e as métricas originais de F183 **não são modificados**. O JS gera uma cópia das contagens de revisão em memória e verifica o invariante `awaiting_first + awaiting_second + reviewed_unresolved + conflict == indeterminate`. A interface de exemplo só apresenta números e motivos fechados, nenhum ID de lead, revisor ou Run. Não guarda eventos de revisão no back-end e não registra identidade real.

## Testes

O smoke Node `tests/Browser/f108_smoke.cjs` exerce ambas as opções, bloqueio de segunda decisão, restauração pelo reset, permanência da incerteza, imutabilidade do snapshot e do relatório de auditoria histórico. Os **seis testes obrigatórios Python** em `tests/Contracts/test_f184_review_sandbox.py` exigem a paridade exata com F182, controles únicos e acessíveis, invariante de incerteza, idempotência simulada, cobertura DOM e ausência de rede/persistência.

```bash
node tests/Browser/f108_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f184_review_sandbox.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=97 EXPECTED_CASES=776 bash tools/run_r01_offline_quality_gate.sh
```

O teste DOM Node só é executado quando esse runtime já está disponível; Python, PHP e os gates sintéticos seguem obrigatórios. **Gate PHP permanece em 776 testes / lint 97**, pois nenhuma linha PHP foi alterada.

## Pendências que não podem ser omitidas

A UI **não** autentica revisores, não faz segregação de funções, não lê dados CRM, não registra auditoria operacional e não integra com o livro PHP REV-01: é uma hipótese visual de duas observações. Não é autorização de uso, homologação, evidência de revisão humana independente, mock de autenticação pronto para produção ou dashboard vivo. CRM PR #57 continua OPEN/DRAFT, 13 cenários OP-01 reais `NAO_EXECUTADO`, R01-G01..G11/CORE/SEG/AUT **NO-GO**. Sem mudanças em `main`, VPS, produção, clientes, segredos ou despesas.

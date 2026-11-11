# FT01/F198 — Testes adversariais do contrato visual F196

**11/11/2026. Laboratório offline. Produção: NO-GO.**

## Problema coberto
A validação do novo painel F196 já recusava estruturas ou indicadores inesperados, mas o smoke DOM cobria principalmente o caminho de sucesso. O contrato deve continuar falhando fechado se o JSON embutido na prévia visual for alterado ou adulterado.

## Implementação
O arquivo `tests/Browser/f196_tamper_smoke.cjs` extrai **o verificador JavaScript da própria página F108**, e não uma segunda implementação. Executa a função em uma sandbox Node sem rede, com elementos de tela fictícios. Reproduz o payload F190 válido e submete 16 variações hostis, incluindo chaves extras, status GO falso, HTML injetado, booleano usado como contador, revisor identificado, falta de snapshot, ocultação de trabalho indeterminado e escrita indevida.

Cada variação precisa falhar **antes** de preencher o estado visual. Os casos positivos exigem PAUSED/OPEN, contagens corretas e NO-GO. Cinco testes Python garantem cobertura do arquivo e sua execução pelo quality gate. O smoke Node é chamado quando Node já está disponível, sem instalar dependências.

```bash
node tests/Browser/f196_tamper_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f198_tamper_gate.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

Mantém baseline PHP **835 testes / lint 103** e a interface do usuário sem alterações. É somente um teste do laboratório local, **não comprova IAM nem isolamento operacional**. CRM PR #57 OPEN/DRAFT, 13 cenários reais OP-01 NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO. Sem main, deploy, VPS, cliente, credencial, dado pessoal real ou custo novo.

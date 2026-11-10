# FT01/SEC-42 — Matriz determinística adversarial do parser OP-01

**Data:** 10/11/2026. **Natureza:** teste local de contrato sintético; sem CRM real, rede, API, credenciais ou acesso a clientes. **Decisão operacional: NO-GO.**

## Por que isso é necessário

A F163/EVID-25 ensaiou 13 cenários de segurança do consumidor com objetos fictícios, mas o contrato de entrada também precisa resistir a combinações de campos, referências, comprimentos e tipos não antecipados. Um teste de poucos exemplos não verifica que uma mudança no parser não aceitou acidentalmente `role`, `is_admin`, campos pessoais, strings inválidas ou variações da ordem das chaves.

## Implementação

O arquivo `tests/Contracts/test_op01_parser_mutation_parity.py` cria uma matriz **determinística** de centenas de candidatos com biblioteca-padrão Python, usando famílias de mutações conhecidas e casos válidos. Compara o resultado de um oráculo independente e estrito com o parser PHP **real do laboratório**, via `tools/op01_php_parser_probe.php`.

- Mutações incluem remoção ou acréscimo de chaves de autoridade; troca de versão e operação; tipos incorretos e limites de referências `SYNTHETIC_`; caracteres Unicode, newline, NUL e separadores; listas de projeção vazias, duplicadas, fora do escopo e não listas.
- Casos válidos com campos mínimos, dois campos em diferentes ordens, ordem alterada das **chaves do corpo**, referências sintéticas curtas e de fronteira devem continuar aceitos.
- O teste usa **lotes de até 32 casos**: respeita os limites de 80 candidatos e 128 KiB do probe. Comprova que matrizes grandes demais, entradas não compatíveis e lotes vazios são rejeitados sem eco de dados.
- A CI executa explicitamente este arquivo; o teste de cobertura do gate impede que seja ignorado por alteração no shell.

O oráculo caracteriza o comportamento **estrito do consumidor PHP**, não é um validador universal JSON Schema nem prova de equivalência com um contrato aceito pelo CRM. O contrato JSON local continua sintético e o manifesto CRM PR #57 permanece pinado e não homologado.

## Gate

**PHP permanece com 555 casos e lint de 83 arquivos** (SEC-41). Esta entrega aumenta **a cobertura Python** e confirma a paridade no runtime PHP sem instalar bibliotecas ou acessar a internet. Exigir CI de revisão no SHA e CI pós-merge na `dev`.

## NO-GO permanece

CRM PR #57 OPEN/DRAFT. Os 13 cenários da homologação real seguem `NAO_EXECUTADO`, as 7 decisões OP01-R01..R07 e gates R01-G01..G11/CRM CORE/SEG/AUT não foram aprovados. Sem alterações em `main`, VPS, dados de clientes, segredos, produção ou custos.

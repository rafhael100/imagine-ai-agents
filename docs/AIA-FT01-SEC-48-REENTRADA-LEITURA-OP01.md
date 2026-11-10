# FT01/SEC-48 — Bloqueio de leitura reentrante no simulador OP-01

**Data:** 10/11/2026. **Âmbito:** PHP sintético offline, sem CRM real. **Operacional: NO-GO.**

## Lacuna

Após a SEC-46 e a SEC-47, a fachada mantém um único recibo de leitura aprovada e um relógio monotônico por recibo. Contudo, um adaptador `CrmReader` substituível poderia chamar **a mesma fachada novamente antes de a leitura original terminar**. Como uma tentativa aninhada altera o estado de recibo, a leitura original poderia concluir e registrar sucesso mesmo quando houve sobreposição e tentativa de replay/recurso de outra empresa no meio.

## Proteção

- `MockCrmReadPublicFacade` impede `handle()` aninhado na mesma instância antes de alcançar parser, política, leitor ou nonce. A tentativa interna recebe apenas `DENIED_OR_NOT_FOUND` e marca a leitura externa como contaminada.
- O `MockCrmReadBoundary` recebeu um **guard opcional antes de sucesso**, usado exclusivamente pela fachada. Mesmo quando o adaptador ignora a resposta negada da chamada aninhada e devolve uma projeção aparentemente correta, o guard levanta uma negação **dentro** da chamada do decorador de auditoria.
- Assim, o único evento da leitura original é `DENIED / OTHER`, não uma falsa leitura bem-sucedida; o recibo não é reativado. O `finally` restaura o estado de chamada para permitir recuperação com nova prova e novo nonce.
- Um recheck interno sem recibo, que não inicia outra leitura, continua retornando falso sem contaminar a leitura autorizada. Isso evita interpretar uma mera consulta ao estado como nova execução.

## Regressões U70-01..08

Leitura aninhada com mesmas provas, tentativa de recurso de outra empresa, três reentradas sem repetição de nonce/auditoria, recheck interno não consumidor, adaptador que ignora a negação, recuperação com nova credencial fictícia, invalidar recibo antigo e preservar checks de horário/nonce sem reentrada.

**Gate esperado:** **613 testes PHP / lint de 83 arquivos PHP**, verificações Python e CI no SHA exato da revisão, seguida de CI pós-merge.

## Limitações

Guard válido somente na **mesma instância do processo PHP**. Não é mutex distribuído, lock de banco, prevenção de concorrência entre workers, autenticação, ACL transacional nem isolamento real. A auditoria é memória sintética e não log operacional imutável. O CRM deverá realizar suas próprias verificações e testes de concorrência.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT; 13 cenários reais `NAO_EXECUTADO`, decisões OP01-R01..R07 e gates R01-G01..G11/CRM CORE/SEG/AUT ABERTOS/NO-GO. Sem `main`, produção, VPS, dados de cliente, credenciais ou custos.

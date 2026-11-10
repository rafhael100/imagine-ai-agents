# FT01/SEC-24 — Limites de Run/Step também no domínio básico

**Data:** 10/11/2026. **Ambiente:** PHP offline e dados totalmente fictícios. **Integração CRM / piloto / produção: NO-GO**.

## Correção da brecha entre camadas

A SEC-22 aplicou limites de **80 bytes para IDs de Run** e **96 bytes para IDs de Step**, mas somente nos métodos `SupervisedCoordinator::start/step`. Os construtores `new Run` e `new Step`, usados em fixtures antigas e por componentes internos, ainda aceitavam referências arbitrariamente grandes com o prefixo `SYNTHETIC_`. O dado excessivo poderia chegar ao `Intent::signature()`, à assinatura de aprovação ou ao gateway falso por esses caminhos diretos.

A SEC-24 impõe os **mesmos limites no próprio domínio**:
- `Run`: 80 bytes inclusive o prefixo, exige sufixo não vazio.
- `Step`: 96 bytes inclusive o prefixo, exige sufixo não vazio.
- Aplica-se tanto a `new Step` quanto a `Run::step`, inclusive fora do coordenador.
- Referências recusadas geram `INVALID_SYNTHETIC_REF`. Os estados válidos e a autorização explicitamente simulada continuam compatíveis.

## Regressões U46-01..06

Run diretamente instanciada com mais de 80 bytes; Run sem sufixo; ID de Run exatamente com 80 bytes e transição válida; Step direto ou via Run com mais de 96 bytes; Step sem sufixo; Step de 96 bytes com Intent/Approval sintéticos aprovados, sem CRM real.

**Gate requerido:** 427 testes PHP, sintaxe de 82 arquivos PHP e testes Python de contrato, isolamento e pacote bilateral. Merge exclusivamente à `dev` após CI no SHA revisado.

## Limite de escopo

Não é identidade autenticada, revisão humana de uma aprovação ou ACL. O CRM [PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece DRAFT; R01-G01..G11 e CORE/SEG/AUT ABERTOS/NO-GO, sem infraestrutura, clientes, dados reais, segredos ou custos. Nenhuma alteração em `main` ou produção.

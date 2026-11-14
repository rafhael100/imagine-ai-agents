# F224 — Verificação recíproca de fontes Agents/CRM por SHA

**14/11/2026 — laboratório DEV, NO-GO.** O CRM passou a verificar a troca canônica F221 no próprio runner em PHP 8.3 e 8.4 pela PR #573, squash `5eca65403a4e3707e1fd7145ec13073a762d26f6`. Antes disso, o Agents verificava o PHP do CRM em uma revisão antiga, sem a nova prova recíproca.

Esta entrega atualiza o checkout CRM na CI Agents para `5eca65403a4e3707e1fd7145ec13073a762d26f6`, preservando o pin SHA e as permissões somente leitura. A CI executa, **depois** do checkout exato do CRM, o verificador `tools/check_f224_reciprocal_pins.py`: confere que o CRM continua pinando o baseline Agents `a18ad19ad8a24f9a04f1f4bce789ff4af6316d55` (contendo F221), que a CI CRM executa o fluxo JSON com o inspetor original, que `AiaReadOnlyMockBoundary` continua sempre negando e que ambos os checkouts não persistem credenciais.

A revisão de SHA Agents usada no CRM é um **baseline revisado e imutável**, não a HEAD móvel mais recente após esta PR; não se inventa um par de SHAs recursivamente autorreferentes. Mudanças posteriores exigem nova comparação de código e testes, não promoção automática.

O gate local Python confirma falha quando o checkout não está presente, e o job GitHub valida ambos os repositórios em checkout somente leitura. Baseline **879 testes PHP / lint 110** preservado; [CRM #57](https://github.com/rafhael100/imagine-crm/pull/57) continua **OPEN/DRAFT**, **14 gates**, **13 cenários reais NAO_EXECUTADO**, **NO-GO**. Sem alteração de `main`, VPS, endpoint, IAM, credenciais ou produção.

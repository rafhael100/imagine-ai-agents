# F223 — Rejeição explícita de PR com destino main enquanto OP-01 está NO-GO

**14/11/2026 — guarda de CI, sem autorização operacional.**

Depois da F222, a CI verifica alterações DEV e recusa erro silencioso ao trocar a visibilidade para privado. A F223 acrescenta `main` **somente aos gatilhos `pull_request`**, para que uma proposta de promoção não receba ausência de verificação por falta de gatilho.

O primeiro passo do job testa se `github.event_name` é `pull_request` e o destino é `main`. Nesse caso, a CI faz `exit 1` com `OP01_MAIN_PROMOTION_NO_GO`, antes de checkout, sem liberar credenciais, deploy ou modificar arquivos. PRs destinadas à DEV continuam executando o quality gate completo, com CRM fonte SHA-fixada e somente leitura. A falha de acesso privado F222 também é preservada.

**Limitação crítica:** CI reprovada **não é branch protection**. Para bloquear merges, a organização precisa configurar uma regra de proteção da `main` com revisão independente, checks obrigatórios e restrição de bypass. Isso ainda não foi verificado; esta entrega não afirma que a `main` está protegida contra administradores.

Baseline **879 testes PHP / lint 110**, testes Python F223. **CRM #57 OPEN/DRAFT**, **14 gates**, **13 cenários** reais `NAO_EXECUTADO`, **NO-GO**. Sem push ou merge na `main`, sem VPS, dados, rede, IA paga ou produção.

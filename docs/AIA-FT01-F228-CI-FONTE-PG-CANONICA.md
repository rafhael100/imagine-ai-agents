# F228 — CI Agents passa a verificar o PostgreSQL/HMAC sintético original do CRM

**14/11/2026 — NO-GO.** A CI Agents estava fixada no CRM `f44fe65745037d39f57e894ef32a25a9efa07bc4` (antes das PRs #592–#594). Esta entrega faz checkout somente leitura da HEAD DEV validada do CRM `d7c7aacc766c7ade4c65dcd211ed4569442144a9`, ainda sem usar branch mutável ou segredos. A CI CRM nessa revisão fixa como fonte Agents a SHA `5a2c8af18448aeebfe8eda0d7570f34ffbe24611`, que contém o emissor canônico F227, e exige PHP 8.3/8.4 para o próprio contrato.

O verificador F224 executado **depois do checkout CRM** passa a exigir código-fonte presente e semanticamente preservado para `AiaPgNonceCandidateLedger` (somente banco CI `imagine_crm_ci`, chave física de nonce) e `AiaPgHmacLabBridge` (verificação HMAC antes da reivindicação SQL e negativa pública uniforme). Também exige os nomes dos cenários PostgreSQL originais do CRM para concorrência COMMIT/ROLLBACK, duplicidade, adulteração e falta de tabela. As duas classes PHP novas são submetidas a lint real pelo runner Agents após checksum SHA.

Os testes de execução PostgreSQL 16 continuam no **workflow do CRM**, [PR #594](https://github.com/rafhael100/imagine-crm/pull/594), que obteve 4/4 checks e mantém tabela efêmera isolada. O Agents não instala PG, não executa SQL e não transforma um teste de sintaxe/fonte em homologação operacional.

Baseline **879 testes PHP / lint 110** inalterado, além de contratos Python/DOM. **CRM #57 continua OPEN/DRAFT, 14 gates, 7 decisões, 13 cenários reais NAO_EXECUTADO, NO-GO.** Sem IAM real, credenciais, rotas, endpoints, service container, VPS, `main` ou produção.

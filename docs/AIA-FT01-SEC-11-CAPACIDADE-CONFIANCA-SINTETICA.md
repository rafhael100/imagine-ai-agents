# FT01/SEC-11 — Limite de memória nos registros de confiança sintéticos

**Data:** 09/11/2026. **Âmbito:** PHP puro, mocks offline e dados artificiais. **Estado operacional: NO-GO**.

## Lacuna
O armazenamento volátil `MockSharedTrustState` podia acumular nonces consumidos e digests revogados sem limite; o modo legado de `MockTrustGateway` também guardava nonces sem teto. A remoção automática dos mais antigos seria insegura: um nonce antigo poderia voltar a ser aceito.

## Implementação
- `MockSharedTrustState` recebe capacidade configurável de 1 a 4096 itens, padrão 1024, **independente para nonces e revogações**. Depois de atingido o teto, solicitações novas falham com `TRUST_STATE_CAPACITY_DENIED`, mas revogação já registrada permanece idempotente e replay continua negado.
- `MockTrustGateway` legado (um processo, sem store compartilhado) limita seu mapa local a 1024 nonces; não remove histórico para acomodar novas solicitações.
- `MockCrmReadAuditedBoundary` transforma o novo código em categoria genérica de auditoria `TRUST`, evitando divulgar detalhe de memória ao consumidor.
- Em nenhum caso se transforma indisponibilidade, revogação ou falta de capacidade em permissão.

## Regressões U28-01..06
Testam esgotamento compartilhado, repetição e limite de revogação, limites de configuração, falha/retorno da disponibilidade, 1024 entradas no modo legado, preservação de replay e minimização dos registros de auditoria.

**Meta exigida pela CI no SHA revisado:** 335 testes PHP e lint dos 79 arquivos PHP. Resultados reais dependem da conclusão do GitHub Actions.

## Limites e dependências
Essa alteração controla **apenas uma simulação volátil e monotônica**: não implementa TTL, limpeza durável, cache distribuído, credenciais, mTLS, JWT, banco de dados, autenticação de usuários, autorização do CRM ou infraestrutura. Em uma solução real, capacidade, expiração, consistência, revogação e retenção exigem desenho e evidência independente de SEG/IAM, CRM CORE/AUT, Privacidade e Infra.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) ainda requer revisão bilateral. Gates R01-G01..G11 e CORE/SEG/AUT permanecem abertos. **Não autoriza API real, main, produção, clientes, credenciais, VPS ou fornecedores.**

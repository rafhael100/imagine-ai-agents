# Imagine AI Agents — F1-11 | Contratos sintéticos de confiança serviço-serviço

**Data:** 09/11/2026. **Estado:** laboratório local em PHP puro, implementado em branch isolada. **Não é autenticação, IAM, JWT, OAuth, mTLS, assinatura criptográfica nem integração real com CRM.**

## Objetivo e vínculo com os gates

Após F1-10 (revisão humana simulada), avançar nos requisitos de **R01-G01** (identidade/tenant), **G02** (autorização final do recurso), **G03** (replay/efeitos) e **G08** (contrato com CRM) sem tocar sistemas reais ou alegar homologação desses gates. O produto proprietário é sempre a autoridade final: o núcleo não assume permissão apenas por conhecer um `tenant_id`.

## Entrega de código

- `src/MockTrust/MockTrustEnvelope.php` — representação fictícia de emissor, destinatário (audience), contexto completo (produto, empresa, principal, finalidade e recurso), referência de operação, escopos read-only explicitamente permitidos, intervalo lógico de validade e digest SHA-256 determinístico. Aceita no máximo 120 unidades de tempo lógico, duas permissões distintas e rejeita ações mutáveis. Permite revogar o envelope em memória.
- `src/MockTrust/MockOwnerScopeEvidence.php` — resposta simulada do produto dono vinculada ao **digest exato** do envelope, contexto, escopo, validade e autorização fictícia. A ausência, expiração, revogação ou negativa bloqueia o fluxo.
- `src/MockTrust/MockTrustGateway.php` — compara emissor, audience, contexto e escopo contra configuração fechada, exige evidência fictícia do produto dono e rejeita **replay de nonce** no mesmo gateway. O único resultado positivo é `SYNTHETIC_READ_ONLY` com `real_authorization=false` e `external_calls=0`.
- `tests/Unit/trust_contract.php` — **16 casos U11-01 a U11-16**, incorporados à suíte de `tests/Unit/run.php`.
- `examples/f111-trust-demo.php` — demonstração PHP local de autorização fictícia somente leitura seguida de `TRUST_REPLAY_DENIED`.
- `tools/validate_ft01_offline.sh` — atualiza contagem esperada para **127 testes, 57 arquivos PHP**, além de exigir HEAD exato via `EXPECTED_SHA`.

## Matriz de rejeição

| Operação simulada | Comportamento |
| --- | --- |
| Emissor e audience esperados, contexto e autorização do dono correspondentes | `SYNTHETIC_READ_ONLY`, sem API real |
| Emissor, audience ou empresa diferentes | `TRUST_DENIED` |
| Usuário, recurso ou finalidade trocados | `TRUST_DENIED` |
| Escopo não permitido ou mutação solicitada | `TRUST_DENIED` ou `TRUST_SCHEMA_DENIED` |
| Evidência do dono inexistente, vencida, negada, revogada ou de outro contexto | `TRUST_DENIED` ou `OWNER_DENIED` |
| Envelope expirado ou revogado | `TRUST_DENIED` |
| Reutilização do nonce, inclusive após alterar o conteúdo | `TRUST_REPLAY_DENIED` |

## Evidência de execução e integridade

- **PHP 8.4.24** `php tests/Unit/run.php` → **127 cases, 0 failures, exit 0**.
- **57/57 arquivos PHP aprovados no lint**.
- **57/57 SHA-1 de Git blob idênticos** entre arquivos locais executados e a árvore do GitHub; fingerprint FNV32 dos pares ordenados caminho/hash **`de8c8fdc`**.
- `php examples/f111-trust-demo.php` → primeira requisição `SYNTHETIC_READ_ONLY`, segunda `TRUST_REPLAY_DENIED`, `real_authorization=false`, `external_calls=0`.
- Não altera os HTMLs navegáveis de F1-08/F1-09; logo não envolve QA de browser nesta etapa.
- Nenhum código de autenticação real, JWT, OAuth, mTLS, banco, credenciais ou consumo de APIs.

## Limitações importantes

Os campos `issuerRef`, `audienceRef`, `principal`, `tenant` e os `scopes` são apenas **strings fictícias fornecidas pelo chamador**. O SHA-256 não assina nem autentica o emissor. O gateway mantém `seenNonces` somente em memória: **reiniciar o processo perde o registro de replay**. A evidência do dono é igualmente fictícia, não prova de ACL, contrato ou responsabilidade de um produto real.

**Não reutilizar diretamente estes objetos para autorização de serviços reais.** A segurança real exige issuer/audience verificáveis, tokens de curta duração ou mTLS com gestão de segredos, ACL do produto dono, revogação persistente/compartilhada e monitoramento. O design deve preservar semântica de negação por padrão quando esses serviços estiverem indisponíveis.

## Governança

R01-G01 a G11, CORE/SEG/AUT CRM e aprovações reais seguem **TODOS ABERTOS**. Não conectar CRM/Portal/Performance/Universidade, não usar dados de clientes e não tocar `main`, VPS, produção, fornecedores pagos ou contratos sem aprovação crítica específica.

**Próximo passo sem gasto:** elaborar contratos reais *ainda somente documentais* para autenticação e autorização serviço-serviço, especificando interfaces, escopos, dono do recurso e mecanismo de revogação antes de implementar qualquer conector.

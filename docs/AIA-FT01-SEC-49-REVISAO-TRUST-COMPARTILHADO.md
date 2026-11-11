# FT01/SEC-49 — Revisão monotônica do estado compartilhado de confiança

**Data:** 10/11/2026. **Âmbito:** mocks PHP inteiramente sintéticos e offline. **Decisão operacional: NO-GO.**

## Lacuna

As SEC-43–48 validam a revogação atual, a revisão de política do proprietário, o recibo, o horário do recibo e a reentrância. Porém `MockSharedTrustState::setAvailable(false)` seguido imediatamente por `setAvailable(true)` **durante** uma leitura ou sugestão podia restaurar um estado visualmente saudável. A rechecagem `assertLive` então aceitaria uma credencial **anterior à interrupção**, apesar de a confiança usada na primeira autorização ter sido interrompida. Também se podia registrar uma nova revogação de digest no mesmo store enquanto uma operação já estava em curso.

## Mudança implementada

- `MockSharedTrustState` mantém `revision` monotônica, incrementada a cada alteração de disponibilidade (inclusive idempotente, de modo conservador) e na primeira revogação de um digest. Repetições da mesma revogação continuam idempotentes; consumo de nonce **não** modifica a revisão. Overflow invalida o state em vez de permitir wrap.
- `MockTrustGateway` expõe apenas uma revisão local e verificação da revisão; sem token, nonce adicional, rede nem segredo.
- `MockCrmReadBoundary` captura a revisão **depois da autorização inicial e antes do leitor**. Após o leitor, a rechecagem compara a revisão mesmo quando uma indisponibilidade foi restaurada. Divergência falha fechado com código de categoria TRUST; auditoria é `DENIED`, jamais sucesso.
- `MockCrmReadPublicFacade` guarda `trust_revision` no **único recibo privado** de leitura e o exige em `recheck`, inclusive depois do provedor de sugestão. Uma revogação não relacionada invalida conservadoramente um recibo antigo. Uma nova leitura com outra prova e nonce ainda pode passar se o estado voltou a ficar saudável.

## Regressões U71-01..08

Dois testes de reader/área auditada (indisponibilidade seguida de recuperação; revogação de digest alheio durante o leitor), quatro de fachada (recibo antigo invalidado, mutação de digest, recheck saudável sem segundo nonce, recuperação com nova prova), dois de piloto (interrupção restaurada ou digest alheio revogado durante provider resulta em Run `FALHOU` e resposta negada).

**Gate previsto:** **621 testes PHP, lint de 83 arquivos PHP, verificadores Python**; CI verde no SHA do PR antes de squash e novamente no merge na dev.

## Limites e bloqueios mantidos

A revisão é um contador **em um objeto PHP, dentro de um único processo**, não uma versão transacional distribuída nem disponibilidade real de uma store de produção. O mock não tem IAM, assinatura, relógio autoritativo, API, ACL/RLS de CRM ou concorrência real. O CRM precisará executar checagem autoritativa de identidade/permissões e testes de interrupção/reconciliação em homologação isolada.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua OPEN/DRAFT. Os **13 casos reais OP-01** seguem `NAO_EXECUTADO`, decisões OP01-R01..R07 e gates R01-G01..G11 / CRM CORE/SEG/AUT ABERTOS/NO-GO. Não houve alteração de `main`, VPS, produção, clientes, dados reais, credenciais ou despesas.

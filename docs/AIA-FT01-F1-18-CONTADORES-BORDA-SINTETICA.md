# FT01/F1-18 — Contadores agregados na borda sintética de leitura CRM

**Data:** 09/11/2026. **Estado:** implementação proposta em branch isolada, aguardando validação do commit exato. **Somente offline.**

## Problema
A F1-17 reduz diferenças de conteúdo entre erros, mas falhas de schema são barradas antes do decorador de auditoria F1-13. Na integração real, esse intervalo não deve produzir uma dependência de logs de payload nem revelar nomes de empresas, identificadores, credenciais ou motivos internos de negativa.

## Entrega
- `MockCrmReadEdgeCounters`: contador de quatro categorias fechadas (sucesso fictício, falha de schema, negação genérica e falha interna), sem dados brutos, hashes, tenant, recurso, timestamp ou campos dinâmicos. Reservas são únicas e limitadas a 1–256 eventos voláteis. Uma reserva ocupada não pode ser concluída duas vezes.
- `MockCrmReadEdgeBoundary`: camada opcional de laboratório sobre `MockCrmReadPublicFacade`, que reserva capacidade **antes** de analisar a requisição fictícia. Registra só a categoria da falha de schema; preserva o formato `DENIED_OR_NOT_FOUND` para falhas, inclusive saturação e erros internos. Concede somente projeções sintéticas já aprovadas pelo F1-17.
- `tests/Unit/edge_telemetry.php`: 14 testes U18, incluindo rejeição de campos injetados, vazamento em contadores, replay, suspensão, capacidade, tickets forjados, schema e resposta idêntica.
- Regressão total esperada: **228 testes** e **73 arquivos PHP**. Esses números são **metas**, não evidência de testes executados, até verificação do commit em CI SHA-fixada.

## Limitações explícitas
- A nova camada não é HTTP, gateway de produção, SIEM, LGPD homologada, auditoria persistente nem telemetria de rede. Não há clocks, latências, códigos HTTP, roteamento, distribuição nem garantias de retentividade/imutabilidade.
- A categorização de schema é interna ao processo de teste; a resposta externa permanece igual à F1-17. A análise de schema é duplicada intencionalmente no decorador de laboratório e no núcleo; não se deve copiar a arquitetura para produção sem revisão.
- A F1-13 segue responsável por eventos fictícios após parse. A F1-18 só cobre o intervalo anterior, sem expor nova autoridade.
- R01-G01..G11, CRM CORE/SEG/AUT, autenticação verificável, política proprietária do CRM e CRM PR #57 seguem **ABERTOS**. Sem API/CRM reais, dados pessoais, keys, SQL, VPS, terceiros, deploy, `main` ou despesas.

## Critério técnico de aceitação
Revisar diff e integridade do commit exato; exigir `EXPECTED_SHA=<HEAD> bash tools/validate_ft01_offline.sh` com **228/228** casos, **73/73** lint e `VALIDATION_PASS`; conferir uso somente de fixtures e nenhuma chamada externa. Merge, se autorizado tecnicamente, exclusivamente na dev.

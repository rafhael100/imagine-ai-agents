# R01/EVID-17 — Reconciliação verificável do contrato documental CRM ↔ Agents

**Data:** 10/11/2026. **Natureza:** sincronização de artefatos de revisão entre repositórios, estritamente documental/offline. **Decisão operacional: NO-GO.**

## Fonte CRM e hashes pinados

- **Repositório:** [Imagine CRM](https://github.com/rafhael100/imagine-crm) e [PR #57 — CRM-AIA-RO-01](https://github.com/rafhael100/imagine-crm/pull/57).
- **Situação observada após atualização:** OPEN / **DRAFT** / NÃO MERGED. HEAD documental CRM exato: `97bc1e8d6ce4cf4b52378472c31cb49de6a66d51`.
- **Arquivo original:** `docs/desenvolvimento/CRM-AIA-RO-01-proposta-contrato-bilateral-offline-2026-11-09.md`.
- **Git blob SHA do documento atualizado:** `cefb390ec41a76d486b137279d92627f097d1d47`.
- **Cópia byte a byte:** `contracts/reviews/crm-aia-ro-01.pr57.snapshot.md` neste repositório.
- `contracts/reviews/crm-aia-ro-01.pr57.manifest.json` e `tools/check_op01_contract_parity.py` fixam esses dois valores; alteração não revisada faz falhar a paridade. Os testes não consultam o GitHub: esta é **evidência de snapshot revisado**, não garantia contínua de que o PR remoto não mudou depois.

## Diferença material em relação ao documento antigo

O texto original do CRM citava exclusivamente F1-12, **144 testes PHP / 60 lint**. Essa evidência permanece identificada como **histórica**; o adendo de revisão de 10/11 incorpora a resposta pública sintética EVID-10, os caminhos governados da ferramenta e o baseline **397 testes PHP / 82 lint**, com CIs de revisão e pós-merge do Agents. Não atribui ao CRM testes de integração ou endpoints inexistentes.

## Matriz bilateral OP01-R01..R07 (todas ABERTAS)

O adendo CRM apresenta sete decisões explícitas para revisão competente: autenticação serviço-serviço; ACL por empresa/membership/recurso/finalidade/campo no **backend CRM**; replay/revogação distribuídos; LGPD/minimização; formato de respostas/erros e versionamento; homologação/rollback; responsáveis e orçamento específico do piloto.

**O que foi executado:** sincronização de arquivo, manifesto e verificador offline de contrato com rejeição de drift, sem alteração na API ou no IAM. **O que não foi executado:** parecer de CORE, SEG, AUT, Privacidade; conexão CRM–Agents; teste Laravel/PostgreSQL integrado; deployment; emissão ou uso de credenciais.

## Próximo marco autorizado apenas com aceite específico

Solicitar decisões independentes por dono técnico de OP01-R01..R07 e formalizar a versão operacional do contrato. Somente após isso, abrir tarefa de integração real OP-01 isolada e somente leitura, com autorização de ambiente, IAM, ACL no CRM, dados sintéticos de teste, negativo multiempresa e rollback. `main`, VPS, produção, dados de clientes e custos permanecem intocados. R01-G01..G11 e CRM CORE/SEG/AUT seguem **ABERTOS/NO-GO**.

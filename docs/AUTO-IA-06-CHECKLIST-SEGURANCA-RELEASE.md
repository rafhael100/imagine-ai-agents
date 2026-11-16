# AUTO-IA-06 — Gate de lançamento estritamente NO-GO

**Continuidade da diretriz [#339](https://github.com/rafhael100/imagine-ai-agents/issues/339) e das entregas AUTO-IA-01 a 05.** Gate estático em Python que inspeciona os cinco componentes centrais de laboratório, verifica invariantes que mantêm contadores de APIs, créditos e efeitos em zero, proíbe domínios de APIs pagas e imports básicos de clientes externos, e executa **mutações negativas** para confirmar bloqueio.

Este script não comprova ausência de toda vulnerabilidade, nem substitui SAST, review humano, pentest, teste de fonte real, proteção de IAM ou controle de licenças. Ele nunca libera produção; emite sempre \`production_decision=NO_GO\`. Nunca use sua execução para fazer deploy automático ou aprovar cobrança.

## Gates reais pendentes e donos
| Gate | Responsável | Prova mínima futura |
|---|---|---|
| IAM/tenant e revogação | Portal, CRM, Agents | Identidade operacional com escopo e revogação, prova negativa entre empresas |
| Gateway IA autorizado | AI Agents | Controle server-owned de modelos, cotas, orçamento e políticas |
| Registros distribuídos | Infra/DBA | PostgreSQL RLS, replay durável, transação de reserva, concorrência e restauração |
| Provas e custos reais | Financeiro/Operação | Reconciliação assinada do provedor e conversão comercial documentada |
| Proteção de segredos e redes | Infra/AppSec | Vault ou equivalente, credenciais com rotação, TLS/mTLS e egress |
| Licença e n8n | Jurídico/Infra | Revisão do uso de n8n como orquestração interna, rede isolada, MFA administrativo |
| Recovery e publicação | DevOps/Segurança | Backups, restauração, teste de rollback, observabilidade e GO explícito |
| LGPD | Privacidade | Retenção, minimização, consentimento/finalidade e auditoria |

## Execução offline
\`\`\`bash
python3 tools/auto_ia/check_auto_ia_06.py
python3 tools/auto_ia/release_readiness_lab.py
\`\`\`

**Não executado/indisponível nesta fase:** instalação n8n e Portainer, acesso SSH/VPS Leaseweb, fluxos reais, banco de produção, APIs pagas, SMS, e-mail, pagamentos, autenticação real ou publicação. Também não existe aprovação implícita de merge. Mantemos PRs empilhadas DRAFT para revisão sem modificar \`main\` ou infraestrutura.

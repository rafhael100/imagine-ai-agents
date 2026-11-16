# CENTRAL-AUTO-P20 — preparação privada, 16/11/2026

Status: DRAFT / NO-GO / NO-DEPLOY / NO-CHARGE / NO-PAID-AI. Dispatch n8n permanece desligado. Este documento não autoriza instalação.

## Bases e evidências reaproveitadas

| Componente | Candidata inspecionada | HEAD |
|---|---|---|
| CRM receiver | #636, dependente de #635/#634 | cca5055608acff8c767b888b6322df77fe966558 |
| Portal S2S | #907, dependente de #904 | 95dc618c4c4a05229b78685a5d565e4e13232719 |
| Agents ingress | #450, dependente de #446 | 94dcfeabd24f180113d2a378afe6232c67053664 |
| Agents integração | #446 | 5b4ef6df9c134d25a528ee7ff3cd76e6d794d935 |
| Portal autoridade P20 | #908, sobre #907 | 12d08d2e2af36b8011bc520d55c5e9cdbcccc108 |

P19: [ensaio de aplicações separadas](https://github.com/rafhael100/imagine-crm/actions/runs/43927603208), [relatório](https://github.com/rafhael100/imagine-ai-agents/issues/414#issuecomment-6925201213). Reutiliza inbox/outbox, concessões, RLS, replay concorrente e restauração em bancos descartáveis. Não repete esses ensaios nem converte suas identidades sintéticas em concessões operacionais.

P20 Portal: [PR #908](https://github.com/rafhael100/imagine-portal/pull/908), [10 testes / 60 assertions em PostgreSQL](https://github.com/rafhael100/imagine-portal/actions/runs/43935202657). Conecta o leitor a uma autoridade persistente com MFA nominal, versão de sessão, concessão específica e auditoria obrigatória, quando habilitado explicitamente. Revalida antes/depois da leitura. Usa fixtures para pessoas/concessões; não comprova contratação real ou MFA do editor n8n. A conexão runtime própria não recebe poderes de edição das concessões. Migration aditiva sem seeds, rollback de schema destrutivo bloqueado. Não aplicada na VPS.

## Inventário real consolidado — sem nova consulta nesta rodada

[Evidência de console somente leitura](https://github.com/rafhael100/imagine-ai-agents/issues/414#issuecomment-6925460120), 16/11/2026 aproximadamente 18:28–18:40 UTC (15:28–15:40 Brasília): Ubuntu 24.04.5 LTS, 8 CPUs, cerca de 22 GiB RAM disponíveis, 273 GiB de disco livre, sem swap; Docker 29.1.3, Compose 2.40.3. Oito projetos Compose, 18 contêineres ativos, 19 redes, cinco volumes. Snapshot, não reserva de capacidade ou comprovação de carga sustentada.

Preservar CRM 18081 e sua jornada privada 18086, Portal 18080/18082/18083/18085 e demais listeners, Sites 18090. Porta 17990 observada livre, **não reservada**. Não reutilizar bancos, redes, senhas ou volumes. PostgreSQL/Redis sem publicação de 5432/6379 observada; não equivale a auditoria integral de firewall. Redes listadas, isolamento/egress não auditados no host. Diretórios de backup existem; destino externo, agendamento e conteúdo não foram verificados. Script automatizado não foi encontrado no host; inventário foi manual. Console acessível, SSH do executor não configurado. Nada foi instalado ou alterado na VPS.

## Dimensionamento candidato e isolamento

Reutilizar `infra/n8n-private/docker-compose.yml`, projeto exclusivo `imagine-n8n-private-staging`, redes internas `n8n_storage` e `n8n_apps_private`, volumes próprios `n8n_pgdata` / `n8n_data`. Não anexar redes dos produtos automaticamente. Integração futura apenas via gateways privados autorizados, TLS e contrato; n8n sem acesso SQL aos produtos.

| Serviço | Limite candidato | Estado |
|---|---|---|
| n8n principal | 2 vCPU, 1 GiB, 128 PIDs | existente na candidata |
| PostgreSQL próprio | 1 vCPU, 768 MiB, 128 PIDs | existente na candidata |
| ingresso privado futuro | proposta 0,25 vCPU, 128 MiB | não provisionado |

Total proposto até 3,25 vCPU / 1,875 GiB; cabe nominalmente no snapshot, mas throughput e picos não medidos. Iniciar sem Redis/workers; rever somente com carga medida. Planejamento de disco de 20 GiB não é quota de volume implementada. Não aprovar capacidade por esta tabela.

n8n/DB sem portas host na base. Overlay de operador P15/P17 usa loopback e identidades de laboratório, não é pronto para uso operacional. Não mudar para 17990 sem nova verificação e autorização de instalação. TLS privado: hostname/SAN, cadeia, EKU e pin verificados; ingress recusa cabeçalhos de identidade injetados. Restrição administrativa deve combinar acesso privado, operador nominal, MFA e concessão persistente, sem depender apenas de CN.

Reutilizar imagem n8n imutável já ensaiada `ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea`; digest não é aprovação de vulnerabilidades. PostgreSQL continua no pin compatível da candidata, sem upgrade de major automático. Antes da instalação registrar inventário/SBOM, versão executada e revisão de segurança das imagens. Não usar tags flutuantes ou pins fictícios de testes estáticos.

Monitoramento proposto: readiness n8n e pg_isready já configurados; falha sustentada, reinício inesperado, falta de backup, expiração de certificado, fila/retentativas, disco e memória exigem alerta nominal e runbook. Sugestões de aviso: disco 70%, crítico 85%, memória sustentada 80%; limites e destinatário ainda exigem validação. Nenhum monitor instalado e nenhuma notificação enviada. Logs estruturados com correlation ID/empresa pseudonimizada/operação/resultado, sem payload, cookies, senhas ou chaves; retenção e acesso precisam aprovação. Não habilitar egress para telemetria ou IA.

## Critérios para instalação privada

| Gate | Evidência exigida | Situação |
|---|---|---|
| Infra | inventário recente, recursos, rede/egress, porta reservada exclusivamente | snapshot existe, reserva/isolamento pendentes |
| Identidade | operadores reais nominais/MFA, autoridade Portal, vínculo empresa/serviço | mecanismos testados; concessões reais ausentes |
| mTLS | custodiante, emissão/rotação/revogação auditadas, hostname/pins | validação offline preparada; custódia pendente |
| Recuperação | destino externo aprovado, chave off-host, restore isolado completo | ferramentas preparadas; ensaio operacional pendente |
| Licença | aceitar recorte técnico interno e revisão de adequação documentada | análise condicional em LICENSE-P20.md |
| Independência | parecer de revisor distinto do implementador | não realizado nesta entrega |
| Operação | plano exato, janela, responsáveis, autorização específica | não concedida para instalar nesta fase |

GO exige todas as evidências, SHAs e pareceres vinculados ao mesmo candidato. Nenhuma flag substitui evidência. Instalação ainda **não apta à aprovação operacional**. Próxima atividade: homologar custódia/destino de backup e operadores por mecanismo seguro, executar restauração isolada aprovada, obter revisão independente; só então submeter plano concreto de instalação.

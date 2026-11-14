# F208 — Conformidade recíproca do código sintético CRM ↔ Imagine AI Agents

**14/11/2026 · PR em DEV · rigorosamente NO-GO operacional.**

Agora o CI do Imagine AI Agents confere também **as classes PHP efetivas do CRM**, em um checkout público read-only fixado no commit imutável `7999b1e34a6cbd0ee1c5e2351067ad172e01bbad` da branch de desenvolvimento CRM, sem usar endpoint, gateway, login, tenant real, segredos, SQL ou serviços. É um teste de interoperabilidade **sintática e de negativa segura**, não homologação bilateral.

Classes CRM exercitadas: `AiaReadOnlyMockBoundary`, `AgentsReadResponseInspector` e `AgentsReadResponseAssessment`. Classes Agents exercitadas: `MockCrmReadRequest` e `MockCrmPublicResponseContract`. O verificador `tools/check_crm_aia_cross_source.php` compara aceitação sintática das requisições em ambos os repositórios, resposta pública uniformemente negativa em qualquer caso (inclusive outro tenant), ausência de vazamento de contexto e inexistência de direitos mesmo quando uma projeção fictícia está formalmente correta. O workflow verifica o HEAD do checkout CRM antes de executar as classes; não instala dependências.

O gate offline local dos Agents continua sem rede e sem precisar do checkout CRM. Os testes Python F208 verificam a imutabilidade do pin, o acionamento da prova, a manutenção da suite e de `NO_GO`. A prova é executada no workflow GitHub **após** o gate offline. Não há mudanças em `main`, VPS, clientes, IA paga ou produção.

**Gates abertos:** CRM #57 OPEN/DRAFT; R01-G01..G11, SEG/DBA, CORE/IAM, Privacidade/LGPD e infraestrutura real; 13 cenários OP-01 reais `NAO_EXECUTADO`. Um resultado verde indica apenas compatibilidade do código sintético fixado — nenhuma concessão de acesso, identidade ou autorização operacional. Uma futura atualização de código CRM exige revisão deliberada do SHA fixado e repetição da CI.

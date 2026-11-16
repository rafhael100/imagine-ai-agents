# CENTRAL-AUTO — candidata integrada de identidade e infraestrutura privada

Base #391: efc6786209742d3a1f6300523fdadd2f85a9da16. Incorpora o patch de validade transacional da chave e testes da #384 (21c820b56d53bce243969a4fdd440bf1e26644e5), preservando RC04/#387 e todas as correções do Compose. Não houve merge em main/dev ou nas branches de origem.

A candidata mantém duas provas independentes: receptor assinado com FOR SHARE e revogação concorrente em PostgreSQL; n8n oficial com PostgreSQL dedicado não-superusuário, segredos por arquivo, readiness interno e nenhuma porta publicada. A prova manual de workflow da #387 é preservada por hashes e não é convertida em operação com clientes.

Não há conexão automática receiver→n8n, sessão humana encaminhada, acesso direto aos bancos de produtos, API paga, cobrança ou publicação. O workflow Code aprovado em laboratório network-none não é habilitado no Compose privado: Code continua excluído até revisão de runner externo.

## Contratos e próximo gate

CRM #634 produz comandos nativos e exporta estados tenant-scoped. Portal #904 valida o contrato CRM_AUTOMATION_STATE_V1 com concessão independente antes/depois da leitura; fonte não registrada por padrão. O endpoint nominal do CRM não vira receiver de serviço por receber headers. AUTO_IA_S2S_V1 permanece contrato técnico do laboratório. Integrações Portal→CRM, Portal→Sites e n8n→produtos exigem concessões próprias homologadas, mTLS/identidade do serviço e revalidação no destino, replay/idempotência persistentes e auditoria. Não encaminhar cookie/token humano para superar esses gates.

P4: host Leaseweb não inventariado nesta rodada (sem SSH agent/config), nenhum serviço iniciado ou modificado. O acesso administrativo n8n requer ingress privado HTTPS/MFA separado; 17990 não é porta funcional desta candidata. Backup externo/restauração, licença, segredos e autorização de instalação continuam NO-GO.

Testes na SHA candidata devem executar ambos os workflows; um teste não substitui o outro. Revisão desta integração é do implementador, não parecer independente externo. DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE / NO-ADS-WRITE.

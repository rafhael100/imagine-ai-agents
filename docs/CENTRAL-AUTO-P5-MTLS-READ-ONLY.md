# CENTRAL-AUTO-P5 — Teste HTTPS/mTLS real com CRM sintético e Portal original

Sequência da [CENTRAL-AUTO-RC05 #392](https://github.com/rafhael100/imagine-ai-agents/pull/392), sem repetir o transporte genérico já estudado no Portal Fase 3. Aqui testamos especificamente o contrato `CRM_AUTOMATION_STATE_V1` e a classe `AutomationStateReader` da Central de Automações.

## O que foi desenvolvido
- Checkout somente leitura da classe `RuleDefinition/AutomationSettings` do [CRM #634](https://github.com/rafhael100/imagine-crm/pull/634), SHA 9afa6683e6b3463f12bd80a25f455f925828c0af; gera uma fixture com uma tarefa de follow-up determinística e sem IA.
- Checkout somente leitura da classe `AutomationStateReader` do [Portal #904](https://github.com/rafhael100/imagine-portal/pull/904), SHA 9d3877dfaa81524faf2c08f80244b485968316ec. O leitor original exige concessão do responsável antes e depois da comunicação TLS.
- Em GitHub Actions, um servidor Python de teste em **outro processo** escuta apenas `127.0.0.1:18787`, com CA e certificados de servidor e cliente gerados no próprio runner e eliminados ao final. O Python serve apenas o snapshot de laboratório; não é endpoint real do CRM.
- O cliente PHP envia requisição POST com contrato fechado, identificador de empresa fictícia, nonce/request únicos, janela máxima de 60s e HMAC. O servidor exige certificado de cliente CN `portal-automation-admin`, audiência correta, empresa autorizada sinteticamente e nega nonce repetido (apenas memória).
- A resposta tem envelope HMAC com o JSON original do estado em base64, vinculado ao nonce, request e empresa. O PHP valida hash e TLS antes de entregar ao leitor real do Portal. O Portal verifica schema/limites/estado/grant pós-leitura.
- Testes de cliente sem certificado, cliente certificado mas não autorizado, outra CA, alteração de empresa e audiência, expiração, nonce repetido, revogação antes e durante leitura, resposta adulterada.

## O que esta entrega NÃO comprova
- Não existe endpoint CRM real nem banco real conectado ao Portal. O servidor é uma fixture independente construída a partir das classes originais do CRM, não um aplicativo Laravel com sessão, roles e membership reais.
- O segredo e o cadastro de certificado são **sintéticos** e não representam IAM de clientes nem service identity real. A proteção de replay é em memória; após reinício, nonce repetido seria aceito. Essa limitação proíbe instalação operacional.
- A porta 18787 é apenas loopback no runner GitHub Actions; não expor Internet, DNS, rede interna da VPS ou arquivo `.env` operacional.
- Nada pode executar n8n, gerar tarefas no CRM, cobrar, publicar Sites ou acionar IA. A classe do Portal fica limitada a observação de estado; não concede direito a efeitos.

## Próximos passos sem duplicação
1. Substituir os registros sintéticos de service identity por vínculo de credencial e empresa validado por IAM CRM/Portal, com revogação concorrente e TTL.
2. Persistir nonces por empresa/serviço em PostgreSQL com atomicidade e backup/restore, sem aceitar empresa declarada pelo navegador.
3. Introduzir uma fonte de produção autorizada somente após gates externos AppSec/DBA, TLS/mTLS, monitoramento, egress e integração de permissionamento.
4. Revisar portal e CRM juntos numa candidata de homologação que não modifique main ou VPS sem pré-condições.

**PR DRAFT / NO-GO PARA MERGE/DEPLOY/N8N/IA PAGA/DADOS DE CLIENTES.**

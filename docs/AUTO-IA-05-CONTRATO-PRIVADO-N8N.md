# AUTO-IA-05 — Contrato sintético e privado n8n↔Imagine

Aprovação técnica da diretriz em [#339](https://github.com/rafhael100/imagine-ai-agents/issues/339). Esta etapa **não instala n8n** nem cria endpoints. O arquivo de contrato serve para validação offline de eventos futuros, exclusivamente fictícios.

## Fronteiras
- Serviços Imagine são donos de suas regras, dados, aprovações e lançamentos. O n8n futuro coordena workflows internos, não consulta diretamente tabelas de outros projetos e não se conecta a provedores de IA.
- A primeira versão do evento contém **apenas referências sintéticas** de produto, tenant, evento, solicitação, nonce e SHA-256 de payload, sem o próprio payload, dados pessoais, token do usuário, URL ou credencial.
- A assinatura HMAC testada usa uma chave sintética local; não é credencial de produção nem autenticação IAM/mTLS. A verdadeira identidade serviço-serviço precisará de envelope de serviço, custódia de segredo e política de rotação.
- O catalogo AUTO-IA-01 limita este canal a **operações NO_AI**. Candidatas a IA não passam por esse fluxo.
- Nonce controlado em memória **não sobrevive reinício**, logo NÃO previne replay entre instâncias. Em produção será necessário registro compartilhado e transacional com consumo atômico antes de efeitos reais.
- Mesmo um evento válido retorna \`LAB_ENVELOPE_ACCEPTED_NO_ACTION\`, \`service_effects=0\`, \`external_calls=0\`, \`ai_tokens=0\`. Não autoriza uma única modificação em sistemas reais.
- Controle de capacidade (128 itens) falha fechado, não elimina registros antigos para aceitar replays.

## Teste
\`\`\`bash
php -l tools/auto_ia/MockN8nEnvelope.php
php tools/auto_ia/check_auto_ia_05.php
\`\`\`

## Homologações futuras (NO-GO atual)
Inventário da VPS, licença de uso interno n8n, autenticação administrativa com MFA quando suportado pela implantação, rede privada, certificados/TLS, Docker Compose isolado, Postgres dedicado, backup/restauração, limites de CPU/RAM, IAM da empresa e serviço, anti-replay durável, assinatura com chaves protegidas, idempotência, fila, dead-letter, observabilidade, falhas e rollback. Sem tokens/segredos em Git, nenhum acesso de clientes ao editor n8n e nenhum uso de API paga sem autorização específica.

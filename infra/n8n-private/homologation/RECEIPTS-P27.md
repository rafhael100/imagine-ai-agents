# CENTRAL-AUTO-P27 — consulta privada de recibos recebidos

## Comportamento

A confirmação HTTP de um evento pode se perder depois que a inbox já fez o
commit. A nova rota `POST /private/events/receipt` permite consultar esse
recebimento sem reenviar o evento. Ela retorna somente referências técnicas e
um recibo assinado de estado `RECEIVED`, com `dispatch=DISABLED` e
`processing=NOT_DISPATCHED`.

Não há inferência de execução a partir da existência da inbox. O caminho P26
`mock.run.completed` / `run.status.project` continua separado do ingresso P19
`lead.ingest`. O arquivo `index.php`, a rota `/private/events` e o ACK antigo
permanecem intactos. O runner compara o blob do receiver com a base e o ensaio
HTTP verifica o ACK existente além da consulta nova.

**Perfil exclusivo da candidata sintética:** `synthetic-private-v1`.
O endpoint não fica habilitado por padrão nem concede permissão operacional.

## Contrato da consulta

A requisição é um objeto JSON plano com exatamente 15 campos. O parser PHP
recusa nomes repetidos, inclusive nomes equivalentes por escape Unicode,
campos extras, objetos/arrays aninhados e tipos incorretos. Não aceita floats,
expoentes, booleanos ou strings nos campos de tempo.

| Campos | Valor ou restrição |
| --- | --- |
| `schema` | `AUTO_IA_RECEIPT_QUERY_V1` |
| `issuer`, `product` | `imagine-crm` |
| `audience` | `imagine-ai-agents` |
| `operation` | `event.receipt.read` |
| `company_id` | UUID v4 canônico em minúsculas |
| `tenant_ref`, `event_ref` | Referências sintéticas do evento recebido |
| `payload_hash` | SHA-256 dos bytes do payload original |
| `request_ref`, `nonce_ref` | Referências sintéticas próprias da consulta |
| `issued_at`, `expires_at` | Inteiros; validade máxima de 60 segundos |
| `key_id` | Chave de ingresso sintética atualmente habilitada |
| `signature` | HMAC-SHA256 em hexadecimal minúsculo |

A assinatura usa o prefixo `IMAGINE_AGENTS_EVENT_RECEIPT_QUERY_V1`, seguido
por uma quebra de linha e pelos valores dos campos na ordem listada em
`receipt_query()` / `receipt-status.sql`, sem o campo `signature`, separados
por quebra de linha. O prefixo e a operação são distintos do ingresso P19.
As referências e digests têm alfabetos fechados, sem quebras de linha.

A janela permite emissão até cinco segundos no futuro e no máximo trinta
segundos no passado. O banco revalida o relógio depois de adquirir os locks,
inclusive os vencimentos dos grants, antes de devolver o recibo. As consultas
têm limites curtos de espera por lock e de execução SQL.

## Permissão de leitura independente

A tabela `auto_ia_lab.receipt_read_grants` contém uma concessão específica de
`event.receipt.read`, vinculada a empresa, fingerprint do certificado,
tenant, revisão e validade. Possuir apenas `lead.ingest` não permite consultar
o recibo. O SQL também exige que o grant de ingresso, o vínculo empresa/tenant
e a chave de assinatura estejam ativos no momento da consulta.

O papel `aia_private_receipt` é `NOLOGIN`, sem poderes administrativos e com
apenas `USAGE` de schema e `EXECUTE` da função de consulta. Não recebe SELECT
ou escrita em inbox, chaves ou grants. A conexão da CI utiliza um login
separado com `NOINHERIT`, que pode assumir somente esse papel; a instalação
SQL não cria senhas, logins ou permissões reais.

As tabelas de grants preservam owner e `FORCE RLS`. A função é
`SECURITY DEFINER`, tem `search_path` fixo e execução revogada de `PUBLIC`.
Além da inbox correspondente ao tenant, evento e hash, a consulta exige a
admissão P19 daquela empresa em `service_event_audit`. Uma linha genérica de
outro caminho ou da outbox P26 não vira um recibo de ingresso P19.

## Resposta e recusas

A resposta `AUTO_IA_EVENT_RECEIPT_V1` inclui emissor/destinatário, empresa,
tenant, evento, hash, estado, dispatch, processing, request/nonce, horários,
identificador da chave e assinatura. O HMAC usa um segredo de resposta
próprio, o prefixo `IMAGINE_AGENTS_EVENT_RECEIPT_V1` e a ordem fechada de
campos construída no endpoint. Sua validade é limitada a trinta segundos e
nunca ultrapassa a validade da consulta.

O cliente deve verificar a assinatura, o domínio, a validade e todos os
vínculos com a consulta enviada. Não basta reconhecer `state=RECEIVED`.
Nenhuma resposta inclui payload, conteúdo de lead, chave, grant ou diagnóstico
interno. O recibo atesta recebimento; não autoriza execução ou acesso futuro.

Evento ausente, hash incorreto, outra empresa, certificado sem grant,
revogação, chave desabilitada ou assinatura inválida recebem a mesma recusa
da aplicação. Não há resposta que confirme a existência de um evento de
outra empresa. O protocolo exige POST, JSON, ausência de query string,
Cookie, Authorization e Content-Encoding; respostas usam `Cache-Control:
no-store`.

Consultas repetidas continuam somente leitura e revalidam autorização atual.
Elas não criam eventos, tarefas, tentativas ou evidências de execução.

## Ensaio real de transporte, com material sintético

O driver P27 cria certificados exclusivamente para a CI, inicia PHP-FPM e
nginx e faz requisições HTTPS com autenticação mútua real. Os processos PHP,
nginx e cliente compartilham o namespace `network none` do PostgreSQL
descartável. Não há porta publicada no host ou saída de rede para provedores.
O upstream de teste usa loopback; os parâmetros FastCGI continuam sendo
definidos pelo nginx a partir do certificado TLS, nunca de headers do cliente.

O teste valida entrega e duplicidade pelo receiver original, consulta positiva,
assinatura e vínculos da resposta, ausência de alteração da inbox, isolamento
entre empresas, grants separados, revogações, janelas de tempo, JSON hostil e
limites dos papéis SQL. Certificado ausente ou não confiável não chega a ser
uma consulta autenticada da aplicação.

O wrapper RC69 executa as suítes Agents, P25, P26 e P27 na mesma árvore
temporária. O resultado do check da HEAD concreta é a evidência de execução.

## Limites

O cliente de teste não modifica o CRM. Adotar a consulta no sender ou numa
interface CRM será uma alteração própria daquele produto, com seu contrato
e permissões. Os certificados, concessões e segredos são sintéticos; o ensaio
não configura custódia real, IAM, produção, VPS, R2 ou fornecedor de IA.

O grant novo permite consultar um recebimento específico. Não liga dispatch,
não aumenta o direito de `lead.ingest` e não confunde recibo de transporte com
execução de trabalho. **DRAFT / NO-GO operacional.**

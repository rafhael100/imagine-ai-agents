# Custódia, recuperação e reversão — procedimento candidato P20

Não executado na VPS. Não é script de deploy ou autorização para parar produtos. Dados de demonstração exclusivamente sintéticos. Nunca reutilizar credenciais CRM/Portal/Sites para n8n. Não usar `down --volumes`, apagar bancos ou aplicar schemas descartáveis de CI na VPS.

## Identidade operacional e custódia

1. Nomear responsáveis distintos por emissão, concessão e recuperação. Registrar identificador de certificado, fingerprint público, SAN/EKU, validade e escopo; chave privada fora do Git/log/chat, arquivo 0400/0600 em diretório protegido. CA privada fora dos contêineres; somente CA pública/pins nos verificadores. Não herdar CA descartável de CI.
2. Executar `material_preflight.py` somente em cópias locais de material aprovado, diretórios confiáveis sem escritores concorrentes. Verifica cadeia/EKU/validade/hostname para servidor, pin e par de chaves. Não faz consulta de revogação e não concede acesso. Resultado é sempre NO-GO para instalação.
3. Associar cada certificado a concessão persistente por empresa/operação/destinatário com validade curta e operador/justificativa auditados. Autoridade indisponível, sessão expirada, MFA ausente ou concessão revogada devem negar. Não encaminhar sessão humana no S2S.
4. Rotação em etapas: emitir nova chave; validar material; cadastrar nova concessão mínima; ensaiar autenticação; trocar consumidor; revogar antiga na autoridade e no ingresso; testar nova chamada e retry da antiga negados; registrar auditoria. Não editar/recriar identidades de CI para fingir concessão real. Cada consumidor deve comprovar revalidação; não supor hot reload do conjunto de chaves.
5. Revogação urgente: revogar concessão e sessão, desabilitar certificado no ingresso, cancelar trabalhos pendentes autorizáveis e confirmar negação no destino inclusive em idempotência/retry. Verificar limites de transação/timeout de chamadas em voo. Reemitir chave comprometida, preservar auditoria; rollback não reativa chave revogada.
6. Contas editor n8n nominais com MFA, recuperação sob custódia e sessão revogável. Portal tem mecanismo próprio testado em #908; falta concessão operacional e prova de inscrição/obrigatoriedade no n8n pinado. Não capturar QR/recovery codes como evidência pública.

## Migrações incrementais

Comparar migrations aplicadas e candidato exato em clone isolado; nunca usar bootstrap descartável de CI. Revisar locks/tempo/índices/FKs/RLS/grants e espaço. Papel migrador separado do runtime. No CRM reutilizar migration incremental #635; no Portal #908 cria apenas novas tabelas, sem seeds de pessoas/grants. RLS FORCE não substitui papel sem SUPERUSER/BYPASSRLS/ownership. Após migração testar restrições por conexão runtime e ausência de acesso cruzado. Sem aplicação operacional nesta fase.

Rollback aditivo por retorno do código compatível e desativação da feature, preservando auditoria/grants/histórico. Mudança incompatível exige restore em NOVO banco e troca controlada; nunca downgrade destrutivo automático. Outbox/inbox e versões de contrato precisam manter as mesmas chaves de idempotência. Segredos/credenciais antigas revogadas não devem ser restauradas como concessões válidas.

## Snapshot e cópia externa

Preparar destino externo criptografado sem contratar serviço. Aprovar custodiantes, recipient público e pin fora do pacote, retenção, espaço, RPO/RTO e canal de transporte. A chave privada de recuperação deve ficar off-host; não basta outro diretório na mesma VPS. Uma política de retenção ou RPO sugerida não é compromisso já aprovado.

Para o ensaio futuro, quiescer apenas a stack de homologação autorizada e seus escritores. Não parar CRM/Portal/Sites existentes. A consistência dos ledgers participantes exige checkpoint coordenado ou protocolo de replay documentado; dumps em momentos distintos não provam consistência distribuída. Não exportar dados reais. Capturar PG17 custom dump n8n, volume de dados e exata chave de criptografia; capturar ledgers inbox/outbox dos produtos pelos proprietários autorizados, sem acesso SQL do n8n. Reutilizar ensaios P12/P19, cujos restores foram locais/descartáveis, sem chamá-los de backup externo.

Preparar cinco arquivos 0400/0600 em diretório 0700, sem symlink/hardlink ou outro escritor: `n8n-pg.dump`, `n8n-data.tar`, `n8n-encryption.key`, `crm-service.dump`, `agents-service.dump`. Não incluir segredos não necessários ou dumps de produção. A ferramenta exige todos os componentes, limita pacote a 20 GiB e volume a 10 mil entradas; rejeita links e caminhos perigosos. Cabeçalho PGDMP é só validação de formato; executar `pg_restore --list` e restore real separadamente.

`seal_backup.py seal` recebe certificado público/pin e gera CMS AES-256-GCM 0600 sem sobrescrever, com manifest e hashes; não coleta, envia, restaura ou aprova snapshot. Criptografia autenticada protege conteúdo, **não autentica origem do operador**: guardar hash do ciphertext, origem, horário, versões e aprovação em ledger protegido separado. Verificar confiança/revogação do destinatário antes do uso; OpenSSL CMS não verifica revogação por si ([documentação](https://docs.openssl.org/3.0/man1/openssl-cms/)). Não confundir recipient self-signed sintético dos testes com custódia homologada.

Copiar ciphertext por canal aprovado ao destino externo; comparar hash em nova leitura do destino. Registrar recibo sanitizado e retenção. Ferramenta deixa evidências de consistência/cópia externa/restore em falso de propósito: somente uma evidência externa independente pode satisfazer cada gate, sem editar manifest para forçar GO.

## Restauração isolada e prova de recuperação

1. Recuperador autorizado lê cópia EXTERNA e chave privada off-host; confere hash no ledger protegido, destinatário e versão. Decriptar CMS em quarentena 0700 e exigir exit code zero antes de qualquer extração. Falha de autenticação exige descarte seguro da quarentena.
2. Executar `verify-decrypted` no tar em quarentena; verifica nomes únicos, tipos, limites e hashes. Não extrai. Após sucesso, revisar o tar interno novamente e extrair com ferramenta restrita, sem links/paths absolutos. Material sensível temporário em volume criptografado com acesso nominal; remover conforme política após ensaio.
3. Criar projeto e volumes NOVOS de recuperação, sem listener público/egress. DB própria PG major compatível, migrador separado, runtime sem privilégios excessivos. Restaurar dump, volume e chave n8n correspondente; verificar leitura de credenciais com fixture, workflow/hash, readiness e execução determinística sem IA. Não reativar webhooks, cron ou dispatch automaticamente.
4. Restaurar inbox/outbox com ownership, RLS FORCE, políticas e grants mínimos adequados. Testar runtime cross-tenant negado; evento já confirmado não repete efeito, retry pendente concilia e credencial revogada continua negada pela autoridade atual. Restore não deve ressuscitar sessão/permissão vencida.
5. Registrar tempo, hashes, versões, contagens sanitizadas, resultado nativo e falhas; comparar RPO/RTO aprovados. Evidência pública sem dados/chaves. Revisão independente do ensaio. Só depois considerar recuperação operacional testada.

## Instalação e rollback futuros

Após todos os gates e autorização específica: rever listeners apenas para a porta proposta, recursos e isolamento; criar somente projeto/volumes exclusivos; validar Compose sem imprimir segredos; executar migration aprovada; inicializar com dispatch OFF; inscrever operadores/MFA; realizar teste privado de acesso/revogação e monitoramento. Não alterar stacks existentes ou DNS.

Rollback: interromper ingresso da NOVA stack, suspender apenas seus jobs, preservar volumes e logs, voltar digest/config compatível; se schema incompatível, recuperar em novo projeto conforme acima e comparar ledgers antes de redirecionar. Não remover volumes antigos até aprovação de retenção. Revogações permanecem efetivas. Falha de restore mantém NO-GO e não autoriza fallback inseguro.

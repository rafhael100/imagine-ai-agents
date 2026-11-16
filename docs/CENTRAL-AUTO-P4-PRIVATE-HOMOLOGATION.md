# CENTRAL-AUTO-P4 — preparação privada, sem instalação Leaseweb

Base #387 `d3860e1e3d95b79ca3a2c6376651a62028820141`, execução oficial network-none já verificada. Não repetir/copiar motor comercial, Gateway ou workflow. #384 permanece correção irmã de identidade, a incorporar explicitamente antes da candidata S2S operacional. DRAFT / NO-MERGE / NO-DEPLOY / NO-CHARGE.

## Achados corrigidos na configuração candidata existente

O Compose original colocava senhas/chave de criptografia diretamente em environment e usava como runtime o POSTGRES_USER criado pelo entrypoint, que é superusuário. Agora usa secrets montados em arquivos, bootstrap administrativo distinto e n8n_runtime com NOSUPERUSER/NOBYPASSRLS/NOCREATEDB/NOCREATEROLE. PostgreSQL permanece exclusivo, sem portas publicadas. As duas redes são internas, nomes/volumes isolados por projeto. Ambos os serviços exigem profile homologation; sem restart automático. Readiness exige aplicação e banco conectados.

Os caminhos _FILE são suportados pela configuração oficial n8n: https://github.com/n8n-io/n8n-docs/blob/main/docs/deploy/host-n8n/configure-n8n/basic-configuration.md . O teste candidato utiliza o mesmo digest n8n 2.47.7 já executado na #387; não presume que documentação de outra versão seja prova de funcionamento.

Code/ExecuteCommand/SSH ficam excluídos nesta configuração. O laboratório manual network-none da #387 mantém sua prova separada; habilitar Code em staging exige revisão de runner externo e isolamento próprio. Acesso ao environment pelos nodes é bloqueado. Sem credenciais de provedores e sem acesso às redes/bancos dos produtos.

## Dimensionamento e gates do host

Candidato: n8n limitado a 1GB/2CPU; PostgreSQL a 768MB/1CPU. Estes são tetos propostos, não reserva de capacidade da VPS. Redis/queue/workers não são adicionados sem medição. Porta candidata 17990 somente loopback, **não inventariada nem reservada** na Leaseweb. CRM 18081 e Sites 18090 permanecem intocados.

O ambiente Codex desta rodada não tem SSH_AUTH_SOCK nem .ssh/config. Nenhuma conexão SSH, inventário remoto, instalação, alteração de firewall/DNS/proxy ou restart de serviço Leaseweb foi executado.

`infra/n8n-private/readonly_inventory.py --probe-host` prepara inventário mínimo por comandos somente leitura. Não lê env, docker inspect, logs, mounts, credenciais ou dados. Emite quantidades e hashes de nomes, disponibilidade da porta sem reservá-la, CPU/disco; sempre mantém status blocked e identidade do host unverified até verificação externa. O script antigo de preflight permanece disponível; nenhum foi executado como prova Leaseweb nesta rodada.

## Segredos, backup, restauração e reversão

Antes de instalar: segredo administrativo distinto, senha runtime e chave n8n em secret store aprovado, arquivos legíveis somente pelos containers previstos, diretório host privado; permissões/UIDs dos mounts precisam ser homologados. Em CI são arquivos aleatórios descartáveis dentro de diretório 0700; valores nunca impressos. Não copiar esses segredos para VPS.

Conjunto indivisível de recuperação: dump PostgreSQL consistente, volume .n8n/artefatos necessários, versão/digest de imagem, export dos workflows técnicos aprovados e seus hashes, chave de criptografia guardada separadamente e manifesto sem conteúdo secreto. Cifrar backup, copiar para destino externo autorizado e verificar integridade/restaurabilidade. Sem chave correta, restauração de banco não comprova recuperação das credenciais. Fonte oficial: https://github.com/n8n-io/n8n-docs/blob/main/docs/deploy/host-n8n/keep-n8n-running/backup-and-restore.md .

Restauração deve ocorrer em volume/banco descartáveis exclusivos, sem rotas externas e sem workflows ativos. Verificar migrações, autenticação, descriptografia de uma credencial de sandbox sem chamar provedor, export/hashes e falha com chave incorreta. Nunca exportar credenciais descriptografadas para logs/GitHub.

Rollback: parar somente ingress/execuções n8n da candidata; preservar snapshot anterior e volume atual; restaurar em novos volumes exclusivos e imagem previamente verificada; validar antes de trocar o endpoint privado. Não executar down --volumes na VPS ou reutilizar volumes CRM/Sites. O down --volumes do workflow pertence apenas ao projeto efêmero imagine-n8n-p4-ci, nunca é procedimento operacional.

## GO/NO-GO

Continuam pendentes inventário/identidade do host, enquadramento da licença, HTTPS/acesso nominal/MFA restrito, mTLS/S2S com autorização por empresa e revogação, egress específico por serviço, runner externo quando necessário, secret store, backup externo/restauração, monitoramento e autorização de instalação. Não usar APP_ENV=local/testing para expor aplicações. Community não é automaticamente válida por esconder o editor; regras dos clientes permanecem nativas no CRM.

CI nova testa apenas a configuração corrigida com containers oficiais descartáveis: role não-superusuária efetivamente conectada, readiness, redes internas, ausência de valores secretos no Config.Env e invariantes de projeto/porta. Não é instalação/homologação da VPS nem prova de backup externo.

# CENTRAL-AUTO-P22 — Revalidar o volume TAR depois da descriptografia CMS

Continua [P20 #451](https://github.com/rafhael100/imagine-ai-agents/pull/451) e [P21 #452](https://github.com/rafhael100/imagine-ai-agents/pull/452). **DRAFT / NO-GO**: testes exclusivamente de CI, sem backup real na Leaseweb.

## Problema de segurança
Um SHA-256 confere que um arquivo tem os bytes descritos no manifesto, mas **não garante que esses bytes sejam seguros para extração**. A P20 inspecionava a estrutura interna de `n8n-data.tar` antes de criar o pacote, mas na verificação do pacote recuperado conferia apenas o hash do TAR interno. Um pacote preparado por origem não confiável, com manifesto recalculado, poderia passar na conferência de hashes e conter links ou caminhos perigosos.

## Correção P22
- O inspetor `inspect_volume()` agora aceita tanto caminho em disco quanto stream não-seekable de membro TAR, com leitura em blocos (sem extrair arquivos nem carregar até 20 GiB em RAM). O mesmo validador é usado na preparação **e na verificação após a descriptografia**.
- Rejeita membros duplicados mesmo com prefixos `./`, caminhos absolutos, `..`, barras invertidas, caracteres de controle, `:` em caminhos, nomes não canônicos, nomes excessivamente longos, tipos não regulares, symlinks, hardlinks e devices, bits suid/sgid, pai do tipo arquivo e colisões entre arquivos e diretórios.
- Exige no máximo 10 mil entradas, até 20 GiB de dados e lê os conteúdos dos membros para detectar arquivo TAR truncado. Não descompacta nem restaura no host.
- A leitura do pacote externo continua fechada aos cinco componentes e manifesto, com integridade via SHA-256 e três flags de evidência **sempre falsas**. Apenas depois disso o volume interno é revalidado.
- O conjunto de testes inclui pacote malicioso cujo manifesto foi recalculado para corresponder ao volume adulterado, demonstrando que hash correto não basta. Também mantém as suítes originais P20 e a prova P21 com `pg_dump`/`pg_restore` reais de três bancos PostgreSQL descartáveis.

## Limites
Esta validação reduz o risco de extração perigosa **mas não transforma um certificado público de criptografia em autenticação da origem do backup**. Uma pessoa com o certificado público pode cifrar um pacote novo com manifest próprio. Para produção será necessária **prova de proveniência assinada por custodiante confiável**, vinculada a um registro de cópia externa independente, com rotação/revogação e acesso nominal.

Mesmo com TAR seguro, a equipe deve realizar restauração apenas em diretório de quarentena protegido, validando limites reais de tamanho/disco, destino externo, credenciais cifradas e integridade de negócio. Não restaurar automaticamente dados ou schemas de produção a partir de um teste CI.

**Sem IAM operacional, DNS, VPS, dados de cliente, merge, deploy, cobrança ou API paga de IA.**

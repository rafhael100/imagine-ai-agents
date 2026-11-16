# CENTRAL-AUTO-RC08 — Recuperação n8n e inventário seguro reunidos

Esta RC integra as entregas independentes [P10 #415](https://github.com/rafhael100/imagine-ai-agents/pull/415), [P12 #419](https://github.com/rafhael100/imagine-ai-agents/pull/419) e [HOM-01 #417](https://github.com/rafhael100/imagine-ai-agents/pull/417) sobre a [RC07 #413](https://github.com/rafhael100/imagine-ai-agents/pull/413), sem merges automáticos nem retrabalho de P4–P9.

## Jobs independentes, mesmo commit
1. **n8n oficial + PG17:** role sem superusuário, segredo por arquivo, duas redes internas e zero porta publicada, fluxo nativo NO_AI de três nós, backup/restauração para segunda base P10 e recuperação do volume `.n8n` + chave sintética para outro projeto Compose P12. Exigir export e resultado 120→12→2 após recuperação.
2. **mTLS/PG16 isolado:** classe original de Portal, contratos CRM sintéticos, certificado e assinatura de laboratório, nonce persistente, oito tentativas concorrentes, falha fechada 503→200→409, backup/restauração PostgreSQL16 e verificação de grants.
3. **Bloqueios NO-GO:** verificadores RC02/RC03/RC04, catálogo NO_AI e proibição de implantação ou gasto com IA.
4. **HOM-01 somente leitura:** os arquivos do inventário e testes negativos da PR #417 são incorporados com conteúdo idêntico. O workflow usa **apenas um host fictício em memória**, e recusa promover um snapshot ao status GO. O runner não é tratado como Leaseweb.

## Regra de segurança
**Não mesclar esta RC enquanto as quatro verificações da própria HEAD não estiverem aprovadas.** Candidatas irmãs RC06 #405/#407 e P41/P44 não devem ser mescladas por inferência; verificar diferenças completas antes de consolidar uma árvore de lançamento.

## Lacunas reais
A recuperação P12 ainda é CI com um único runner e segredos/volume de laboratório, não backup externo em host distinto nem validação de credenciais reais cifradas. O inventário HOM-01 está pronto para ser executado somente pelo operador já autorizado na VPS; **nenhuma execução real no host foi feita nesta sessão**. Seguem abertos: licença n8n, ingresso HTTPS/MFA, identidade e autorização de serviço real, vínculo com CRM/Portal, backup cifrado externo, AppSec/DBA/LGPD e GO humano.

**DRAFT / NO-GO / SEM DEPLOY, VPS, DADOS REAIS, COBRANÇAS OU IA PAGA.**

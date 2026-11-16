# CENTRAL-AUTO-RC06 — Candidata única de infraestrutura n8n privada + comunicação mTLS com replay durável

**Escopo:** unificação explícita da configuração de [CENTRAL-AUTO-P4 #391](https://github.com/rafhael100/imagine-ai-agents/pull/391) e das etapas [P5 #397](https://github.com/rafhael100/imagine-ai-agents/pull/397), [P6 #399](https://github.com/rafhael100/imagine-ai-agents/pull/399), [P7 #401](https://github.com/rafhael100/imagine-ai-agents/pull/401), [P8 #403](https://github.com/rafhael100/imagine-ai-agents/pull/403). Base RC05 [#392](https://github.com/rafhael100/imagine-ai-agents/pull/392), preserva a correção de revogação concorrente #384.

## Qual problema concreto esta consolidação resolve
A P4 e a P8 foram desenvolvidas sobre bases independentes. Esta candidata reúne seus arquivos e suas provas **na mesma HEAD Git**, evitando atribuir testes de um ramo à configuração do outro. Não faz merge em `main` nem altera a VPS. O workflow `central-auto-rc06-integrated.yml` tem **três jobs independentes**:

1. **Infraestrutura privada:** executa oficialmente n8n e PostgreSQL em contêineres Docker CI separados, lê segredos de arquivos efêmeros, exige runtime `n8n_runtime` não-superusuário e `NOBYPASSRLS`, sem portas publicadas, ambas redes `internal: true`. Readiness é verificado dentro do contêiner, não por uma porta host bloqueada; ao final elimina somente os volumes de CI.
2. **mTLS e banco durável:** usa as classes reais e pinadas do CRM #634 e Portal #904, servidor Python sintético HTTPS/mTLS, validações de certificado, nonce, grants, resposta assinada e reversão da concessão. Executa PostgreSQL16 com FORCE RLS, oito ingressos concorrentes, recusa de persistência e recuperação 503→200→409, dump/restore em segundo banco e revalidação de privilégios.
3. **Bloqueio de lançamento:** regressões de catálogo NO_AI, prova imutável da RC04, verificações NO-GO e inventário estritamente informativo (não executado no host Leaseweb).

## Restrições importantes
- **O n8n do job Docker não está conectado ao servidor sintético mTLS:** são ambientes de teste independentes no mesmo commit, não uma implantação funcional de serviços. O n8n de homologação mantém `Code/ExecuteCommand/SSH` desativados por política; a demonstração manual com Code da RC04 foi executada em contêiner sem rede separado.
- Nenhuma porta do n8n é publicada no host: não existe uma URL administrativa acessível na Leaseweb. Uma arquitetura de ingress HTTPS/MFA por operador, com egress cuidadosamente autorizado, ainda precisa ser projetada e homologada.
- O receptor de estados do CRM é sintético; o `AutomationStateReader` do Portal é original mas seus grants são fixtures. Não há IAM real vinculando empresa/serviço, nem receptor CRM Laravel operacional, nem persistência de identidades da autoridade central.
- O bootstrap PostgreSQL e os certificados TLS são **exclusivos de CI**. A conta `ca_mtls_lab_service` e seus segredos embutidos em fixtures não podem ser levados à VPS.
- O pipeline não instala n8n ou Portainer na VPS, não altera DNS/firewall, não executa regras em CRM real, não permite compra de créditos, cobrança ou publicação de conteúdo.

## Gate para homologação verdadeira
Inventário somente leitura na VPS, identificação das redes/portas existentes (incluindo CRM e Sites), orçamento de memória, seleção segura de ingress autenticado, custódia de segredos, política de backup externo e restauração n8n (chave de criptografia inclusa), licença n8n, AppSec/DBA/LGPD e IAM S2S por empresa. Em todos os pontos, não confiar em empresa declarada pelo browser. Deploy/merge só após evidências específicas; CI verde não é autorização.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-AI-SPEND / VPS INALTERADA.**

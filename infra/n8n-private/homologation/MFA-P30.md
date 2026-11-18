# CENTRAL-AUTO-P30 — operador nominal, MFA nativo e sessões

## O que o ensaio precisa demonstrar

P15/P17 verificavam o ingresso do operador e a proteção de transporte, mas
não criavam uma conta nem uma sessão autenticada no n8n. O acesso ao HTML
do editor e o mTLS não comprovam o segundo fator da aplicação.

P30 cria uma conta nominal fictícia numa instância descartável e exercita
as rotas nativas de autenticação. O ensaio usa a mesma imagem fixada:

`ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea`

A versão precisa ser conferida no processo real como `2.47.7`. A fonte
oficial correspondente, tag `n8n@2.47.7`, é o commit
`d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063`.

## Autenticação e revogação reais

O driver reutiliza o Stack P24 e inicializa um banco novo. Um cliente Node
executa dentro do contêiner, alcançando somente o REST em loopback. Não há
porta publicada, navegador, novo transporte HTTPS ou workflow executado
neste ensaio. Os testes de transporte anteriores continuam separados.

O roteiro usa `/rest/owner/setup`, `/rest/login`, as rotas `/rest/mfa/*` e
`/rest/logout`. Uma rota REST protegida comprova se a sessão ainda permite
acesso. Apagar o cookie no cliente ou receber a página de login não conta
como evidência de revogação.

O autenticador sintético gera TOTP usando a dependência original instalada
na própria imagem. O servidor n8n realiza cadastro, verificação e consumo
dos códigos pelas suas APIs; não se implementa um autenticador substituto.

O ensaio exige:

- Cadastro de MFA e login com TOTP válidos na conta nominal fictícia.
- Recusa de login apenas com senha depois do cadastro e de fator inválido.
- Login com um código de recuperação e recusa da segunda utilização dele.
- Recusa do cookie reapresentado depois do logout.
- Invalidação das duas sessões anteriores depois de rotação nativa da senha,
  preservando a sessão nova emitida pelo servidor.
- Revogação do cookie anterior à desabilitação de MFA, conservada depois de
  novo cadastro do segundo fator.
- MFA novamente habilitado na conta ao terminar a prova.

Senha, cookies, segredo TOTP e códigos de recuperação não entram no
relatório ou nos argumentos de processos. Saídas externas são capturadas;
falhas publicam somente códigos fixos. O cleanup remove apenas a instância
e os volumes descartáveis criados por esse ensaio.

## Cadastro individual e política obrigatória são aceites distintos

A fonte de `MfaService.enforceMFA` consulta o entitlement de licença
`mfaEnforcement`. Quando ele não está disponível, uma tentativa de ativação
persiste `false`. O status HTTP da operação não prova que a política ficou
ativa.

Por isso P30 consulta o estado efetivo em `/rest/settings` autenticado e a
configuração persistida, além de testar a operação nativa. A imagem nova,
sem licença provisionada, deve continuar reportando a política global
desligada. O ensaio não injeta entitlement, altera a licença ou contorna
esse comportamento.

A documentação oficial distingue [cadastro pessoal de
2FA](https://docs.n8n.io/administer/manage-users-and-access/verify-user-identity/require-two-factor-auth)
de [políticas de
segurança](https://docs.n8n.io/deploy/host-n8n/configure-n8n/security/manage-security-policies).
A segunda página lista as edições e ressalva features específicas de
licença; a política descrita se aplica a login por email e senha. Não se
infere a exigência de MFA no SSO a partir dela.

Fontes da versão fixada, consultadas em 17/11/2026:

- [MfaService](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/cli/src/mfa/mfa.service.ts)
- [MfaController](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/cli/src/controllers/mfa.controller.ts)
- [AuthService](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/cli/src/auth/auth.service.ts)
- [Testes de integração MFA](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/cli/test/integration/mfa/mfa.api.test.ts)

## Validação e limite operacional

O wrapper RC72 executa P30 depois de P25, P26/P28 e P29/P27 na mesma árvore
candidata combinada com a RC65 fixada. A CI da HEAD concreta deve verificar
esses resultados; pesquisa de fonte e sintaxe não substituem a execução
da imagem.

O ensaio isolado da imagem passou na
[CI 44197654228](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44197654228),
SHA `50223f35221837fdca68ec30e3443e09ba1f1681`, sobre a base P27 já validada.
Confirmou versão `2.47.7`, as vinte assertivas de autenticação/sessão,
invalidação durável do logout e enforcement efetivo/persistido `false`.
O conjunto RC72 ainda precisa validar a HEAD consolidada com as demais
mudanças; esse ensaio isolado é evidência específica do driver MFA.

O ensaio fornece evidência de MFA individual e de comportamento de sessão
para uma conta sintética. O requisito de `LICENSE-P20.md` que exige MFA
obrigatório para **todos** os operadores continua aberto até existir uma
política efetiva aprovada e demonstrada. Também permanecem separados a
identidade do operador real, seu ciclo de revogação e a autorização do
ambiente de instalação.

**DRAFT / NO-GO operacional.** Não há contratação, licença ativada, provedor
externo, identidade real, workflow de cliente ou instalação nesta entrega.

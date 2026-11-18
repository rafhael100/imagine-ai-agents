# P67 — rascunho de regra com autoria atestada pelo CRM

## Recorte aprovado

A autorização específica do responsável permite implementar e testar a criação e a consulta de rascunhos pelo `owner` ativo da empresa no CRM, em homologação fictícia isolada. Esta candidata permanece em PR DRAFT, sem merge em `main` ou DEV, deploy, uso de clientes, chamadas a IA ou custos voluntários.

O CRM resolve identidade humana, sessão/MFA, conta, participação, empresa, papel e regra comercial. O certificado mTLS identifica o serviço CRM; ele não é uma assinatura pessoal do autor. O Agents conserva a autoria histórica `CRM_AUTHENTICATED_OWNER` em registros próprios e imutáveis, sem reclassificar a autoria sintética de P64/P59.

## Contrato fechado

| Elemento | Contrato |
| --- | --- |
| Criação | `POST /private/owner-drafts/create`, operação `crm.rule.draft.create` |
| Consulta | `POST /private/owner-drafts/read`, operação `crm.rule.draft.read` |
| Perfil | `AGENTS_PRIVATE_CRM_OWNER_DRAFTS=owner-rule-draft-v1`; ausente por padrão |
| Negócio | `CRM_AIA_OWNER_RULE_DRAFT_V1`, serialização canônica e SHA-256 |
| Autorização | `CRM_AIA_OWNER_DRAFT_AUTH_V1`, emissor `imagine-crm`, destinatário `imagine-ai-agents` |
| Resposta | `CRM_AIA_OWNER_DRAFT_RESPONSE_V1`, emissor/destinatário invertidos, `DRAFT` ou `ABSENT` autenticado |
| Separação | Dois processos FPM, dois logins/roles de execução e duas chaves HMAC independentes |
| Autoridade técnica | Empresa, fingerprint do certificado e operação precisam de grant vigente; chave e empresa também precisam estar ativas |
| Prazo | Autorização por até 30 segundos; resposta até o menor prazo entre a autorização e mais 15 segundos |
| Correlação | Empresa, operação, ator, draft, hash, nonce UUID e key ID vinculados à resposta |
| Efeito | `executed=false` e `operational=false` são campos obrigatórios e autenticados |

O objeto de negócio contém empresa, referência estável do rascunho, autor, planejamento de origem, regra `followup_qualified`, revisão original das configurações (0–20), lead/versão, instante original, hash do comando e projeção limitada a ação/etapa/atraso/prazo. O CRM reavalia o comando com seu `RuleDefinition` original. O Agents valida estrutura e vínculos; não implementa um segundo motor comercial.

A serialização ordena chaves ASCII recursivamente, preserva tipos inteiros e recusa listas, campos desconhecidos e nomes JSON duplicados. As chaves são 64 caracteres ASCII hexadecimais usados literalmente, sem `hexdecode`. Os domínios de HMAC são `IMAGINE_CRM_OWNER_DRAFT_REQUEST_V1` e `IMAGINE_CRM_OWNER_DRAFT_RESPONSE_V1`, cada um seguido por uma quebra de linha e o JSON canônico sem `mac`.

Cookies, senhas, tokens e identificadores de sessão humana não atravessam a borda. Query string, métodos diferentes, cookies e cabeçalhos de autorização são recusados no recebimento. Os processos FPM não recebem chaves HMAC: verificação, persistência e assinatura são feitas pela função PostgreSQL restrita.

## Durabilidade e idempotência

O CRM grava e commita uma intenção antes de iniciar o I/O. `draft_ref` e `business_hash` pertencem ao negócio; cada tentativa possui nonce e prazo novos, sob autorização atual. A combinação empresa/origem e a combinação empresa/rascunho são únicas. Um reenvio autorizado preserva conteúdo e autoria; um conflito não reescreve o registro original.

Timeout, resposta inválida ou falha no reconhecimento local significam resultado pendente. Eles não comprovam rollback remoto. A consulta posterior verifica novamente o owner e os grants e reconcilia o resultado sem enviar uma criação. Um rascunho histórico continua consultável por um owner atual autorizado mesmo quando a origem já deixou de estar pendente. A elegibilidade da origem é exigida para nova criação/reenvio, não para apagar o histórico.

Não existe uma transação distribuída entre CRM e Agents. Locks locais ordenam decisões e revogações no caminho nominal; morte de processo ou perda de resposta ainda podem deixar a intenção pendente depois de o Agents commitar. Prazos são rechecados depois de esperas em locks e antes de concluir as funções.

## Evidência reproduzível

`tools/run_p67_owner_drafts.py` verifica o evento real de PR, o HEAD, a base P66 `0de0d18b13138e35bf6cb427cc1b4ba5a408cda4` e a Central P30 `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8`. A árvore combinada é exportada sem commit artificial, e cada blob exportado é verificado. Uma alteração de fonte histórica fora deste recorte ou um conflito de composição falha antes do runtime.

A CI P67 usa PostgreSQL 16, certificados descartáveis, mTLS, segredos novos, processos separados, roles sem acesso direto às tabelas e rede Docker interna. O harness exercita os endpoints originais, persistência, replay, expiração, revogação e isolamento. Seus emissores são fixtures técnicas: essa prova não se apresenta como autenticação humana no CRM. A execução humana externa no CRM já ocorreu contra o receptor original fixado, conforme as evidências abaixo.

Os checks de domínio P48/P49/P53/P60/P65 são repetidos. A matriz completa PostgreSQL/Chromium de P66/RC77 permanece evidência histórica da SHA `0de0d18`; seus arquivos são preservados, e ela não é relabelada como nova execução P67. Os resultados efetivos e links de CI pertencem à PR da SHA que realmente executou.

## Resultados verificados e origem da prova humana

A [PR Agents #476](https://github.com/rafhael100/imagine-ai-agents/pull/476), HEAD `0c722eee6bd53a7ee46c4ba68ab70200fc191b78`, passou na [CI 44230030310](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44230030310): 16 HTTP positivos, 113 negativas HTTP/SQL, 21 contraprovas de vínculo da resposta e 13 corridas reais de locks PostgreSQL. Os campos `human_crm_session_verified=false` e `crm_rule_source_verified=false` continuam corretos para esse ensaio interno.

A [CRM #658](https://github.com/rafhael100/imagine-crm/pull/658), HEAD `e390c56af2a544d8191cce86752637efe6a35e43`, passou na [CI 44237380307](https://github.com/rafhael100/imagine-crm/actions/runs/44237380307) usando **essa SHA P67**, composta com a Central P30 original. O ensaio externo executou sessão/MFA humana, fonte comercial nativa, empresa resolvida no backend e transporte mTLS; o relatório HTTP registra `human_session_mfa_executed=true` e `mtls_agents_executed=true`, com 407 verificações/52 requisições.

Chromium executou 607 verificações, oito leituras confirmadas e três pendentes, três idiomas e quatro PNGs. A criação do planejamento ocorreu na sessão do navegador; member foi recusado, autoria histórica preservada e revogação remota de leitura apresentada como pendência. O navegador não chama diretamente o Agents. Esses resultados complementam os testes internos de atestação fictícia; não alteram seus flags nem a autoria sintética P64/P59.

As quatro candidatas e as regressões aprovadas da mesma HEAD CRM estão vinculadas no [STATUS](../../STATUS-ATUAL.md). O próximo marco é disponibilizar o mesmo owner create/read em homologação persistente, mantendo o contrato já aprovado e preparando a mudança concreta para revisão. Não há nova decisão pendente sobre permitir esse fluxo ao owner.

## Limites da entrega

O rascunho não executa a ação `task.followup.create`, não altera a regra ou o planejamento nativo, não publica automação e não concede acesso a clientes. A recuperação de laboratório foi exercitada separadamente no [P68](AIA-P68-OWNER-DRAFT-RECOVERY.md) e no ensaio inerte da CRM #658; esses resultados não provam recuperação distribuída ou disponibilidade operacional. Instalação, configuração do ambiente e qualquer promoção continuam delimitadas pelo respectivo recorte. Uma CI verde deste laboratório não equivale a GO operacional nem demonstra ausência de infraestrutura, MFA, credenciais ou backup externo.

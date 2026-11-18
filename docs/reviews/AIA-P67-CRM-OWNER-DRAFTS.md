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

A CI P67 usa PostgreSQL 16, certificados descartáveis, mTLS, segredos novos, processos separados, roles sem acesso direto às tabelas e rede Docker interna. O harness exercita os endpoints originais, persistência, replay, expiração, revogação e isolamento. Seus emissores são fixtures técnicas: essa prova não se apresenta como autenticação humana no CRM. A candidata correspondente do CRM fixa a SHA P67 e executa login, MFA, fonte nativa, transporte, reconciliação e Chromium na aplicação real de homologação.

Os checks de domínio P48/P49/P53/P60/P65 são repetidos. A matriz completa PostgreSQL/Chromium de P66/RC77 permanece evidência histórica da SHA `0de0d18`; seus arquivos são preservados, e ela não é relabelada como nova execução P67. Os resultados efetivos e links de CI pertencem à PR da SHA que realmente executou.

## Limites da entrega

O rascunho não executa a ação `task.followup.create`, não altera a regra ou o planejamento nativo, não publica automação e não concede acesso a clientes. Restauração, instalação operacional, segredos de ambiente real, políticas administrativas e qualquer promoção devem ser avaliadas no respectivo recorte. Uma CI verde deste laboratório não equivale a GO operacional.

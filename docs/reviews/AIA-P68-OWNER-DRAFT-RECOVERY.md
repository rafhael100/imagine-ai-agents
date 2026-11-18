# P68 — recuperação descartável dos rascunhos P67

## Estado e escopo

Este incremento acrescenta um ensaio real de `pg_dump`/`pg_restore` para os seis
conjuntos persistidos pelo P67. O ensaio usa exclusivamente fixtures técnicas
geradas no job, dois PostgreSQL 16 descartáveis e o receptor PHP/FPM/mTLS
original. Não altera o runtime, as permissões de produto ou a regra comercial.

Estado nesta entrega: código e revisão estática preparados; a execução real
depende da CI da PR P68. A ausência de PHP, Docker e PostgreSQL no ambiente local
impede declarar um resultado de runtime local. O marcador `PASS` só é emitido
depois de todas as verificações e da remoção dos recursos próprios.

- Base exata P67: `0c722eee6bd53a7ee46c4ba68ab70200fc191b78`.
- Central original: `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8`.
- Branch P68: `test/aia-p68-owner-draft-recovery-2026-11-17`.
- Base da PR: `feat/aia-p67-crm-owner-drafts-2026-11-17`.
- Resultado de produto permanece `NO_GO`, `executed=false`, `operational=false`.

O sucesso anterior do [P67](AIA-P67-CRM-OWNER-DRAFTS.md), quando disponível na
composição, comprova o seu recorte de admissão. O P68 acrescenta uma evidência de
durabilidade desses registros; não substitui a autenticação humana real feita
pelo CRM nem atesta recuperação completa do CRM.

## Fonte verificada e execução

O novo [runner](../../tools/run_p68_owner_draft_recovery.py) confere o evento
`pull_request` real, repositório público e de mesma origem, SHA/ref exatos da
base e HEAD da PR, checkout limpo e runner GitHub hospedado. `GITHUB_SHA` permanece
o SHA de merge informado pelo GitHub, sem sobrescrita. HEAD vem do evento real.

A composição virtual é Central + P67 + cinco arquivos P68. O runner compara
todos os blobs e modos com a composição Central + P67: qualquer modificação de
arquivo preexistente, remoção ou sexta adição é recusada. Depois exporta a árvore
em `RUNNER_TEMP`, verifica seu inventário e cada blob e só então permite Docker.
Nenhum preflight/main antigo recebe evento, SHA ou variáveis fabricadas.

O [workflow](../../.github/workflows/aia-p68-owner-draft-recovery.yml) constrói o
Dockerfile P67 inalterado a partir dessa árvore, registra o image ID PHP e usa
os digests reais das imagens PostgreSQL/nginx obtidas no job. O [harness
novo](../../tools/check_p68_owner_draft_recovery.py) reutiliza apenas lifecycle,
certificados, montagem isolada e consumidores HTTP/probes originais do P67.
Não inicia n8n nem executa as matrizes antigas completas.

Não existe modo local que dispense o contexto real da CI. As duas entradas do
workflow são `python3 tools/run_p68_owner_draft_recovery.py --prepare` e
`python3 tools/run_p68_owner_draft_recovery.py --run`.

## O que é preservado

O [probe administrativo P68](../../tools/p68-owner-draft-recovery-probe.php)
calcula contagem e digest dos registros completos, com ordenação por chave.
Não publica registros, certificados, senhas, chaves HMAC ou conteúdo do dump
nos logs/artifacts; a projeção de autoridade transita somente no pipe
administrativo capturado pelo harness.

| Conjunto P67 | Verificação de restauração |
| --- | --- |
| `companies` | Empresa, estado e revisão históricos iguais ao snapshot; depois, estado atual monotônico da fixture |
| `mac_keys` | Segredo e todos os metadados preservados no digest; chave revogada após snapshot continua recusada após reconciliação |
| `service_grants` | Empresa, fingerprint, operação, chave, validade, estado e revisão preservados; grant revogado permanece recusado |
| `drafts` | Business, hash, autoria humana atestada, fonte de autoria, estado DRAFT e proveniência técnica imutáveis |
| `nonces` | Nonce consumido, operação, empresa, ator, hashes e prazos preservados; nova MAC/TTL não permite reutilizá-lo |
| `audit` | Histórico completo, vínculos e revisões preservados; nova leitura acrescenta uma auditoria e um nonce |

Também são comparados owners, ACLs de schemas/relações/colunas/funções, default
ACLs, seis políticas RLS, FORCE RLS, constraints, índices, tipos/defaults de
colunas, configuração e definição das nove funções, atributos das três roles
NOLOGIN e versão/schema das extensões. A sequência `audit_id_seq` tem seu
`last_value` e `is_called` comparados, e a primeira auditoria nova deve continuar
exatamente após o valor restaurado.

## Sequência do ensaio

1. A origem nova recebe o schema P67 literal e os materiais técnicos efêmeros.
   Três chamadas HTTPS/mTLS reais criam rascunhos A/B e leem B com outro ator
   atual. O autor original fica no registro e no business.
2. Uma transação `REPEATABLE READ READ ONLY` exporta o snapshot. O manifest e
   `pg_dump --format=custom --snapshot=...` importam esse mesmo snapshot. A
   sessão exportadora fica aberta até ambos terminarem, com prazos limitados,
   e seu fechamento é conferido.
3. O job não envia chamadas concorrentes à origem durante a captura. O estado
   completo é comparado novamente ao término, incluindo a sequência. Isso é
   necessário porque [sequências não seguem o rollback/snapshot MVCC como
   linhas comuns](https://www.postgresql.org/docs/16/transaction-iso.html).
   O ensaio não promete uma numeração sem lacunas para operações arbitrárias.
4. Após o dump, a fixture da origem revoga o grant CREATE de A e a chave READ
   de A, aumentando suas revisões. Chamadas novas são recusadas na origem.
   Os metadados atuais são capturados fora do arquivo de backup; seu conteúdo
   secreto não integra essa projeção de autoridade.
5. Outro cluster, volume, token de ownership e namespace de rede são criados.
   O bootstrap contém somente `aia_p67_owner`, `aia_p67_create` e
   `aia_p67_read`, todas NOLOGIN e sem privilégios administrativos. Não se
   restaura `pg_dumpall`, senha antiga ou membership global.
6. `pg_restore --exit-on-error` restaura o dump com owners e ACLs. Não são usadas
   as opções que descartam propriedade ou permissões. Antes de qualquer
   login/FPM, o manifest completo deve ser idêntico ao snapshot original.
7. O destino revoga temporariamente os dois grants de EXECUTE das funções de
   transporte para suas roles técnicas e prova SQLSTATE `42501` nas duas operações. Não
   existe listener de aplicação. A quarentena preserva todos os registros.
8. Autoridade ausente e uma chave cujo metadado foi alterado são recusadas.
   O segundo caso falha depois de updates anteriores na transação, permitindo
   verificar rollback real: os digests/sequence continuam idênticos à
   quarentena e nenhum LOGIN ou membership aparece.
9. A reconciliação válida exige o mesmo inventário e campos imutáveis; admite
   somente revogação/encurtamento de validade e revisão não decrescente. Não
   pode criar escopo, empresa, grant ou chave, trocar segredo ou ampliar prazo.
   A mesma transação aplica o estado atual, restitui os dois EXECUTEs originais
   e cria logins novos com senhas diferentes das antigas. Não há comando
   separado de provisionamento que ignore a autoridade atual.
10. As senhas da origem falham no destino. O probe P67 original confirma
    separação CREATE/READ, ausência de acesso direto às tabelas e RLS por
    empresa. Os FPMs separados e nginx originais são então iniciados.
11. HTTPS/mTLS real verifica leitura de B por ator atual diferente, retry
    idempotente com nonce novo, recusa do nonce consumido com MAC/TTL atuais,
    conflito de source_run, tentativa de mudar autoria no mesmo draft_ref,
    grant/chave de A revogados, ausência uniforme para empresa estrangeira e
    referência inexistente, e novo controle positivo de leitura.
12. O estado da origem após as revogações permanece exatamente igual durante
    todas as operações no destino. O dump também mantém seu digest. Por fim,
    somente containers/volumes com os tokens de ownership do job são removidos.

A preservação de owners/ACLs e o uso do snapshot externo seguem os contratos de
[`pg_dump`](https://www.postgresql.org/docs/16/app-pgdump.html) e
[`pg_restore`](https://www.postgresql.org/docs/16/app-pgrestore.html) do PostgreSQL
16. O banco tem nome fixo `imagine_agents_ci` e não publica portas no host.

## Material privado e limite da autoridade atual

O diretório privado tem modo `0700`; o dump custom é arquivo regular `0600`,
com tamanho limitado e SHA-256 conferido antes da restauração. Senhas e chaves
são geradas no job. Cada FPM recebe apenas sua própria senha de banco; o
browser não participa deste recorte. Dump, segredos, respostas de negócio e
logs de containers não são enviados como artifacts. A saída contém somente
marcadores fixos, contagens e proveniência pública dos commits.

O backup desta prova é temporário e local, protegido por permissões de acesso.
Não constitui um pacote durável cifrado, teste de retenção, restore em outro
host ou validação de armazenamento externo.

**A preservação de idempotência, nonces e auditoria termina no snapshot
restaurado.** O ensaio não recupera gravações ou nonces aceitos depois dele,
não prova RPO zero e não demonstra consistência de recuperação distribuída
entre CRM e Agents. A recusa de replay exercita um nonce efetivamente incluído
no dump. Uma recuperação operacional também precisaria tratar as janelas de
atestação ainda válidas, o material técnico atual e as revogações fora do
snapshot; este recorte não altera o runtime P67 para definir esse protocolo.

**A autoridade atual vem da origem fictícia ainda viva.** Isso permite produzir
uma revogação posterior ao snapshot e demonstrar que recuperar o snapshot não
a reativa. Não prova que essa fonte estará disponível após um desastre. Uma
recuperação operacional precisaria de uma fonte confiável de revogações atual,
independente do snapshot restaurado, e deveria permanecer fechada se ela
estivesse ausente ou inválida. Este incremento não inventa canal, custódia,
assinante ou disponibilidade dessa fonte.

O P68 não acessa R2, VPS, produção ou dados reais e não infere indisponibilidade
de nenhum destino já existente. Também não atesta a autenticação pessoal do
criador: as chamadas exercitam atestações técnicas fictícias no contrato P67.

## Lacuna correta do backup legado do CRM

A inspeção do CRM em `fe88dd15d42dbda0bab206dfdbc9f8ce65093212` identificou que
o [backup privado legado](https://github.com/rafhael100/imagine-crm/blob/fe88dd15d42dbda0bab206dfdbc9f8ce65093212/scripts/ops/backup-private-crm.py)
chama `detect_profile` antes de gerar seu dump. O [contrato de
inventário](https://github.com/rafhael100/imagine-crm/blob/fe88dd15d42dbda0bab206dfdbc9f8ce65093212/scripts/ops/private_crm_backup_contract.py)
exige igualdade exata de relações e ainda não inclui
`crm_private_owner_draft_intents`/`crm_private_owner_draft_grants` do
[addon](https://github.com/rafhael100/imagine-crm/blob/fe88dd15d42dbda0bab206dfdbc9f8ce65093212/infra/homologation/s2s/owner-drafts.sql).
Com essas tabelas presentes, o caminho é recusado por
`Incomplete or unknown private backup relation profile.`

Essa conclusão é de inspeção de código, sem executar o backup legado. A recusa
é correta: acrescentar nomes à lista sem atualizar e provar o perfil fechado
de RLS/ACL e restauração daria uma cobertura incompleta. O P68 não modifica esse
contrato. Recuperar também as intenções/grants locais do CRM exige seu próprio
recorte de fixture, snapshot, perfil e reconciliação.

Os ensaios Agents P25/P28 anteriores tampouco bastam para os seis conjuntos
P67. Suas [roles de recuperação](https://github.com/rafhael100/imagine-ai-agents/blob/c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8/runtime/central-auto/p25-recovery-roles.sql)
e [inventário P28](https://github.com/rafhael100/imagine-ai-agents/blob/c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8/runtime/central-auto/p28-state-probe.php)
não contêm as três roles e os registros P67. Este ensaio novo evita ampliar ou
reinterpretar resultados históricos e preserva seus arquivos literalmente.

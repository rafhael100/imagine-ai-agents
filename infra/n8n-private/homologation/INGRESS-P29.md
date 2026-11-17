# CENTRAL-AUTO-P29 — JSON inequívoco e validade após esperas

## Problemas corrigidos

O receiver P19 usava `json_decode` diretamente. Nomes repetidos em um objeto
eram reduzidos ao último valor, inclusive quando escritos com escapes
equivalentes. O handler também verificava a forma do evento, mas não exigia
que `lead_id` fosse uma referência UUID textual. Uma assinatura válida não
torna um payload malformado aceitável.

No banco, o instante de validação podia ficar desatualizado durante uma
espera por lock. A tentativa poderia alcançar o INSERT ou concluir depois
do prazo do envelope. Isso precisava ser recusado sem deixar uma inbox ou
uma confirmação de admissão da tentativa vencida.

## Compatibilidade do contrato

`strict-json.php` faz uma leitura limitada por bytes e profundidade antes
do decode. Confere nomes já decodificados em cada objeto e recusa duplicatas
em qualquer nível. O parser JSON do PHP continua validando a gramática,
Unicode, números e escapes. A leitura aceita a ordem de campos, espaços,
quebras de linha e escapes permitidos pelo JSON; não exige nova serialização
canônica.

O handler exige UUID textual em `company_id`, `event_id` e `lead_id`, sem
inventar uma versão de UUID para evento ou lead. A empresa conserva a
restrição v4 minúscula do contrato anterior, e `event_ref` continua ligado
ao UUID do evento. Os tipos e o conjunto fechado de campos do envelope
permanecem obrigatórios. Entradas malformadas recebem a mesma negativa
HTTP 403, antes de abrir conexão com o banco.

A referência do produtor foi inspecionada, somente para leitura, no CRM
`cca5055608acff8c767b888b6322df77fe966558`:
`infra/homologation/s2s/outbound-candidate.sql` utiliza colunas UUID para
empresa/evento/objeto e `app/Homologation/S2S/AgentsEventAdapter.php`
emite o evento de lead correspondente. Esta mudança não altera esse
repositório nem amplia o contrato de negócio.

O corpo, a assinatura e as decisões do ACK válido continuam iguais:
`LAB_RECEIVED_NO_DELIVERY` ou `LAB_DUPLICATE_NO_DELIVERY`, com dispatch
`DISABLED`. A consulta P27 mantém seu contrato separado e exige suas
concessões próprias.

## Validade no banco e rollback da tentativa

`receive_company_signed_no_ai` atualiza o relógio depois de adquirir os
locks de autorização e novamente depois das possíveis esperas de INSERT.
O envelope e o grant precisam continuar válidos. Os limites anteriores de
emissão e duração máxima também são reavaliados.

Inbox e auditoria de admissão ficam na mesma subtransação. Se o prazo
expirar depois de uma espera por unicidade ou durante a auditoria, uma
exceção SQL específica desfaz os efeitos daquela tentativa e retorna
`LAB_DENY_ENVELOPE`. O tratamento não apaga registros: uma linha vencedora
de outra transação permanece intacta.

Uma rejeição tardia não transforma a tentativa em confirmação durável.
Também não prolonga envelope, grant, lease ou validade de assinatura para
fazê-los caber no teste. A admissão válida e a idempotência existentes
continuam usando o mesmo catálogo e inbox.

## Ensaio integrado e evidência

O entrypoint `check_p29_ingress_boundaries.py` fixa o novo SHA256 do receiver
e reutiliza a matriz HTTPS/mTLS completa de P27 antes das regressões P29.
O entrypoint P27 padrão mantém seu pin anterior; uma mudança posterior do
receiver só pode usar a matriz por opção explícita do chamador revisado.

O probe CLI compara JSON válido com o decode padrão e cobre duplicatas
literais/escapadas, Unicode inválido, delimitadores, profundidade e tamanho.
Os 25 cenários HTTP usam assinaturas válidas sobre os payloads malformados;
o próprio driver confere o MAC e o hash antes de enviá-los. Duas formas JSON
válidas alternativas são admitidas e consultadas depois dessas negativas.

Quatro cenários concorrentes observam `pg_blocking_pids` e o relógio real do
servidor: espera pela chave, conflito único com COMMIT do escritor, conflito
único com ROLLBACK do escritor e espera na auditoria até o grant expirar.
As sessões SQL do ensaio têm prazos maiores para observar a corrida; os
timeouts do endpoint HTTP não mudam. Contagens e conteúdo conferem a
ausência dos efeitos da tentativa recusada e a preservação do vencedor.
Depois de cada corrida, um evento novo deve obter ACK e recibo válidos.

O wrapper RC71 executa a matriz P29/P27, a recuperação P25, a execução P26
com recuperação P28, as suítes de domínio RC65 e as regressões Python
anteriores na mesma árvore temporária. A CI da HEAD concreta deve passar
antes de considerar esta candidata validada; o check de uma base não a
substitui.

O ensaio isolado de ingresso passou na
[CI 44198509977](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44198509977),
SHA `25250ec6c9e5850a2729259ceccba1a4918ff2bc`, árvore combinada
`5abcc02aa04894555f09ed4232023b3b155567b7`. Executou o parser PHP,
HTTPS/mTLS P27 e os 25 negativos/quatro cenários concorrentes P29. Essa
evidência específica não substitui a matriz completa RC71 da candidata.

**DRAFT / NO-GO operacional.** Certificados, identidades e dados do ensaio
são sintéticos. O receiver continua sem despacho n8n. Esta correção não
constitui adoção pelo cliente CRM, autoridade IAM real, backup externo ou
autorização de instalação.

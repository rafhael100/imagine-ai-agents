# Captura durável dos eventos CRM para a Central de Automações

## Problema e resultado

O ingresso P29 valida os bytes de `CRM_LEAD_EVENT_V1`, mas sua inbox guarda
somente o hash. Uma tarefa posterior não consegue recuperar o `lead_id` ou
conferir o conteúdo a partir desse hash. Solicitar outro payload ao consumidor
perderia o vínculo com o evento originalmente autenticado.

Esta candidata acrescenta uma captura imutável dos dados mínimos já recebidos:
schema, ação e identificadores de empresa, evento e lead. Não busca nome,
e-mail ou conteúdo comercial. O receptor salva o conteúdo na mesma transação
da admissão e da auditoria; uma falha na captura desfaz essa tentativa inteira.

Base: P29 #463, commit `1838e7e13667c2f2c33d19b5b3eb469637a7c431`.
A linha de domínio Agents continua fixada na RC65
`3fb86c5fb011bc9df1f12a4a30a7c094370b6872`, combinada somente em árvore
temporária de teste. Nenhuma branch existente é promovida ou mesclada.

## Ativação explícita no laboratório

O perfil `AGENTS_PRIVATE_S2S_PROFILE=synthetic-private-v1` continua obrigatório.
O novo modo de servidor `AGENTS_PRIVATE_LEAD_CAPTURE=immutable-v1` seleciona a
função `auto_ia_lab.receive_company_signed_lead(uuid,text,jsonb,text)`.
O schema adicional precisa estar instalado no banco descartável antes desse
opt-in. Configuração inválida ou função ausente falha sem retornar um ACK de
sucesso. Uma variável ausente ou vazia preserva o ingresso anterior.

O HTTP chama a nova função em um único statement. Não há sequência de dois
commits, callback depois do ACK, ou gravação feita pelo navegador.
Os bytes usados no SHA-256 são os mesmos bytes persistidos, inclusive ordem,
espaços e escapes JSON válidos. O SQL revalida o conteúdo plano e seu vínculo
ao envelope antes de reutilizar a admissão P29.

## Duplicidade, origem e recuperação

- Uma duplicata precisa corresponder à empresa, tenant, evento e conteúdo
  imutáveis. Nenhum payload existente é sobrescrito.
- A autoridade atual é revalidada; a proveniência da primeira captura permanece
  histórica. Uma revisão de concessão não reescreve essa proveniência.
- Um recibo anterior, sem captura correspondente, não recebe preenchimento
  retroativo automático. A migração desse histórico exige tratamento próprio.
- Uma espera que ultrapasse a validade desfaz a tentativa atual, incluindo a
  inbox e a auditoria que ela havia inserido. Registros de outra transação
  vencedora permanecem. Sequências PostgreSQL podem avançar mesmo com rollback;
  as provas comparam registros persistidos, não a continuidade de IDs.
- A relação com a inbox tem chave estrangeira e a tabela mantém RLS forçada.
  O papel de transporte acessa somente a função, sem ler ou alterar a tabela.
- O ensaio novo restaura o banco em outro cluster e compara explicitamente o
  conteúdo capturado e os controles de acesso. O baseline P28 de 17 tabelas
  não é usado como evidência suficiente para uma tabela que ele não conhecia.

## Recebimento e execução têm contratos separados

O ACK `AUTO_IA_ACK_V1` e a consulta P27 conservam `DISABLED` e
`NOT_DISPATCHED`. A captura não concede execução e não inicia um worker.
A operação correta permanece `imagine-crm / lead.ingest`; não se transforma
um lead em `mock.run.completed / run.status.project` para utilizar a P26.

A ligação seguinte precisa de contrato explícito de tarefa de lead,
autorização de execução por operação e vínculo à captura autenticada. Deve
reaproveitar a fila existente e o tratamento durável de resultado incerto,
preservando as regras comerciais no CRM. Esse trabalho não é declarado
concluído pela captura.

## Verificação

`tools/run_crm_lead_capture_virtual_tree.sh` executa os controles existentes
de segurança, domínio, recuperação e transporte na árvore candidata, além
do novo ensaio HTTP/mTLS de captura, concorrência, rollback e restauração.
O workflow é restrito a runner GitHub hospedado e repositório público, com
token somente leitura e nenhum segredo operacional.

O resultado deve ser consultado na CI da HEAD exata. Testes locais de sintaxe
não são relatados como execução de PostgreSQL, PHP ou Docker quando esses
runtimes não estão disponíveis.

**Estado operacional: NO-GO.** Certificados, dados e concessões são de teste.
Esta entrega não instala o n8n na VPS nem comprova backup externo no R2,
custódia real de chaves ou integração operacional de clientes.

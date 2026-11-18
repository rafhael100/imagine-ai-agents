# AIA-P60 — Modelo de leitura para pareceres assinados no painel

16/11/2026. Camada de apresentação pura, sobre P59 (#443). Nenhuma permissão operacional.

## Entrega

\`src/Automation/SignedReviewQueueReadModel.php\` reúne dois contratos estritos: a fila sintética P53 e os estados sintéticos assinados e revalidados da P59. Para cada proposta, exibe quantidade de pareceres, papéis PRODUCT/SECURITY e estado: aguardando, parcial, duas observações, ou rejeitada.

O componente não chama PostgreSQL, Portal, CRM ou n8n. Recebe apenas resultados já validados externamente; exige correspondência exata entre os identificadores de propostas e os estados fornecidos, recusa empresas diferentes, contadores falsos, permissões implícitas, papel desconhecido, revisões duplicadas, estados contraditórios, registro ausente e campo extra. Os dados são de demonstração, e o componente não é uma autorização entre serviços.

Mesmo com duas observações assinadas, a saída continua \`can_approve=false\`, \`can_execute=false\` e \`operational_authorization=false\`. Referências de identidade individual do revisor não são incluídas no resultado de apresentação.

## Verificação

CI composta com P13–P59, browser PostgreSQL16 isolado e n8n PostgreSQL17 independente. Os testes P60 incluem os quatro estados, 1.300 cenários pseudoaleatórios com zero ações, validação de empresa e negação de status falso ou adulterado.

## Para uso real

Precisaremos de um adaptador de leitura que consulte a fila e os pareceres numa transação coerente de banco, vinculada a um IAM do CRM/Portal. O modelo de apresentação P60 **não** transforma votos sintéticos em autorização, e o executor permanece inativo.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**

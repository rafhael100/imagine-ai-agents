# AIA-P48 — Motor determinístico NO_AI no domínio dos Agentes

16/11/2026. Incremento sobre P47 (#423). PR de laboratório, não operacional.

## Entrega de produto

Nova classe reutilizável de domínio em PHP: src/Automation/DeterministicAutomationService.php. Uma entrada de comando fechada, com referência sintética de empresa e request, projeta os resultados de um funil a partir de visitas e duas taxas. As taxas são expressas em pontos-base inteiros (10.000 = 100%), com arredondamento inteiro half-up, sem ponto flutuante, acesso ao banco, dependência de n8n, API de IA, custo ou comportamento probabilístico.

O comando funnel.calculate retorna visitas, leads estimados e clientes estimados junto com NO_AI, LAB_PREVIEW_ONLY, zero chamadas, zero tokens, zero débitos e operational_authorization=false. Trata-se de **projeção aritmética**, nunca afirma quantidade real de leads, resultado de campanhas ou criação de cliente no CRM.

## Validação

- Paridade do vetor da RC07 oficial do n8n: 120 visitas, taxa de 10%, 12 leads projetados, 20% deles, 2 clientes projetados.
- 6.000 cenários determinísticos com limites: nenhuma etapa aumenta a quantidade sem autorização da taxa, zero/100%, valores extremos sem overflow e comportamento reprodutível.
- Falha fechada para valores negativos, percentuais fora da faixa, parâmetros de tipo errado, campos extras, ausência ou troca na ordem de campos, operação não permitida e identificadores malformados.
- CI P48 herda as regressões P13–P47, mantendo o segundo job n8n PG17 separado e somente leitura.

## Limites

O serviço de aplicação aceita somente referências SYNTHETIC_* e é apenas um preview; NÃO autentica empresa, não verifica permissões reais do CRM/Portal, não cria entidades CRM, não publica workflows, não faz persistência, não entrega mensagens e não usa provedor externo. A CI do n8n ocorre em outro job e a paridade é de um **vetor fixo**, não de uma integração live.

Próximas etapas: integrar o cálculo às decisões determinísticas de automação, adicionar versão e idempotência controlada em armazenamento aprovado e contrato read-only da UI, sempre com autorização do serviço proprietário e controle de dados reais antes de disponibilizar a clientes.

**DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-SPEND.**

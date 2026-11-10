# R01/EVID-10 — Conformidade bilateral do formato de resposta OP-01 (offline)

**Data:** 09/11/2026. **Classificação:** evidência técnica sintética, não contrato final do CRM. **Decisão operacional: NO-GO**.

## Objetivo
A proposta [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) descreve somente a intenção de uma leitura mínima de lead. O Agents já verificava a conformidade do **pedido** (`SYNTHETIC_CRM_AIA_READ_V1`), mas faltava um contrato independente para as **respostas realmente geradas** pela fachada PHP. A divergência entre a implementação e o formato negociado pode causar ampliação acidental de campos ou negação inconsistente.

## Entregáveis
- `contracts/crm-aia-public-response.v1.synthetic.schema.json`: schema de saída fechado, sem propriedades adicionais, com `real_authorization=false`, `external_calls=0` e os estados `READ_SYNTHETIC_ONLY` ou `DENIED_OR_NOT_FOUND`.
- Uma resposta aceita exige projeção não vazia de **somente** `ref` e/ou `stage`, com valores sintéticos. A resposta negada exige `projection=null`; não revela motivo interno, empresa ou identificador real.
- `tools/op01_php_public_response_probe.php`: chama a **fachada PHP de verdade, com objetos estritamente fictícios**, em seis cenários. Inclui leitura de um campo, projeção em ordem distinta, tentativa repetida (replay), política sem grant, divergência entre empresas e campo desconhecido.
- `tools/check_op01_public_response_parity.py`: restringe o subconjunto de schema aceito, executa o probe PHP local, verifica o formato exato, a ordem dos campos solicitados e a uniformidade de recusas; falha se propriedades ou escopos forem ampliados.
- `tests/Contracts/test_op01_public_response_parity.py`: regressões negativas contra resposta com campo extra, `ALLOW`, `real_authorization=true`, emissão de dados de cliente, valores inválidos, tipo incorreto, campos duplicados e enfraquecimento do schema.

## Evidência de revisão
A CI unificada (`tools/run_r01_offline_quality_gate.sh`) agora executa o verificador e sua suíte sem rede ou dependências novas; confere também lint do probe. Mantém o baseline oficial do núcleo: **339 casos PHP / 79 arquivos PHP**. Os testes de contrato Python são verificações adicionais e **não** foram contabilizados artificialmente como casos PHP.

**Limite técnico:** a validação comprova **somente paridade de formatos e comportamento dos mocks**. O schema de resposta não é autenticação, autorização do CRM, prova de origem, privacidade operacional, SLA, endpoint real, integração de rede ou homologação dos campos reais de lead. O CRM permanece a autoridade sobre `tenant`, principal, recurso, finalidade, campos e respostas de serviço.

## Gates não resolvidos
Para a primeira operação real `crm.lead.read_minimal`, ainda faltam G01/G02/G05/G08/G10/G11 e CRM CORE/SEG/AUT, além dos demais gates R01 aplicáveis. O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua DRAFT; sem merge, token ou rota de integração. Sem mudanças na `main`, VPS, clientes, credenciais, provedores, despesas ou produção.

**Próximo passo condicionado:** revisão escrita e aceite bilateral do contrato operacional, da identidade, dos campos e das políticas; depois, ambiente de teste autorizado para validação da implementação real. Nenhuma CI sintética libera esses gates.

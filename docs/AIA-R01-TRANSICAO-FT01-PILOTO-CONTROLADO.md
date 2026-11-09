# Imagine AI Agents — Transição dos mocks FT01 para piloto controlado

**Situação em 09/11/2026:** preparação exclusivamente documental, não autorização de integração. Os recortes sintéticos FT01/F1-01 a F1-10 estão integrados à **dev**; último squash `70d81bf58d3e3e0b1e3ac87250538451a9c166e9`. A `main` permanece `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`. Nenhum serviço real foi ativado.

## 1. Resultado atingido, sem extrapolação

O laboratório tem orquestração sintética, segregação simulada de contexto, autorizações fictícias, ferramentas e idempotência mockadas, cotas sintéticas, catálogo/versionamento de agentes, recuperação de conhecimento marcado não confiável, comparação A/B com regressão crítica eliminatória e, no F1-10, revisão simulada de Produto e Segurança com digest/revogação.

A suíte PHP executada tem **111 testes e zero falhas**, **52/52 lints PASS** e **52/52 hashes de blobs Git idênticos** ao head integrado. Isso **não valida autenticação real, autorização dos produtos, LGPD operacional, throughput, custos, continuidade, rastreamento assinado nem produção**.

## 2. Dependências antes de conectar o primeiro produto

Fonte: `docs/AIA-R01-CONSOLIDACAO-TRANSVERSAL-GATES.md`, com os gates R01-G01 a G11 mantidos ABERTOS.

| Gate | Entrega mínima verificável necessária | Estado |
| --- | --- | --- |
| G01 | Identidade canônica serviço/empresa/usuário, issuer/audience e trust boundaries documentados e testados | Aberto |
| G02 | Produto dono autoriza cada recurso/escopo, incluindo testes negativos entre tenants | Aberto |
| G03 | Máquina de estados com idempotência, confirmação externa, timeout e reconciliação | Aberto |
| G04 | Alçadas reais, aprovador autenticado, revisão do conteúdo exato, revogação e delegação | Aberto |
| G05 | Mapa LGPD de finalidades, dados, provedores, retenção e expurgo | Aberto |
| G06 | Cotas técnicas/comerciais, reserva, preço versionado e conciliação | Aberto |
| G07 | Suíte de segurança com aceite de revisores e evidência de laboratório integrado | Aberto |
| G08 | Contrato versionado com CRM; validação de titularidade e rollback | Aberto |
| G09 | Licença de n8n e dependências de execução, conforme uso comercial pretendido | Aberto |
| G10 | Topologia/isolamento, segredos, logs, filas, backups, RTO/RPO e operações | Aberto |
| G11 | MVP piloto, orçamento, responsáveis, janelas de implantação e aprovação executiva | Aberto |

Os gates próprios do CRM (CORE/SEG/AUT) também continuam abertos. Não aprovar nenhum como consequência automática dos 111 testes de mock.

## 3. Ordem técnica recomendada, sem precisar de despesas

1. **F1-11 documental / contratos sintéticos:** contratos de confiança serviço-serviço, contexto, audiences, recursos e negação uniforme. Gerar testes de contrato com atores e recursos exclusivamente fictícios; **não emitir credencial real**.
2. **F1-12 documental / read-only boundary:** definir primeiro piloto CRM com consulta limitada a contexto autorizado, resumo e sugestão; negar escrita por padrão. Não acessar endpoints do CRM até suas políticas serem homologadas.
3. **Revisão transversal de segurança:** ownership por produto, correlação, rollback, timeout, recuperação de dados, logs mínimos, retenção e teste contra acesso cross-tenant. Acompanhamento de G01–G10 com responsáveis.
4. **Gate executivo crítico G11:** escopo de integração real, infraestrutura isolada, teto de custo explícito e responsável por segurança/aceite. Só após isso contratar/ativar tecnologia ou tocar VPS/credenciais.
5. **Piloto supervisionado em desenvolvimento:** integrar primeiro o caminho somente leitura à API autorizada do CRM, com acessos de teste segregados, provas negativas e nenhum envio a LLM externo sem análise própria.
6. **Go/no-go para produção:** homologação completa, proteção LGPD, métricas, monitoramento, rollback, recuperação e aceite explícito. Até lá, `main` e produção preservadas.

## 4. Decisões que só o responsável pode tomar

A autorização ampla existente permite trabalhos **offline/sintéticos em branches isoladas**, com merge controlado na dev. Não autoriza habilitar dados privados ou APIs, gastar com fornecedores, mudar `main`, VPS ou produção.

**Decisão crítica futura**: autorizar (ou não) o início do desenho executivo da integração real **somente leitura, isolada em desenvolvimento**, sujeito antes ao fechamento de G01/G02/G05/G08/G10/G11 e dos gates CRM aplicáveis. Essa decisão deve especificar escopo, credenciais de teste, papéis, teto de custos e ausência de produção. Não pressupor aprovação por silêncio.

## 5. Resultados esperados do próximo ciclo sem aprovação adicional

- Especificação testável dos contextos serviço/usuário/empresa e dos limites de autoridade, sem tokens.
- Suítes de contrato para produto, recurso e finalidade sintéticos, incluindo negação cross-tenant e replay de contexto.
- Matriz mínima de evidência do piloto e itens de gate rastreáveis nos PRs.
- Nada em `main`, outros repositórios, VPN/VPS, CRM real, fornecedores pagos ou dados de clientes.

**Conclusão:** a fase de protótipos mockados tem uma base funcional testada, mas **o projeto operacional não está concluído**. A próxima transição depende da segurança e das autorizações reais; novas telas fictícias isoladamente não substituem esses requisitos.

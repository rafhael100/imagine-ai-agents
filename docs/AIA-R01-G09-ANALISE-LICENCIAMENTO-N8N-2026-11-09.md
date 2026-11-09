# AIA-R01/G09 — Análise preliminar do licenciamento n8n para o ecossistema Imagine

**Pesquisa:** 09/11/2026. **Estado vigente:** [PR #122](https://github.com/rafhael100/imagine-ai-agents/pull/122) incorporado somente à dev (squash `ab31c28afeac4cf75fa3e09a33cec9db37916865`). Parecer técnico **preliminar** / **G09 ABERTO**, não concluído nem homologado. **Não é opinião jurídica final nem autorização de uso comercial.** Sem contratação, provisionamento ou execução de n8n.

## 1. Fontes oficiais verificadas

1. [n8n Help Center — Which license do I need for my use case?](https://support.n8n.io/article/can-i-use-your-license-for-my-use-case), atualização publicada **06/10/2026**. A orientação distingue fluxo gerido pela própria organização, cliente que altera lógica por UI/API/MCP/agente e oferta de n8n como serviço; a equipe de suporte informa que não confirma conclusivamente casos específicos.
2. [n8n OEM / Embedded iPaaS — FAQ](https://n8n.io/oem/), consultada em 09/11/2026. Faz distinção entre n8n como backend de produto comercial (Enterprise) e disponibilização do editor aos clientes (OEM).
3. [n8n — LICENSE.md](https://github.com/n8n-io/n8n/blob/master/LICENSE.md), licença Sustainable Use License. Há restrições a usos comerciais não internos e arquivos Enterprise separados.
4. [n8n Docs — Community license](https://github.com/n8n-io/n8n-docs/blob/main/docs/n8n-community-license/README.md), consulta em 09/11/2026. Fonte de apoio, não contrato específico.

**Ponto de ambiguidade relevante:** a FAQ publicada em outubro descreve como admissíveis alguns serviços geridos exclusivamente pela agência, incluindo fluxos executados para clientes. A página OEM, por sua vez, descreve backend integrado a produto SaaS como modalidade Enterprise mesmo sem exibir editor ao usuário. Não presumir que a FAQ genérica dispensa um contrato comercial quando o n8n é parte do produto Imagine oferecido a múltiplos clientes.

## 2. Matriz de uso no Imagine — avaliação condicional

| Cenário | Operação pretendida | Indicação preliminar | Conduta |
| --- | --- | --- | --- |
| G09-S01 | Equipe Imagine cria e opera internamente fluxos próprios, sem controle do cliente sobre a lógica e sem oferecer n8n como serviço | Community pode ser compatível conforme FAQ, sujeito à finalidade exata | Validar por escrito escopo e credenciais, não conectar antes dos gates |
| G09-S02 | Imagine CRM permite que cliente configure ou altere automação que é traduzida para n8n por API, UI, MCP ou agente | FAQ oficial aponta necessidade de licença comercial | **Não implementar via Community** |
| G09-S03 | Imagine Agents usa n8n como motor interno de uma funcionalidade SaaS, disparada por usuários do CRM/Portal e com resultado ao cliente | Página OEM apresenta backend de produto como Enterprise; FAQ genérica requer reconciliação | **Tratar como possível exigência Enterprise**, pedir confirmação escrita |
| G09-S04 | Imagine oferece editor/canvas n8n ao cliente, embutido ou por acesso direto | OEM separado, não Community | **Fora do escopo inicial** |
| G09-S05 | Motor próprio do Imagine CRM executa regras configuradas pelo cliente; n8n não executa nem recebe suas configurações | Não depende do direito de uso do n8n para aquela automação | **Arquitetura preferida** para automações do cliente |

Os cenários não equivalem a licença outorgada ou parecer legal. A matriz CSV rastreável está em `docs/evidencias/aia-g09-matriz-uso-n8n.csv`.

## 3. Diretriz técnica provisória de menor risco e custo

- **Imagine CRM** mantém o motor nativo de automações dos clientes; nada de espelhar ou compilar configurações do cliente para n8n Community.
- **Imagine Agents AI** permanece dono da execução IA, ferramentas e escopos; não deve depender obrigatoriamente de n8n no núcleo de negócio ou como condição de habilitar um tenant.
- **n8n central** é opção futura para tarefas operadas exclusivamente pela própria Imagine, com workflows predefinidos/geridos por operadores internos. Ainda assim validar se cada fluxo é uso interno ou backend de produto SaaS.
- Fluxos oferecidos a clientes pelo SaaS devem ter **alternativa técnica sem n8n** se licenciamento Enterprise for necessário e não houver orçamento aprovado. Candidatos para avaliação futura: serviços PHP/Laravel com jobs/filas no backend sob políticas próprias, sem implementar aqui.
- Credenciais de clientes, canais OAuth de usuários, gatilhos e acesso indireto ao editor por IA/API merecem avaliação específica antes de qualquer projeto n8n real.
- Não contratar Enterprise/OEM nem desativar arquitetura existente como decisão automática; o objetivo deste registro é evitar incorporação inadvertida de risco contratual.

## 4. Questões que exigem resposta do licenciador / jurídico

1. Uso do n8n como backend multiempresa invisível aos clientes Imagine, com fluxos criados só pela Imagine mas disparados pelo cliente no CRM, cai em SUL ou Enterprise?
2. Cliente fornece credenciais próprias de canais e recebe resultado de fluxos administrados exclusivamente pela equipe Imagine: isso altera categoria de licença?
3. Quais parâmetros do cliente configuram execução (horários, filtros, alvos, lógica, gatilhos) a ponto de serem considerados edição indireta por API/AI?
4. É permitido oferecer serviços de implantação geridos na infraestrutura própria para clientes distintos sob Community? Onde termina serviço gerenciado e começa SaaS/OEM?
5. Quais termos e custos se aplicam a um backend SaaS multiempresa com n8n, e qual alternativa existe sem compromisso comercial inicial?

Contatos **publicados pelo n8n**: `license@n8n.io` para esclarecer permissão de uso, `sales@n8n.io` para termos comerciais. **Nenhuma consulta foi enviada nem compra realizada** nesta etapa.

## 5. Evidência exigida para revisão de G09

Para eventual fechamento do G09, exigem-se: definição precisa de topologia/fluxos e direitos de clientes; resposta escrita oficial n8n ou parecer jurídico fundamentado com versão da licença e escopo comercial; classificação de cada caso implantado; registro de riscos/contratos/custos; aceite de responsável. Os demais gates R01-G01..G11 e CRM CORE/SEG/AUT são independentes e continuam abertos.

**Resultado atual:** `LICENSING_REVIEW_REQUIRED` / **G09 ABERTO** / **NO-GO para backend SaaS real em n8n Community sem confirmação**.

# FT01/F185 — Configurador de rascunhos de agentes no laboratório F108

**11/11/2026. Protótipo local sem serviços, cliente, autenticação ou publicação. Operação real: NO-GO.**

## Entrega utilizável no navegador

A página **Agentes fictícios** do `examples/f108-laboratorio-navegavel.html` ganhou um configurador de rascunhos com layout responsivo. O usuário pode escolher um dos **três modelos fechados** (Qualificação CRM, Análise de Performance ou Tutor de Conhecimento), informar um nome de 3 a 48 caracteres e pré-visualizar o objetivo e o estado **RASCUNHO LOCAL · SEM APROVAÇÃO**.

A opção **Ver cenário vinculado** abre a página já existente do laboratório com um teste fixo para o modelo escolhido: CRM → `U07-01` (recurso autorizado fictício); Performance → `U07-08` (dados dinâmicos exigem fonte oficial); Universidade → `U07-09` (texto hostil é dado não confiável). **Abrir a página de cenário não o executa nem emite uma leitura real**.

As entradas não podem alterar o tipo de ferramenta, autorização, tenant ou o próprio cenário. O catálogo é `Object.freeze` com verificação de propriedade própria, rejeitando nomes de modelo como `__proto__`. Nomes são normalizados para NFC, limitados antes de qualquer regex e aceitam apenas letras Unicode, números e sinais simples. HTML/instruções maliciosas nunca são interpolados: a prévia usa `textContent`, sem `innerHTML`, `eval` ou instruções dinâmicas. Alterar nome/modelo **invalida o rascunho anterior** e desabilita o botão de teste. Limpar descarta a configuração e restaura o primeiro modelo.

Todos os estados vivem apenas na memória do navegador; não há `localStorage`, endpoint, SQL, rede, upload, cobrança, seleção de IA real, snapshot publicado ou criação de cliente. O nome não é enviado a um modelo, CRM ou sistema operacional. O configurador **não** faz parte do Portal de produção e não autoriza um agente autônomo.

## Testes e resultado esperado

O `tests/Browser/f108_smoke.cjs` exercita os três modelos, habilitação inicial, rascunho válido, navegação ao cenário sem execução, invalidação após alterar os campos, recusa a marcação HTML, nomes excessivos, tentativa de protótipo `__proto__`, reinício e preservação da trilha de auditoria.

O `tests/Contracts/test_f185_agent_draft_workbench.py` inclui sete casos obrigatórios sobre acessibilidade, catálogo fechado, renderização segura, invalidação, cenários adversariais cobertos pelo Node smoke, ausência de rede/armazenamento/efeitos e CSS mobile. Os sete testes entram no gate offline, com Node smoke executado quando o runtime estiver disponível.

```bash
node tests/Browser/f108_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f185_agent_draft_workbench.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=97 EXPECTED_CASES=776 bash tools/run_r01_offline_quality_gate.sh
```

O incremento é HTML/CSS/JS e testes Python/Node, portanto **mantém o baseline PHP 776 casos e lint 97 arquivos**. CI de PR no SHA revisado e CI pós-merge continuam obrigatórias antes de alegar integração completa.

## Bloqueios de serviço

O produto real ainda necessita autenticação, definição de permissões por empresa, catálogo persistente, cadastro/versionamento de agente, workflows, tutoria de IA e infraestrutura real. Não confundir uma prévia de UX com criação funcional de um agente no SaaS. CRM PR #57 OPEN/DRAFT; 13 cenários OP-01 reais `NAO_EXECUTADO`; R01-G01..G11 e CORE/SEG/AUT **NO-GO**. Sem `main`, VPS, clientes, segredos, dados pessoais, produção ou gastos.

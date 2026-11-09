# FT01/SEC-02 — Validação estrita dos contextos sintéticos

**Escopo:** endurecimento de objetos PHP de laboratório. Não equivale a autenticação, autorização, RLS, ACL, identidade de serviço, homologação do CRM nem prontidão de produção.

## Inconsistência encontrada

Antes deste recorte, `ImagineAI\\Domain\\TenantContext` aceitava qualquer string que **começasse** com `SYNTHETIC_`. Isso incluía `SYNTHETIC_` sem identificador, controles, letras minúsculas e sufixos acima do limite de 80 caracteres, enquanto o contrato sintético OP-01 já exigia `^SYNTHETIC_[A-Z0-9_]{1,80}$`. Uma discrepância entre objetos de contexto e parser pode produzir comportamentos divergentes em testes e mascarar bugs de integração futura.

## Correção

O construtor `TenantContext` passa a aplicar a expressão estrita usada no contrato OP-01 a cada uma de suas cinco dimensões: produto, empresa, principal, finalidade e recurso. Mantém a versão lógica `mock-v1` e a mesma falha `INVALID_SYNTHETIC_REF` para valores inválidos. Não aceita IDs reais nem amplia permissões.

`tests/Unit/context_ref_strict.php` adiciona 12 casos: valor máximo, ausência de sufixo, minúsculas, pontuação, controles, Unicode, comprimento excessivo, todas as cinco dimensões, versão e igualdade entre empresas. A suíte oficial passa de 228 para **240 testes** e de 73 para **74 arquivos PHP**. O validador em `tools/validate_ft01_offline.sh` e a CI unificada são atualizados para essas metas. **Resultados comprovados:** [PR #133](https://github.com/rafhael100/imagine-ai-agents/pull/133) integrado somente à dev (`07088bb58c2e4f4a3d68e11091de50737f875368`), com [CI de PR #42632772738](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632772738) e [CI integrada #42632952682](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42632952682), ambas `success`: **240/240 casos PHP e 74/74 verificações de sintaxe**.

## Restrições

Dados exclusivamente fictícios. Sem rede, banco, LLM, segredos, API, alterações no CRM, servidores, fornecedores pagos ou `main`. Gates R01-G01..G11 e CRM CORE/SEG/AUT continuam abertos; contrato CRM PR #57 DRAFT, estado operacional NO-GO.

# FT01/F187 — Exportação e importação manual de rascunhos sintéticos

**11/11/2026. HTML/JS offline, sem API, CRM, armazenamento ou serviço externo. Operação real: NO-GO.**

## Funcionalidade do produto

O catálogo de rascunhos F186 no laboratório navegável F108 ganhou uma área de **transferência manual** com campo JSON e três ações: **Gerar JSON para copiar**, **Validar e importar JSON** e **Limpar somente o campo**. O usuário pode copiar o texto para um arquivo por conta própria, colá-lo depois e restaurar os rascunhos de demonstração, sem persistência automática no navegador ou servidor.

O formato é estritamente fechado:

```json
{
  "version": "SYNTHETIC_AGENT_DRAFTS_V1",
  "drafts": [
    {"name": "Agente Alfa", "template": "crm"},
    {"name": "Tutor Beta", "template": "universidade"}
  ]
}
```

O campo de versão identifica apenas um **contrato fictício de UX**, não um protocolo de produção, assinatura digital ou autorização. Cada item contém somente nome limitado e chave de um dos três modelos fechados: `crm`, `performance` ou `universidade`. Não há IDs reais, texto de prompt, empresa, cliente, token, permissão, credencial ou configuração de execução.

## Regras de proteção e integridade

- Máximo de **4096 caracteres por pacote** e até **5 rascunhos**. Esquemas diferentes, propriedades desconhecidas no objeto principal/itens, tipos incorretos e modelos desconhecidos são recusados.
- Nomes precisam estar normalizados em NFC, usar apenas os caracteres permitidos por F185, ter entre 3 e 48 caracteres e ser únicos após comparação case-insensitive em `pt-BR`. Um mesmo nome em dois modelos também é recusado.
- **Importação atômica:** primeiro valida o documento completo em um array local `staged`; somente depois substitui a coleção. Um segundo item malicioso não pode deixar o primeiro importado e apagar a lista anterior. Mensagens de erro genéricas não ecoam conteúdo injetado.
- Exportação gera apenas JSON na `textarea`. Não há upload, download automático, `navigator.clipboard`, `fetch`, `WebSocket`, cookie, `localStorage`, `sessionStorage`, SQL nem `innerHTML`.
- Uma importação validada é uma **substituição explícita** da lista fictícia: pacote com `drafts: []` limpa a lista; importação inválida **não altera nada**. O botão de limpar campo JSON não altera a coleção.
- A prévia ativa é invalidada após importação válida para evitar contexto antigo; nenhuma importação cria agentes reais, executa cenários ou autoriza publicação.

## Testes

O `tests/Browser/f108_smoke.cjs` testa exportação do esquema exato, limpeza isolada do campo, exportar/limpar/importar/restaurar dois modelos, importação inválida com texto não-JSON, versão desconhecida, campo adicional, modelo `__proto__`, role inesperada, duplicidade por caixa, segundo item HTML malicioso, seis itens, entrada excessiva, e importação vazia explicitamente autorizada.

Sete regressões Python obrigatórias em `tests/Contracts/test_f187_draft_transfer.py` protegem os controles, esquema, validação atômica, nomes, cobertura DOM, ausência de rede/persistência e manutenção da coleção sob falha.

```bash
node tests/Browser/f108_smoke.cjs
python3 -m unittest discover -s tests/Contracts -p 'test_f187_draft_transfer.py' -v
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=100 EXPECTED_CASES=810 bash tools/run_r01_offline_quality_gate.sh
```

O baseline PHP permanece **810 testes / lint 100**, porque F187 acrescenta apenas HTML/JS, Python/Node e documentação. Uma revisão de segurança independente de produto persistente ainda não foi executada; esta área é **um protótipo**, não um mecanismo de backup confiável de produção.

## Gates reais ainda bloqueados

O CRM PR #57 continua OPEN/DRAFT, treze cenários OP-01 reais `NAO_EXECUTADO`, e R01-G01..G11/CORE/SEG/AUT **NO-GO**. Permissões, criptografia, identidade de revisor, persistência de versões, retenção LGPD e integração Portal/CRM exigem trabalho adicional. Sem `main`, deploy, VPS, clientes, segredos ou custos.

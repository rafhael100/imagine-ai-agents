# FT01/F199 — Matriz de escopo e limites no rascunho de agente

**11/11/2026. Preview local exclusivamente demonstrativo. Operação real: NO-GO.**

## Entrega

O configurador de rascunhos F185 no laboratório F108 passa a explicar, para cada modelo fechado de CRM, Performance e Universidade, **a fonte sintética de dados que seria permitida, as ações não permitidas e os requisitos ainda pendentes para liberação real**.

- **CRM:** referência e resumo mínimo fictícios; sem alteração de leads, leitura entre empresas ou envio de dados reais; depende de CRM CORE/SEG/AUT e R01-G01..G11.
- **Performance:** cenário fictício sem contas conectadas; não lê saldos nem cria/edita anúncios; exige integração e autorização independentes do produto.
- **Universidade:** documento de exemplo marcado como UNTRUSTED_DATA; não executa ferramentas por instruções recebidas no conteúdo; depende de proveniência, revisão e permissões.

Os três textos vêm de um catálogo fixo e imutável, não do nome digitado pelo usuário ou de dados importados. O preview se apaga quando a configuração muda ou é limpa. Os pacotes F187 continuam contendo **somente** nome e template e nunca carregam papéis, escopos de CRM ou tokens.

## Verificação

Cinco contratos Python e smoke DOM Node atualizado cobrem os três modelos, apagamento de campos, negação de novos poderes e ausência de alteração do pacote de transferência F187. Adicionados ao gate offline, mantendo o baseline **835 testes PHP / lint 103**.

```bash
python3 -m unittest discover -s tests/Contracts -p 'test_f199_agent_scope_preview.py' -v
node tests/Browser/f108_smoke.cjs
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=103 EXPECTED_CASES=835 bash tools/run_r01_offline_quality_gate.sh
```

Sem CRM ou Performance reais, chamada de IA, IAM, autenticação, banco, deploy, VPS, clientes, credenciais ou novos custos. CRM PR #57 OPEN/DRAFT, os 13 cenários OP-01 reais NAO_EXECUTADO e gates R01-G01..G11/CORE/SEG/AUT NO-GO. Main permanece intacta.

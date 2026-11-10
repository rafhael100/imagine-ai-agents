# R01/EVID-19 — Pacote bilateral de aceite OP-01, sem aprovação automática

**Data:** 10/11/2026. **Estado:** pacote de revisão documental gerado localmente; **NO-GO** para integração real, piloto e produção.

## Entrega

O gerador offline `tools/generate_op01_bilateral_packet.py` vincula três fontes existentes, sem acesso à rede:

1. [Proposta documental do CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57), guardada por HEAD e Git blob exatos em `contracts/reviews/crm-aia-ro-01.pr57.manifest.json` e `contracts/reviews/crm-aia-ro-01.pr57.snapshot.md`.
2. `docs/evidencias/aia-r01-gates-op01.csv`: 11 gates R01 e 3 dependências CRM CORE/SEG/AUT, todos ABERTOS ou EM_REVISAO, **nunca aprovados por CSV**.
3. `docs/evidencias/op01-casos-homologacao.csv`: 11 cenários negativos e 2 positivos, todos marcados `NAO_EXECUTADO`.

O resultado versionado é [op01-pacote-aceite-bilateral.json](evidencias/op01-pacote-aceite-bilateral.json), contendo **sete decisões OP01-R01 a OP01-R07**, responsáveis técnicos sugeridos, gates vinculados, evidências pendentes e cenários que precisam ser executados. As mesmas evidências podem ser necessárias a diferentes revisores; as sete decisões, em conjunto, cobrem **todos os 14 gates e 13 cenários**.

**Isso não é um aceite:** `decisao_piloto=NO_GO`, `integracao_autorizada=false`, `api_crm_verificada=false`, `permite_credenciais=false` e `permite_deploy=false` são invariantes do gerador e dos testes. Passar na CI comprova integridade estrutural do inventário e paridade com a proposta CRM pinada, **não autenticidade de evidências de homologação real**. O pacote não identifica pessoas que tenham efetivamente aprovado algo.

## Como conferir

```bash
python3 tools/generate_op01_bilateral_packet.py --check
EXPECTED_SHA="$(git rev-parse HEAD)" EXPECTED_PHP=82 EXPECTED_CASES=403 bash tools/run_r01_offline_quality_gate.sh
```

Para atualizar o JSON **somente depois de revisar** mudança legítima na proposta documental ou nos CSVs:

```bash
python3 tools/generate_op01_bilateral_packet.py --write
python3 tools/generate_op01_bilateral_packet.py --check
```

A opção `--write` só regrava um arquivo JSON local de revisão; não concede aprovações nem toca no CRM. Mudanças no HEAD/blob pinado do PR #57 precisam de atualização consciente do snapshot e dos verificadores de contrato. O pacote não verifica automaticamente a atualidade remota do PR.

## Testes de regressão

`tests/Contracts/test_op01_bilateral_packet.py` verifica reprodução byte a byte, cobertura completa, ausência de aprovação, tentativa de marcar `GO` no arquivo, tentativas de forjar `APROVADO` ou `PASS` nos CSVs, HEAD de PR divergente, estado `EM_REVISAO` que permanece NO-GO e geração determinística. A suíte faz parte do gate oficial e não chama CRM ou outros serviços.

## Próximo passo real

CORE/SEG/AUT, Privacidade, Arquitetura, Infra, QA e Direção devem revisar e decidir separadamente os itens OP01-R01..R07, anexando evidências reais e auditáveis. Só depois de homologados os requisitos de IAM, autorização **no backend CRM**, replay distribuído, privacidade, rollback e ambiente isolado será possível abrir uma implementação operacional autorizada de OP-01.

**Gates R01-G01..G11 e CRM CORE/SEG/AUT continuam ABERTOS/NO-GO.** Nenhuma alteração na `main`, VPS, produção, segredos, dados de clientes, consumo de IA externa ou custos.

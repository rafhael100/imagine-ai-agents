# F206 — Ensaio executável de saúde e capacidade da fila

**11/11/2026. Somente PHP/Python e dados inventados; CRM real e produção NO-GO.**

O programa `examples/f206-queue-health-demo.php` exercita o contrato F205 `SYNTHETIC_QUEUE_HEALTH_V1` em oito situações: fila vazia, dois trabalhos pendentes, reserva ativa, lease expirado, admissão pausada, retomada com incerteza preservada, ocupação de 60/64 e ocupação de 64/64. As duas amostras de pressão pertencem a filas sintéticas distintas, também isoladas e sem serviços externos.

O resultado `SYNTHETIC_QUEUE_HEALTH_REHEARSAL_V1` contém somente classificação, sinais agregados, contagens de fila e auditoria e flags permanentes `operational_decision=NO_GO`, `real_authorization=false`, `external_calls=0` e `write_calls=0`. Não contém IDs de runs, claims, pedidos, revisores nem tenants.

O verificador `tools/verify_f206_health.py` compara duas execuções PHP e exige contrato fechado, tipos estritos, valores de contagem esperados por estágio, concordância entre a triagem e a capacidade, alertas de pressão, saldo de decisões e manutenção da execução indeterminada após a retomada. A suíte `tests/Contracts/test_f206_health_rehearsal.py` acrescenta 14 testes incluindo adulteração de JSON, contadores booleanos, GO falsificado e campos individuais indevidos.

**Nenhuma homologaçao operacional é inferida.** CRM PR #57 segue OPEN/DRAFT, 13 casos reais OP-01 NAO_EXECUTADO, gates R01-G01..G11/CORE/SEG/AUT NO-GO, sem main, VPS, deploy, clientes, credenciais ou despesas. O baseline proposto após integrar o novo exemplo é **879 testes PHP / lint 110**.

O relatório complementar `tools/render_f206_health.py` apresenta os oito estados em HTML estático com CSP sem scripts, estilos locais, sem rede e escapando todas as células. A função `render()` valida o contrato antes de produzir qualquer HTML; quatro regressões adicionais exigem determinismo, proteção contra injeção e ausência de arquivos escritos. Executar com `python3 tools/render_f206_health.py > relatorio.html` em um checkout local aprovado (a geração padrão escreve somente no stdout).

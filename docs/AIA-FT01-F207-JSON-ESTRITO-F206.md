# F207 — parser JSON fechado do ensaio de saude da fila

**14/11/2026. Somente laboratorio offline. NO-GO permanente.**

O consumidor Python da demonstracao PHP F206 agora aplica `decode_fixture_json` antes da verificacao semantica. O parser recusa chaves duplicadas em qualquer nivel (inclusive chaves que tentam sobrescrever `NO_GO`), valores nao finitos (`NaN`, `Infinity`, `-Infinity`), raiz diferente de objeto, segundo documento/trailing content, bytes invalidos e payload vazio ou superior a 256 KiB. Nao ha normalizacao permissiva nem tentativa de recuperar entradas ambiguas.

A fixture continua gerada por `php examples/f206-queue-health-demo.php`, sem rede, dados reais, seguranca operacional, IAM ou CRM. `verify()` permanece exigindo os oito estados, flags NO_GO e ausencia de identificadores privados.

**Verificacao:** dez casos `F207StrictJsonTests` adicionados ao arquivo `tests/Contracts/test_f206_health_rehearsal.py`, ja obrigatorio no gate offline. A contagem de testes PHP (879) e arquivos PHP em lint (110) nao muda. PR em rascunho, sem merge em main, VPS, deploy, segredos, clientes ou custos. Gates R01-G01..G11, CORE/SEG/AUT e 13 cenarios reais permanecem NO-GO/NAO_EXECUTADO.

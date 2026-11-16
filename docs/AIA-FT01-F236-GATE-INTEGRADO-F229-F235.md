# F236 — consolidação do gate R01 (F229–F235)

**15/11/2026 — DRAFT / NO-GO / NO-MERGE / NO-DEPLOY / NO-COST**

## Por que é necessário

As PRs F232–F235 executavam workflows próprios e obtiveram resultados positivos, mas o gate integral R01 ainda exigia inventário anterior de arquivos. F234 colocava intencionalmente operações de arquivo sob src/, proibidas pelo scanner de isolamento do produto. Isso causaria falsa sensação de qualidade caso as PRs fossem simplesmente empilhadas.

## Correções

- O ensaio F234 baseado em flock passa a existir somente sob tools/labs/, sem exceção ou relaxamento na lista de funções proibidas pelo scanner PHP.
- O worker F234 e os workflows isolados passam a usar o caminho de tools/labs/; o experimento continua em arquivo efêmero local, não em código elegível a operação.
- O workflow primário R01 recebe a nova branch de desenvolvimento; valida a HEAD exata e executa F231, F232, F233, F234 e F235 no mesmo comando sem rede adicional ou instalação.
- O inventário de PHP de aplicação/teste/exemplos passa de 112 a 115 arquivos, mantendo a regressão oficial em 879 casos (não houve alteração dos testes unitários de base).
- Contratos Python existentes que inspecionam o inventário são atualizados; novo teste F236 impede remover a proteção ou tratar arquivos locais como fonte operacional.
- Continua proibida promoção à main e checkout privado não revisado; o segundo checkout CRM original SHA-pinado e seus testes anteriores permanecem inalterados.

## Compatibilidade com CRM

A PR CRM #602 já executa PostgreSQL16 com verificador Ed25519 do Agents fixado na revisão fe79e7e9a55e4d6728ab42c2ef578e40d9bd68ba. Os testes F234/F235 aqui são complementares, NÃO substitutos do banco CRM, e **não** justificam portar diretamente a árvore #602 para o CRM funcional #614. A homologação efetiva deve começar na autoridade de negócio real, não na fixture.

## Gates ainda abertos

Revisão independente SEG/CORE/DBA/IAM/LGPD, fonte operacional segura de chaves, identidade S2S TLS/mTLS, consentimento e ACL servidor, nonce durável compartilhado, reversão, testes de 14 gates, 7 decisões e 13 cenários reais. Nenhum arquivo, segredo, banco ou publicação foi alterado na VPS; main permanece intocada.

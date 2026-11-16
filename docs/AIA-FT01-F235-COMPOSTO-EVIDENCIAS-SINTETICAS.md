# F235 — composição sintética de identidade, escopo e replay

**15/11/2026 — DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**

A classe MockSyntheticEvidenceGate verifica um contrato fictício de ponta a ponta no mesmo processo:

1. Escopo e lista permitida de campos (F232) são comparados com uma fixture injetada, ANTES de admitir a assinatura.
2. Identidade de serviço com Ed25519 assinada, corpo canônico, tenant, operação e validade (F231).
3. Consumo de nonce issuer-wide em ledger compartilhado local (F233), depois de validar as provas anteriores.

Retorno true significa **somente que as evidências sintéticas concordam uma vez**. Não é autenticação operacional, ACL ou autorização de leitura real no CRM. No código não existem roteador HTTP, chamadas externas, credenciais, SQL ou dados pessoais de clientes. A chave privada usada pelos testes é um vetor determinístico PUBLICAMENTE CONHECIDO e jamais pode ser usado para produção.

## Testes

tools/check_f235_composed_gate.php executa prova Ed25519 real em três camadas fictícias, replay com nova instância de identidade, 16 negativas (tenant, usuário, lead, finalidade, campos, claims e chave), saturação e indisponibilidade. O workflow F235 executa também as provas F231–F234; cada teste mantém seu próprio veredito.

## Próximos bloqueios reais

Ainda falta: implementar identidade e autorização apenas no backend CRM, keyring seguro, nonce distribuído durável/atômico, rotação, TTL, revisão de LGPD, staging, testes de integração com a versão real do CRM, 14 gates / 7 decisões / 13 cenários efetivos do CRM #57. F235 não habilita nenhuma destas etapas automaticamente. Main e VPS permanecem intactas.

# F240 — ciclo HTTP sintético F238 → F239 com gate R01 completo

**15/11/2026 — CANDIDATO DE REVISÃO / NO-GO / NO-MERGE / NO-DEPLOY**

## Entrega

A F240 unifica as classes de laboratório F238 (preparação de envelope) e F239 (reconhecimento estrito de negativa HTTP 404) em `tools/check_f240_http_cycle.php`. O ensaio constrói uma requisição `crm.lead.read_minimal` de dados sintéticos, gera uma assinatura Ed25519 determinística de teste, confere identidade, escopo e replay via F235 e **apenas monta** cabeçalhos e corpo. A resposta CRM é simulada exclusivamente com o contrato público original `MockCrmPublicResponseContract::denial()`; o parser rejeita 20 respostas adversariais.

Nenhuma requisição sai do processo: não há URL de destino, controlador HTTP, instalação de pacotes, chaves reais, banco, IAM, perfil humano ou leads de clientes. A assinatura do teste **nunca** é credencial de serviço. `NO_GO` permanece explícito inclusive quando o teste é aprovado.

A classe F239 representa apenas o *formato* da negativa; não fornece autenticidade de servidor nem TLS. O CRM continua como único responsável pela política de autorização real. Antes de eventual homologação operacional: reconciliar CRM #602 com a aplicação funcional #614, validar identidade protegida, escopo de negócio e o registro atômico de nonces do lado CRM, revogação, RLS, auditoria e execução de 14 gates/7 decisões/13 cenários da PR #57.

## Gate consolidado

O workflow R01 será executado sobre HEAD exata do candidato F240 e exige:
- 117 arquivos PHP com lint (115 anteriores + classes F238 e F239).
- Manter **879 testes PHP oficiais** (sem adicionar testes Unit fora do gate).
- Rodar F231–F235, F238, F239 e F240, além de todo o histórico Python/DOM e teste de fonte CRM SHA-fixada.
- Scanner de efeitos de código `src/` intocado, bloqueios de `main`, credenciais e checkout privado preservados.
- Testes Python F240 verificando o gate sem permissões operacionais.

A documentação detalhada do candidato anterior continua em F236/F237. Uma CI verde aqui não autoriza merge nem deploy.

A revisão do CRM #614 identificou o adaptador F248 existente e seu perfil HTTP de negativa distinta da F246; F239 agora aceita estritamente ambos os perfis, sem aceitar outros cabeçalhos. A F240 prova os dois perfis e 20 variantes negativas.

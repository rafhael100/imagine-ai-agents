# F212 — Plano executável de desbloqueio OP-01

**14/11/2026. Estado técnico: NO-GO.**

Ferramenta nova: `tools/plan_op01_release.py`. Ela recompila e verifica o pacote bilateral existente (CRM #57 OPEN/DRAFT), confere os 14 gates, 13 casos reais **NAO_EXECUTADO** e 7 revisões independentes. Depois transforma as pendências em cinco frentes de trabalho com dependências verificáveis, donos sugeridos e evidências exigidas.

| Frente | Responsabilidade | Critério de conclusão (revisão humana) |
| --- | --- | --- |
| W01 Identidade e replay | CRM AUT + SEG | autenticação de serviço/usuário, emissor e audience, nonce distribuído e revogação |
| W02 Permissão de recurso/empresa | CRM CORE + SEG | ACL/RLS efetiva por tenant/lead/campo, negação de acesso cruzado |
| W03 Contrato e privacidade | Arquitetura + Privacidade + Jurídico | versionamento do contrato, minimização, finalidade, retenção e rollback |
| W04 Infra e cenários reais | Infra + SEG + QA | staging segregado, controle de segredos, falha fechada e 13 cenários OP-01 independentes |
| W05 Piloto supervisionado | Direção + Produto + Financeiro | fluxo de revisão/efeitos, custos, escopo e aprovação expressa **após** W01–W04 |

Comandos locais (sem rede ou dependências externas):

```bash
python3 tools/plan_op01_release.py --check
python3 tools/plan_op01_release.py --format markdown
python3 tools/plan_op01_release.py --format json
```

O script falha quando alguém tenta marcar um gate como aprovado, um cenário como executado, altera dados sem atualizar o pacote ou muda o estado de revisão; **nunca** emite GO, credenciais ou comandos de deploy. Os testes F212 são obrigatórios no quality gate. Este planejamento não fornece autorização operacional: CRM PR #57 OPEN/DRAFT, R01-G01..G11/CORE/SEG/AUT/LGPD NO-GO. Nem `main`, VPS, clientes ou produção são tocados.

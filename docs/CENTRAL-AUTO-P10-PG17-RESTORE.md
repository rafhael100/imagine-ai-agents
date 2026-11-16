# CENTRAL-AUTO-P10 — Backup e restauração do banco n8n PostgreSQL 17 em CI

Continua [RC07 #413](https://github.com/rafhael100/imagine-ai-agents/pull/413) e as etapas HOM-05 registradas em [issue #414](https://github.com/rafhael100/imagine-ai-agents/issues/414). **Não é implantação operacional na Leaseweb.**

## Escopo deste ensaio
O pipeline reutiliza a configuração isolada n8n oficial com PostgreSQL17, role `n8n_runtime` sem superusuário e segredos em arquivos. Após importar, exportar e executar o workflow nativo NO_AI (120 visitas, 12 leads, 2 clientes), o teste P9 para **somente o serviço n8n**. O banco permanece acessível.

Em seguida o P10:
1. Recusa execução fora do GitHub Actions do repositório esperado e fora do projeto descartável `imagine-n8n-p4-ci`. Recusa caso o contêiner principal n8n ainda esteja ativo, caso PG não seja 17 ou não haja exatamente um workflow sintético.
2. Executa `pg_dump -Fc --no-owner --no-acl` para arquivo privado do runner, cria uma **segunda base** `n8n_restore_ci` na mesma instância PG17 descartável, e executa `pg_restore --exit-on-error` com papel `n8n_runtime`.
3. Confere contagem de workflows e hash calculado de nome, nós, conexões e estado ativo antes/depois, assim como ausência de superuser/BYPASSRLS na conta runtime.
4. Reabre a base restaurada em um contêiner CLI efêmero n8n, **reutilizando apenas no laboratório a mesma chave e volume da aplicação original**. Exporta e verifica os três nós nativos e as expressões revisadas. Executa novamente o funil e verifica o mesmo resultado 120→12→2, com sinal de zero consumo de IA e crédito.
5. Remove apenas os contêineres, volumes e arquivos descartáveis do projeto CI. **Nenhum backup é enviado como artefato nem usado em outra máquina.**

## Limites obrigatórios
- O procedimento demonstra **recuperabilidade lógica do banco** com a mesma chave de criptografia e o mesmo volume do n8n. **Não demonstra recuperação física do volume `.n8n`, restauração de uma chave perdida ou disponibilidade em host distinto.** Ainda faltam guarda e teste externo de backup cifrado e de segredos de homologação.
- O dataset contém apenas workflow fictício sem credenciais. O ensaio não comprova recuperação ou rotação de credenciais cifradas, nem tolerância a uma falha real de disco.
- `pg_restore` é feito em uma **segunda base temporária**, não sobrescreve a original; não execute este script na VPS ou bancos com dados reais. O Compose encerra volume no runner usando `down --volumes`, procedimento **expressamente proibido para a VPS**.
- Nenhum acesso real de Portal/CRM, IAM, edição de workflows por clientes, plugin pago, chamada de IA, cobrança, merge, deploy ou publicação é autorizado.

**DRAFT / NO-GO / NO-DEPLOY / NO-SPEND.**

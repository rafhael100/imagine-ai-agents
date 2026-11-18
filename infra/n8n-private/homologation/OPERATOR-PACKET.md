# Pacote público da candidata privada n8n — 18/11/2026

**Candidata DRAFT.** Conferir a evidência desta HEAD e seu artefato no workflow
`n8n-private-candidate.yml`, da branch
`feat/n8n-private-candidate-bundle-2026-11-18`. As CIs abaixo pertencem às
SHAs originais e não aprovam antecipadamente esta consolidação. O pacote é
um contexto completo de fontes para build e revisão. Não é instalador, Compose
operacional completo, backup ou autorização de acesso.

## Lacuna concreta e composição

A Central #475 foi validada numa árvore temporária combinada com o domínio
#468. Seu Dockerfile copia
`src/MockOrchestration/CapturedLeadTaskContract.php`, arquivo ausente no
checkout isolado da #475. Esta candidata reúne fisicamente essas fontes e a
P30, permitindo construir a imagem a partir de um checkout único. Mantém os
blobs originais de runtime, domínio e driver MFA; a seção histórica de status
da #475 foi preservada na resolução do conflito documental.

| Fonte | Commit fixo | Evidência anterior |
| --- | --- | --- |
| [Central #475](https://github.com/rafhael100/imagine-ai-agents/pull/475) | `dfa4685cd5df4a9499c3420f580980e769a1981d` | [CI 44225455499 — success](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44225455499) |
| [Domínio #468](https://github.com/rafhael100/imagine-ai-agents/pull/468) | `05e4ea7942e530055f4e455544d82f5d3edbd56d` | [CI 44208519874 — success na composição com Central #469](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44208519874) |
| [MFA P30 #464](https://github.com/rafhael100/imagine-ai-agents/pull/464) | `c08688b16e61d8d1ecc4d8c0ed4da8758a687ee8` | [CI 44203383716 — success](https://github.com/rafhael100/imagine-ai-agents/actions/runs/44203383716) |
| [CRM #654](https://github.com/rafhael100/imagine-crm/pull/654) | `e1b3b7f118eff0350a00789aecc7bb2bb2331464` | [Tela](https://github.com/rafhael100/imagine-crm/actions/runs/44224140953) e [backup/snapshot](https://github.com/rafhael100/imagine-crm/actions/runs/44224140836) aprovados nessa SHA |
| [Adapter histórico](https://github.com/rafhael100/imagine-ai-agents/commit/8b04d0e936168c77db028160b5aeb1758db497c4) | `8b04d0e936168c77db028160b5aeb1758db497c4` | Dependência pública pinada do replay existente; não é fonte de autoridade atual |

O commit e a árvore **reais desta candidata** são obtidos do checkout da CI
e registrados no manifesto, sem reutilizar a identidade de uma árvore virtual
anterior. A consolidação possui sua própria história Git; o CRM e o adapter
continuam dependências públicas separadas, com seus commits e árvores próprios.

## Verificação e conteúdo público

O workflow `.github/workflows/n8n-private-candidate.yml` e o runner
`tools/run_n8n_private_candidate.sh` destinam-se exclusivamente ao runner
descartável autorizado. Verificam a preservação das fontes e exigem os checks
da #475 no checkout consolidado e o driver P30 original antes do pacote.
O build usa o arquivo de fontes da HEAD completa e o CRM pinado; o adapter
histórico é acrescentado somente ao ambiente dos testes depois do build.

A criação é restrita à CI hospedada e recebe observações produzidas pelo
runner, sem aceitar uma aprovação operacional no manifesto:

```text
operator_packet.py create --candidate-repo PATH --crm-repo PATH --observations JSON --output-dir NOVO
operator_packet.py verify DIRETORIO --expected-candidate-sha SHA40
```

O diretório de saída deve ser novo. O runner usa
`$RUNNER_TEMP/n8n-private-candidate-packet` e verifica o pacote antes do upload.
O artefato `n8n-private-candidate-${{ github.sha }}-${{ github.run_attempt }}` contém
somente `candidate.tar.gz`, `crm.tar.gz`, `adapter.tar.gz`, `manifest.json` e
`SHA256SUMS`, com retenção de sete dias no GitHub. As fontes podem ser
regeneradas dos mesmos objetos Git públicos após esse prazo. Essa retenção
de artefato de revisão não constitui backup nem altera sua política operacional.

Para verificar uma cópia baixada, usar o script de um checkout confiável na
SHA da candidata, sem extrair ou executar código do download ainda não verificado.
`PACKET_DIR` aponta para os cinco arquivos, sem extrair os TARs; definir
`EXPECTED_CANDIDATE_SHA` a partir do commit confirmado na PR/CI no GitHub,
nunca a partir do próprio manifesto recebido:

```bash
python3 infra/n8n-private/homologation/operator_packet.py verify \
  "$PACKET_DIR" --expected-candidate-sha "$EXPECTED_CANDIDATE_SHA"
```

O verificador é offline, sem Docker, SQL ou rede. Confere hashes, bytes, tipos e
caminhos das entradas e suas árvores Git contra os commits públicos incluídos.
O `create` confere também a ancestralidade das três fontes no checkout da CI;
a verificação offline não reivindica revalidar o histórico Git completo.

O artefato contém arquivos de fontes públicas reproduzíveis e um manifesto
SHA-256 com commits/árvores, vínculo ao run e imagens observadas. Não inclui
estado dos bancos, dumps, volumes ou material privado de execução — chaves,
certificados, senhas, cookies e sessões — nem relatórios brutos do ambiente.
Não inclui imagens Docker.

A reprodutibilidade refere-se aos bytes dos arquivos de fontes para os mesmos
objetos Git. **Os builds de contêiner não são reprodutíveis bit a bit:** os
Dockerfiles originais preservam bases como `php:8.4-fpm-bookworm` e instalações
de pacotes mutáveis. O manifesto distingue referências de registry por digest
de n8n/PG17/PG16/nginx dos IDs locais observados nos builds PHP. Um ID local
não é digest de imagem distribuída nem publicação em registry. Hashes não
constituem assinatura do operador, análise de vulnerabilidades ou aprovação.

Manifesto e `SHA256SUMS` podem ser substituídos juntos. A conferência contra a
HEAD obtida por fonte confiável valida a identidade completa das fontes Git;
não autentica os status de CI ou imagens de registry declarados no relatório
nem substitui a aprovação do operador.

## O que a CI desta candidata deve distinguir

- A cadeia de eventos e retornos usa os harnesses existentes: captura mTLS,
  tarefa autorizada, fila P6, execução nativa, ledger P26, ACK e histórico CRM.
  `PREPARED` representa preparo técnico; não altera comercialmente o lead.
- As recuperações existentes comparam estado e revogações em recursos
  descartáveis. O pacote público de fontes não contém esses backups nem
  transforma restore dentro do runner em recuperação externa operacional.
- P30 usa **outro Stack descartável**, com banco/conta novos. A prova de MFA
  não é executada sobre o n8n restaurado e não prova recuperação de identidade
  humana. O driver permanece separado dos ensaios de workflow/restore.
- MFA individual e revogação de sessões passaram na P30 histórica. Na imagem
  sem entitlement, o enforcement global efetivo e persistido foi `false`,
  mesmo após resposta HTTP 200 ao pedido de ativação. O controle obrigatório
  para todos os operadores permanece pendente; ver [MFA-P30](MFA-P30.md).

Os resultados da nova HEAD devem ser registrados a partir do run terminal.
Não somar ensaios sobrepostos nem atribuir todos os workflows executados pelas
regressões à contagem de uma única prova de retorno.

## Handoff operacional: evidência atual e próxima ação

| Tema | Evidência disponível e próxima ação |
| --- | --- |
| Host | [Inventário real por console em 16/11](https://github.com/rafhael100/imagine-ai-agents/issues/414#issuecomment-6925460120): oito CPUs, oito Compose e 18 contêineres naquele instante. É histórico; a sessão desta rodada encontrou o portal Leaseweb exigindo login. Retomar por autenticação legítima e atualizar a coleta somente leitura. |
| Coexistência | Preservar todos os projetos e listeners, inclusive CRM 18081/18086 e Sites 18090. A porta 17990 foi observada livre em 16/11, **não reservada**. Confirmar recursos, portas, redes, volumes e egress atuais antes de propor instalação. |
| Acesso e autoridade | Definir contas nominais, ingresso privado com MFA obrigatório, revogação e ausência de bypass; conferir grants por empresa/operação, origem de autoridade e custódia de PKI/chaves reais. Nenhuma fixture fornece essas concessões. |
| Licença | Manter integrações técnicas fixas da Imagine e regras comerciais no CRM. Rever o recorte real em [LICENSE-P20](LICENSE-P20.md); nenhum entitlement ou gasto novo é aprovado por este pacote. |
| Backup externo | Há registro de piloto histórico Restic/R2 e da baseline INF-DEV-03; não declarar R2 inexistente. Identificar o destino/prefixo já aprovado, custódia, quota e recibo externo próprios deste pacote. Restore operacional desde essa cópia ainda precisa ser demonstrado. |
| Instalação | Consolidar fontes/imagens/configuração, migrations próprias, janela, monitoramento, rollback e aceite gerencial do risco residual. Não executar bootstrap/cleanup de CI na VPS. Plano exato e autorização de instalação permanecem pendentes. |

Reutilizar [PLAN-P20](PLAN-P20.md), [RECOVERY-P20](RECOVERY-P20.md),
[RECOVERY-P28](RECOVERY-P28.md), `material_preflight.py`,
`../readonly_inventory.py` e `../assess_readonly_host.py`. São instrumentos
candidatos, não instaladores. O inventário não lê env/segredos; salvar sua
saída somente no canal privado aprovado. A avaliação continua NO-GO e não
autentica o host. O preflight de materiais só deve ler material autorizado em
ambiente protegido e não substitui custódia ou revogação efetiva. Não copiar
checklists OP-01 antigos como se fossem o estado atual desta integração.

A [baseline INF-DEV-03 preservada na candidata Portal #927](https://github.com/rafhael100/imagine-portal/blob/86c9f74e890d5d2ae254c63b65dee36af3a58e7f/apps/portal/deploy/phase6/RELEASE-GATES.md)
é Restic/R2 Standard UE, backup diário com retenção inicial de 30 dias,
verificação semanal, restauração mensal e simulação trimestral de desastre.
Não substituí-la por 7 cópias diárias/4 semanais. O piloto histórico não
comprova cópia externa deste pacote, custódia atual, RPO/RTO medidos ou autorização para
agendamento/prune. Os procedimentos P20 devem ser lidos com essa decisão
posterior e com o escopo atual de recuperação.

## Revisão, decisão e escopo preservado

Conduzir revisão interna cruzada com apoio de IA e decisão final da Direção,
conforme [Portal #58](https://github.com/rafhael100/imagine-portal/issues/58)
e [registro da Direção no CRM](https://github.com/rafhael100/imagine-crm/issues/27#issuecomment-6731873469).
Não criar requisito de contratar consultoria externa, nem apresentar revisão
por agentes como auditoria externa. Registrar achados, evidências e riscos
aceitos; a aprovação para continuar o desenvolvimento não fornece evidência
de host, backup ou autoridade ainda não verificada. Gates executáveis de
outros produtos não são modificados por este handoff.

As linhas [P67 #476](https://github.com/rafhael100/imagine-ai-agents/pull/476),
[P68 #477](https://github.com/rafhael100/imagine-ai-agents/pull/477) e
[CRM #658](https://github.com/rafhael100/imagine-crm/pull/658) tratam dos
rascunhos do owner e sua recuperação em paralelo. Não fazem parte desta
consolidação; este pacote não duplica nem declara concluído esse trabalho.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-GO operacional.** Fontes públicas e
evidência de CI não concedem acesso a clientes, instalação, operação de
serviços, contratação, IA paga ou alteração dos gates da [issue 414](https://github.com/rafhael100/imagine-ai-agents/issues/414).

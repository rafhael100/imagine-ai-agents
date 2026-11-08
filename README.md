# Imagine AI Agents

**Status:** projeto criado no GitHub; fundação documental inicial (05/11/2026).  
**Repositório:** `rafhael100/imagine-ai-agents` (privado).  
**Execução:** sem código de produto, sem infraestrutura provisionada e sem deploy.

## Propósito

Construir a camada compartilhada de **configuração, execução supervisionada e governança de agentes de IA** do ecossistema Imagine.

**Produtos consumidores previstos:** Imagine CRM, Portal Imagine, Imagine Performance AI e Universidade Imagine.

## Princípios

1. **Núcleo comum, domínios independentes:** cada produto mantém suas APIs, identidades, dados e regras de negócio. Não há acesso direto e irrestrito aos bancos dos produtos.
2. **Isolamento multiempresa:** contexto, memória, documentos, ferramentas e custos segregados por empresa e produto, com autorização obrigatória no backend.
3. **A IA propõe, o sistema valida:** regras críticas (acesso, finanças, exclusão, publicações, cobranças) não dependem somente de prompts.
4. **Autonomia graduada:** informar, sugerir, solicitar aprovação e executar operações explicitamente permitidas conforme risco.
5. **Observabilidade:** versões, execuções, erros, custos, testes, avaliações e trilhas de auditoria.
6. **Melhoria controlada:** feedback e testes antes de promover versões.
7. **Integração por contratos técnicos:** APIs autenticadas, eventos, filas e ferramentas versionadas.
8. **Não duplicação:** o CRM mantém o motor nativo de automações; n8n pode atender fluxos internos controlados pela Imagine, mediante validação do licenciamento comercial.

## Documentos

- [AGENTS.md](AGENTS.md) — diretrizes para Codex e agentes de desenvolvimento.
- [STATUS-ATUAL.md](STATUS-ATUAL.md) — fatos, pendências e bloqueios.
- [docs/ARQUITETURA-INICIAL.md](docs/ARQUITETURA-INICIAL.md) — limites e componentes.
- [docs/ESCOPO-FUNCIONAL.md](docs/ESCOPO-FUNCIONAL.md) — 12 módulos candidatos.
- [docs/ROADMAP.md](docs/ROADMAP.md) — etapas e critérios para avançar.

## Próximas decisões

Revisar/aprovar limites do serviço, contratos entre produtos, proteção de dados, estratégias de modelos/provedores, custos, licenciamento, MVP e critérios de aceite.

**Regra:** a publicação documental neste repositório não autoriza programação, alterações nos demais repositórios, despesas, infraestrutura ou deploy.

## FT01-F1-01 — domínio mockado (2026-11-06)

Primeiro lote autorizado: oito modelos em `src/Domain`, contratos locais em
`src/Contracts`, três mocks determinísticos em `src/Mocks` e dez casos sintéticos
em `tests/Unit/run.php`. Nenhum contrato é homologado com CRM. Todos os dados de
exemplo são marcados `SYNTHETIC`; o prefixo é convenção de teste, não sanitização
nem prova de identidade. Não fornecer dados reais.

Requisito de sintaxe: PHP 8.1+ já disponível (não há Composer nem bibliotecas).
No diretório do repositório, executar localmente:

```sh
php tests/Unit/run.php
```

O runner usa autoload local e verificações explícitas, com código de saída 1 em
falha. Não usa rede, banco ou serviços. **Neste ambiente PHP não está instalado:
nenhum teste PHP ou lint PHP foi executado; compatibilidade ainda não verificada.**
Nenhum runtime ou dependência foi instalado para contornar a restrição.

O contexto contém produto, tenant, principal, finalidade, recurso e `mock-v1`.
Aprovação usa relógio inteiro fornecido pelo chamador e vincula intenção material
imutável. Deduplicação é volátil, por produto/tenant/chave, com igualdade exata de
material e contexto; não oferece atomicidade ou recuperação. O estado global de
run não confirma efeito. `CONFIRMADO` exige prova **sintética** do fake, sem valor
para o CRM real. Sugestões ignoram instruções contidas no texto de entrada e não
dispõem de capacidade de escrita. Uso técnico não implementa crédito/faturamento.

Vocabulário mínimo de run: `RECEBIDA → EXECUTANDO ↔ AGUARDANDO`, com saídas
terminais `CONCLUIDA`, `FALHOU`, `CANCELADA`. É um recorte local dos documentos,
não a máquina definitiva de R02/R03. Não há compensação, fila, persistência,
callback, expiração de run ou reconciliação oficial. Status indeterminado permanece
indeterminado; nenhum retry produz novo efeito nesse fake.

Pendências: executar lint e os dez casos em ambiente autorizado com PHP já
existente; revisar os contratos simulados e homologar contratos reais separadamente.
R01-G01 a G11 e CORE/SEG/AUT CRM permanecem abertos. F1-02, merge e deploy exigem
nova autorização.

## FT01-F1-02 — coordenação supervisionada local (2026-11-08)

`SupervisedCoordinator` recebe contexto, gateway fake e provedor fake por injeção
manual. Permite iniciar run, criar etapas, sugerir, executar intenção com aprovação
externamente fornecida, consultar efeito e transitar a run explicitamente. Não
emite aprovação: usa o objeto F1-01, inclusive revogação e janela `[issuedAt,
expiresAt)` com relógio passado como parâmetro. Não há relógio global ou agendamento.

`execute` reserva a intenção antes do único `submit`. Repetição da mesma intenção
consulta `status`; divergência material falha com `IDEMPOTENCY_CONFLICT`. Falha de
envio vira `INDETERMINADO`, nunca retry automático. `afterTimeout` exige tentativa
TIMEOUT vinculada à intenção e faz somente consulta. `reconcile` também funciona
para run terminal, preservando a distinção entre estado da run e efeito. Novas
operações exigem run ativa. Confirmado/negado não podem regredir; ausência de
registro após dispatch é tratada como indeterminação. Erro na consulta propaga a
falha sem liberar novo envio ou apagar a última evidência.

`MockSupervisedGateway` compõe o fake F1-01 sem modificar contratos. Expõe contadores
para observar submissões/consultas e permite simular perda da resposta depois do
efeito. `recordSyntheticEvidence` é controle explícito de fixture, não API real;
atualiza resultado pendente/indeterminado para confirmação, negativa ou continuação
da espera. Prova CONFIRMADO continua exclusivamente `SYNTHETIC_MOCK_CRM`.

Rastros são arrays em memória com sequência, referências de produto/tenant/run/
step/intent e códigos fechados. Referências recebem hash determinístico para evitar
copiar conteúdo arbitrário mesmo com prefixo SYNTHETIC. Não armazenam material,
prompt, sugestão, erro bruto ou stack. Hash não é autenticação nem anonimização de
produção. Cada coordenador serve um contexto sintético; não é ACL CRM.

Os dez casos originais são preservados; `tests/Unit/supervised.php` acrescenta os
12 casos U02-01–U02-12 da ordem executiva, carregados pelo mesmo runner. Em ambiente
autorizado com PHP 8.1+ **previamente disponível**, executar:

```sh
find src tests/Unit -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/Unit/run.php
```

Nesta execução Codex, PHP está ausente: **lint e os 22 testes NÃO EXECUTADOS**.
Nenhuma instalação ou GitHub Actions utilizada. A validação independente anterior
F1-01 registrada no STATUS não comprova o código novo F1-02.

Limitações: estado volátil por instância, nenhuma atomicidade, concorrência,
recuperação durável ou prova de serviço real. Run concluída não indica efeito
confirmado; cancelamento não apaga efeitos existentes. Novos objetos/coordenadores
não representam recuperação de processo. Sem merge, F1-03 ou deploy. R01-G01 a G11
e CORE/SEG/AUT CRM continuam abertos.

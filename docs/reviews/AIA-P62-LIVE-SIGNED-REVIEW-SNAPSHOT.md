# AIA-P62 — fila e pareceres assinados no mesmo snapshot

Continuação funcional da RC65 Agents, base
3fb86c5fb011bc9df1f12a4a30a7c094370b6872, em worktree e branch próprios.
O recorte permanece sintético e somente leitura. Não altera o receptor
Central, o contrato CRM, a autorização de execução ou a licença n8n.

## Lacuna fechada

A tela P56 já consultava propostas persistidas em PostgreSQL, mas apresentava
sempre o rótulo “aguardando revisão”. O modelo P60 conhecia os quatro estados
de pareceres assinados, porém recebia estruturas previamente preparadas e
não tinha um adaptador de leitura conjunta do banco.

Com AIA_P62_SIGNED_REVIEW_LAB=true, a mesma tela passa a exibir:

- Aguardando pareceres.
- Um parecer assinado.
- Duas observações assinadas.
- Parecer de recusa registrado.

Filtros, layout, identidade visual e restrição localhost/CI da P56 são
preservados. Não há formulários, botões de aprovação, scripts externos ou
ações operacionais. Sem a flag P62, o comportamento anterior continua
disponível.

## Uma leitura coerente

PgSignedReviewQueueBridgeLab::read() possui uma única transação PostgreSQL
REPEATABLE READ, READ ONLY. Ela reutiliza:

1. P55 para validar escopo, lotes, contagem e fingerprints da fila.
2. P61 para verificar as assinaturas históricas e a proveniência do autor.
3. P57/P59 para revalidar os pareceres com a chave e a revisão observadas
   nesse snapshot, exigindo separação entre autor e revisor.
4. O modelo P60 original para validar e apresentar o conjunto completo de
   propostas e estados, com esquema, contadores e flags fechados.

P55, P57 e P61 ganharam métodos explícitos de composição em snapshot já
aberto. A guarda compartilhada exige a transação de leitura com o isolamento
correto. Esses métodos não confirmam nem encerram a transação do chamador.
As APIs anteriores continuam criando suas próprias transações e recusando
aninhamento.

O relógio vem do PostgreSQL. O contrato preserva o epoch/UTC técnico;
a tela mostra “Consulta:”, data brasileira e o fuso America/Sao_Paulo,
com seu deslocamento UTC explícito. Uma mudança posterior aparece na
leitura seguinte. A visualização não é uma
decisão contínua de IAM nem concede autoridade durante sua exibição.
Os limites temporais originais da P57 continuam intactos, incluindo a idade
máxima de 60 segundos de issued_at, além de expires_at.

## Histórico não substitui validade atual

P61 verifica assinaturas dos registros presentes mesmo depois de revogação
ou expiração. Essa prova histórica nunca é utilizada como entrada de
“parecer atual válido” para o P60.

Se algum parecer não puder ser revalidado por tempo, atividade ou revisão da
chave, a saída possui current_review_available=false e signed_review=null
para a fila inteira. A tela conserva um resumo histórico separado e
apresenta **“parecer atual indisponível”** em todas as propostas visíveis.
Não converte expiração ou revogação em “aguardando”, não apresenta fila
parcial de pareceres e não fabrica aprovação.

Corrupção de assinatura, autoria ausente ou inconsistente, fila incompleta,
schema indisponível ou erro de acesso negam a consulta inteira. A tela
responde HTTP 503 com o erro genérico P56, sem retornar propostas.

O histórico verificado não comprova imutabilidade externa nem recupera um
registro apagado por um administrador do banco. A evidência cobre os
registros presentes e seus vínculos; custódia, retenção e trilha externa
permanecem aceites separados.

## Escopo de empresa e perfil de leitura

O laboratório conserva os dois papéis de leitura P55, vinculados a empresas
sintéticas distintas. O perfil P62 usa login sem herança, sem membership,
sem ownership, SUPERUSER ou BYPASSRLS. O runtime recebe apenas SELECT nas
cinco tabelas de lotes, propostas, chaves de revisores, observações e autores.
Todas usam FORCE ROW LEVEL SECURITY; cada consulta também filtra o tenant.
O horário, a empresa e o papel de conexão não são aceitos pelo HTTP.

Uma policy RLS ausente pode esconder linhas sem causar erro SQL. Por isso,
“zero linhas” só é interpretado depois de conferir o perfil esperado:
privilégios, RLS, policy aplicável, papel e expressão exata por tenant.
Uma policy ausente, adicional, restritiva ou com outro literal é negada.
Não se removem espaços ou parênteses de literais SQL para fazer essa
comparação.

O leitor adquire ACCESS SHARE nas cinco tabelas antes do primeiro SELECT e
mantém esses locks até concluir. Assim uma mudança de policy já em andamento
termina antes da escolha do snapshot e outra mudança não passa entre a
verificação do catálogo e o uso da policy. Os timeouts são de 750 ms para
locks e 2 s para statements.

Este é um perfil fechado de PostgreSQL 16 descartável. A forma exata de
pg_get_expr e os nomes dos papéis/policies pertencem à fixture P62, não são
uma validação genérica de políticas operacionais ou uma migration produtiva.

## Ensaio integrado

Na árvore combinada Agents + Central, executar:

    php tools/check_p60_signed_review_queue.php
    python3 tools/check_p62_live_signed_review.py

O segundo comando reutiliza AgentsRecovery da P25 e os mesmos
AGENTS_PG_IMAGE/AGENTS_PHP_IMAGE imutáveis do runner combinado. Exige GitHub
Actions hospedado, o repositório esperado, Docker local e o arquivo histórico
_auto_ia_pinned_source já exportado pelo runner.

O driver cria um PG16 novo sem rede e uma instância PHP no mesmo namespace.
O servidor escuta somente loopback, não recebe senha administrativa e roda
como www-data. O cliente HTTP não recebe credenciais de banco. Não há porta
host, nginx, n8n ou conexão externa neste ensaio. Senhas e chaves de assinatura
são sintéticas; materiais entram por arquivos individualmente montados ou
stdin capturado, sem publicação no relatório.

O probe prepara quatro estados da empresa A e uma proposta separada de B.
Verifica as APIs anteriores, os novos limites de transação, SELECT-only,
RLS das cinco tabelas, ausência de efeitos e ocultação de identidades,
chaves e corpos assinados na apresentação.

Os testes funcionais incluem:

- A tela com os quatro estados e os filtros existentes, com headers de
  segurança, sem ações e sem recursos remotos.
- Revogação, expiração e preservação distinta do histórico; assinatura
  corrompida, autor ausente e privilégio retirado com recusa uniforme.
- Policies removidas, restritivas, adicionais e com espaço/parênteses dentro
  do literal do tenant; nenhuma pode esconder votos e produzir “aguardando”.
- Alterações reais por uma segunda conexão após ler a fila e antes de ler
  os pareceres: chave, autoria e assinatura. Uma instrumentação PDO restrita
  ao probe confirma o snapshot antigo completo e a mudança na próxima
  consulta, sem introduzir callbacks no produto.
- Espera real por ALTER POLICY concorrente, observada por
  pg_blocking_pids. O probe exige que LOCK TABLE tenha retornado com
  sucesso antes de aceitar a negativa posterior; uma recusa por timeout
  não conta como revalidação da policy.
- Hashes e contagens das cinco tabelas antes/depois das leituras, além de
  controle positivo após cada família negativa.

As reescritas de validade e restaurações de fixtures pertencem apenas ao
administrador do banco descartável do teste. O leitor não renova parecer,
reativa chave ou escreve qualquer linha.

## Estado da evidência e limites

Sintaxe Python e revisão estática podem ser verificadas no checkout local.
PHP/PostgreSQL/HTTP reais precisam passar no check da HEAD concreta na CI;
a existência dos arquivos não equivale a execução aprovada.

A entrega acrescenta comportamento funcional à tela de laboratório e
preserva can_approve=false, can_execute=false, operational_authorization=false,
zero chamadas de IA e zero efeitos externos. Não autentica pessoas reais,
não integra o IAM do Portal/CRM, não envia propostas ao CRM e não abre o executor.

**DRAFT / NO-GO operacional.** O acesso de usuários reais, a custódia das
chaves, a retenção e a implantação continuam sujeitos aos aceites próprios.

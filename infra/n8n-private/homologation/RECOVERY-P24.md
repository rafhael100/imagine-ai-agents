# CENTRAL-AUTO-P24 — recuperação completa do n8n desde pacote assinado

## Problema e avanço

P21–P23 comprovam criptografia, procedência sintética, validação do TAR e
restauração de dumps PostgreSQL com tabelas mínimas. P12 já recuperava um n8n
real e um workflow nativo, mas não passava pelo pacote CMS/assinatura P23 nem
comprovava a recuperação de uma credencial cifrada pela aplicação.

P24 une esses caminhos em **um ensaio novo de aplicação**: um n8n descartável
cria seu schema real em PostgreSQL 17, recebe o workflow nativo já revisado e
uma única credencial `httpBasicAuth` fictícia. O backup contém banco, volume
`.n8n` e chave da instância; o destino tem outro projeto Compose, bancos e
volumes novos. A chave restaurada precisa permitir a exportação e decifra da
credencial, e o workflow deve produzir o mesmo resultado antes e depois.

**Estado: candidato de laboratório; NO-GO operacional.** Só considerar o
ensaio aprovado quando a CI da HEAD concreta concluir com sucesso.

## Correções de segurança incluídas

A revisão P24 também fecha falhas encontradas nos utilitários reaproveitados:
flags do recibo exigem o booleano `false` exato; o signer exige chave pública
Ed25519 em SPKI estrito e correspondência com a chave privada; falhas de escrita
não podem remover arquivos preexistentes ou substituídos por outro processo.
A estrutura DER do SPKI Ed25519 segue a
[RFC 8410, seção 10.1](https://www.rfc-editor.org/rfc/rfc8410.html#section-10.1).
O pacote decriptado exige membros regulares privados, cabeçalho PGDMP,
comprimento válido da chave, manifesto fechado sem chaves repetidas e tamanhos
inteiros exatos. As regressões `test_p24_receipt_safety.py` cobrem esses achados.

## Arquivos e reutilização

- `check_p24_full_n8n_restore.py`: orquestra apenas recursos descartáveis do job;
  reutiliza `docker-compose.yml`, `check_compose_static.py`,
  `check_native_noai_flow.py`, `seal_backup.py`, `signed_receipt_p23.py` e
  geração de certificado/decriptação do P21.
- `test_p24_restore_archive.py`: regressões de materialização privada,
  destino preexistente, TAR hostil com manifesto correto e normalização de
  ownership/permissões sem alterar o conteúdo dos arquivos.
- `.github/workflows/central-auto-p24-full-n8n-restore.yml`: CI apenas na branch
  candidata, repositório público, GitHub token com `contents: read` e sem
  persistência de credencial no checkout.
- `tools/run_rc66_p24_virtual_tree.sh`: combina os fontes da HEAD P24 com
  Agents RC65 `3fb86c5fb011bc9df1f12a4a30a7c094370b6872` somente em árvore
  temporária. As regressões de segurança, testes Agents e o ensaio de aplicação
  rodam nessa mesma árvore; não há merge commit ou alteração de refs remotas.

## Sequência do ensaio

1. Exigir runner GitHub hospedado, repositório correto, Docker local e ID de
   execução válido. Negar nomes/recursos preexistentes antes de iniciar.
2. Expandir o Compose existente e validar redes internas, ausência de portas
   no host, segredos por arquivo, usuário PostgreSQL restrito e política NO_AI.
   Verificar novamente essas propriedades nos contêineres em execução.
3. Inicializar o n8n real. Parar seu processo principal antes de usar a CLI,
   evitando a colisão da porta interna do Task Broker já tratada em P9/P12.
4. Importar e executar o único workflow inativo permitido: Manual Trigger e
   dois nós Set. A saída deve conservar 120 visitas, 12 leads, 2 clientes e
   zero chamadas/tokens/créditos de IA. Nenhum nó referencia a credencial.
5. Importar uma única credencial fictícia `httpBasicAuth`; validar que a forma
   persistida/exportada é cifrada e que a exportação decriptada corresponde
   exatamente ao objeto sintético. Os arquivos e logs ficam em diretório
   temporário protegido e não são publicados como artefatos.
6. Com o n8n parado, gerar `pg_dump -Fc` do schema real, TAR do volume somente
   leitura e cópia da chave sintética. O contrato P20 exige também os dois
   componentes CRM/Agents: neste ensaio eles contêm **bancos de marcadores
   sintéticos PG17**, não migrations nem dados dos produtos. Ambos são
   restaurados e comparados, sem alegar recuperação dos schemas reais.
7. Selar o pacote CMS AES-256-GCM, assinar recibo Ed25519 e copiar o ciphertext
   para outro diretório **no mesmo runner**. Validar o pin independente do
   signer sintético, assinatura, hash e escopo antes da decriptação positiva.
8. Negar ciphertext adulterado no recibo, adulteração GCM e chave CMS errada.
   Somente depois da decriptação autenticada validar manifesto, todos os hashes
   e a estrutura interna do TAR. Copiar os cinco nomes fixos para novos arquivos
   regulares privados, nunca usar `extractall` sobre o pacote recebido.
9. Recriar o TAR do volume usando exclusivamente arquivos/diretórios canônicos,
   uid/gid 1000 e modos 0600/0700. Somente esse TAR novo é extraído em um volume
   Docker novo e vazio. Metadados e privilégios recebidos não são preservados.
10. Restaurar o banco n8n com `pg_restore --exit-on-error --no-owner --no-acl
    --role=n8n_runtime`, volume e chave; os passwords PostgreSQL do destino são
    novos. Inicializar o destino e verificar readiness, role restrita e
    isolamento real das redes.
11. Validar contagens, marcador do volume, igualdade do ciphertext da credencial,
    decifra exata do dado fictício e execução do mesmo workflow. Com chave n8n
    errada, exigir falha e ausência de exportação: a recusa pode ocorrer na
    comparação com `.n8n/config` durante bootstrap, antes de tentar decifrar a
    credencial. O relatório não afirma uma etapa mais específica.
12. Remover somente os projetos criados pelo próprio job, inclusive seus volumes
    e redes. A limpeza não deve ser copiada para VPS ou usada em outro projeto.

## Imagens e fidelidade

O digest do n8n é o já usado no laboratório:
`ghcr.io/n8n-io/n8n@sha256:b4b90747b81fcda7e76550c0d3aa7f0f64917a1e9573cff40d6834eaf2e0f8ea`.
O repositório não possui um digest imutável de PostgreSQL 17 homologado: a CI
mantém o procedimento existente de obter `postgres:17`, resolver seu RepoDigest
e usar **o mesmo digest** nos dois projetos e nas ferramentas auxiliares.
O driver exige formato SHA-256 e confirma `server_version_num` de major 17.
Essa resolução de tag não é homologação de imagem para produção.

Contratos CLI conferidos na fonte oficial n8n, commit
`d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063`:

- [Importação de credenciais](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/cli/src/commands/import/credentials.ts):
  array de objetos e cifragem de `data` via `Cipher.encryptV2`.
- [Exportação de credenciais](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/cli/src/commands/export/credentials.ts):
  `--all --decrypted --output` e decifra da credencial pela aplicação.
- [Chave da instância](https://github.com/n8n-io/n8n/blob/d4dcb1b152a98fc31ea7c4ee6aecdc45c5c88063/packages/core/src/instance-settings/instance-settings.ts):
  inconsistência com o config local impede a inicialização.

## Validação e limites

O workflow executa P20, P22, P23, segurança de recibos P24, fronteira de extração
P24 e suítes Agents na árvore temporária; o ensaio completo é executado uma vez.
Validação local sem Docker pode executar as suítes Python e `py_compile`, mas
não comprova o runtime n8n/PostgreSQL. Falha ou job ignorado não conta como PASS.

O relatório mantém `go_for_installation=false`,
`external_backup_verified=false`, `different_host_restore_verified=false`,
`real_signer_custody_verified=false` e `real_crm_agents_schemas_restored=false`.
Dois projetos Compose no mesmo runner não representam outro host. O ensaio
não verifica credenciais de clientes, snapshot distribuído, retenção/RPO/RTO,
IAM/MFA operacional, revogações atuais, R2 real, custódia humana, licença,
permissão de instalação ou recuperação da VPS. Não há integração publicitária,
fornecedor de IA, gasto, merge em main ou deploy.

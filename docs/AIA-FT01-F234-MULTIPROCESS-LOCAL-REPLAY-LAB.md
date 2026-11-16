# F234 — Ensaio de replay atômico entre processos PHP, apenas no host local

**15/11/2026 | DRAFT | NO-GO | NO-MERGE | NO-DEPLOY | NO-COST**

## Objetivo

F233 compartilha um objeto PHP apenas no mesmo processo. F234 testa 12 processos PHP independentes, todos apontando para um arquivo temporário local e o mesmo nonce de teste, com exclusão flock(LOCK_EX) durante leitura, validação, comparação e gravação. O resultado esperado é uma única reserva bem-sucedida; as outras 11 falham. Reinício de processo não libera o nonce.

## Implementação

- MockFileAtomicNonceLab aceita somente nome de arquivo controlado sob sys_get_temp_dir() no padrão aia-f234-ID.json; inicialização separada falha se o arquivo já existe.
- Armazenamento contém exclusivamente hashes de issuer+nonce sintéticos e vencimentos de teste; limitado e sem TTL eviction.
- Dados truncados, malformados, saturados, arquivo ausente e emissão inválida falham fechados.
- tools/check_f234_multiprocess.py cria arquivo efêmero com umask 077, inicia 12 workers PHP independentes e verifica exclusividade, reinício, saturação, entrada inválida e corrupção. Apaga o arquivo ao final.

## Limites críticos

flock é uma prova de exclusão local de host, não de Redis/PostgreSQL, armazenamento compartilhado entre nós, operação linearizável distribuída ou durabilidade contra queda de energia. O formato JSON e ftruncate não têm journal/WAL; arquivo parcial leva a negação conservadora. Nunca usar em NFS, produção, IAM, ACL ou serviços externos. O backend CRM continua sendo a única autoridade de leitura e deve validar concorrência, política de escopo e armazenamento em staging autorizado.

Nenhuma alteração em main, VPS, DNS, credenciais, custos ou produção. Os 14 gates, 7 decisões e 13 cenários reais OP-01 continuam pendentes.

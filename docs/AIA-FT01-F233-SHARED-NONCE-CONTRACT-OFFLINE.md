# F233 — contrato de consumo único de nonce sintético entre objetos

**15/11/2026 — OFFLINE / NO-GO / NO-MERGE / NO-DEPLOY.**

## Escopo concluído

A classe `MockSharedNonceLedger` permite que dois pontos de chamada em um **único processo PHP** recebam a mesma instância de controle de replay. O par `issuer + nonce` é registrado uma vez, independentemente de tenant (não se aceita mudança de tenant para reutilizar o nonce). A capacidade é limitada a 64 registros por padrão (1–512); indisponibilidade, dados inválidos e saturação retornam `false`; o histórico não é eliminado para recuperar espaço. Um registro expirado permanece bloqueado neste mock, preservando a segurança à custa da disponibilidade.

`tools/check_f233_shared_nonce.php` exercita chamada duplicada entre dois consumidores da mesma instância, capacidade, indisponibilidade/recuperação, dez entradas inválidas e **demonstra intencionalmente** que dois objetos com armazenamento separado permitem consumir o mesmo nonce.

## Não homologado

O teste não demonstra concorrência entre processos, compare-and-set atômico, Redis/PostgreSQL, TTL durável, persistência após reinício, isolamento IAM, autorização real, integração CRM ou concessão de leitura. Ele não se conecta automaticamente à F231 nem autoriza respostas positivas.

O **CRM** deverá validar identidade, política de tenant/principal/recurso/campo e revogação por meios próprios. Antes de qualquer piloto operacional: escolher o armazenamento compartilhado e a operação atômica, definir um namespace inequívoco e janela anti-replay, provar concorrência de múltiplos workers e falha/reinício no staging isolado, revisar política de retenção LGPD e concluir 14 gates, 7 decisões e 13 cenários reais ainda pendentes do CRM #57.

Não criar endpoints, dados reais, segredos, instalação de serviços, cobrança, deploy, merge ou alteração em `main`/VPS.

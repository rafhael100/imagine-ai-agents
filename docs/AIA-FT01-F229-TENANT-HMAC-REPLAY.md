# F229 — isolamento multiempresa e replay HMAC no laboratório Agents↔CRM

**14/11/2026 · SOMENTE OFFLINE · NO-GO.**

## Objetivo

Executar uma prova **bilateral real de código PHP**, combinando o emissor
`MockCrmReadRequest::issueSyntheticSignedFixture` do Imagine AI Agents à
verificação `AiaHmacFixturePreflight` e ao ledger em memória presentes na
**fonte CRM imutavelmente fixada na revisão
`d7c7aacc766c7ade4c65dcd211ed4569442144a9`**.

O caso F229 comprova, somente para três empresas **fictícias**, que uma
prova HMAC de teste assinada para o tenant A não deve ser aceita para B/C;
que adulteração de bytes canônicos, claims, assinatura, chave e janelas de
tempo falha sem consumir nonce; e que replays, indisponibilidade e
saturação da memória local falham conservadoramente.

## Protocolo de verificação

- Três provas positivas, **somente criptográficas**: cada empresa
  fictícia tem payload, chave pública de teste e nonce próprios;
  cada prova pode ser consumida uma vez no ledger local.
- **54 casos negativos** (18 por empresa): tenant trocado, chave trocada,
  corpo mudado, espaço extra, propriedade JSON duplicada, claims de tenant,
  nonce, emissor, audiência, digest, MAC, expiração e emissão adulterados,
  campo não permitido, ordem alterada, acesso prematuro, expirado e
  corpo excessivo. Todos exigem consumo de nonce igual a zero.
- Queda do ledger local: negação sem consumo; recuperação preserva replay.
- Saturação com uma posição e colisão de nonce em empresas distintas:
  o segundo pedido não pode ser admitido, nem apagar o primeiro.
- Em todas as variantes, a fachada pública CRM responde
  `DENIED_OR_NOT_FOUND`, sem projeção nem autorização ou chamadas externas.

O script `tools/check_f229_tenant_replay_matrix.php` é executado **depois**
do checkout exato da SHA CRM, sem instalar dependências nem chamar serviços.
O contrato `tests/Contracts/test_f229_tenant_replay_matrix.py` verifica
que a execução, os controles de acesso da CI e a documentação não sejam
removidos. Mantém-se baseline de **879 testes PHP / lint 110**, mais
os ensaios F229 de verificação cruzada.

## Limitações e gates

**CRM #57 OPEN/DRAFT; 14 gates, 7 decisões e 13 cenários reais
NAO_EXECUTADO; NO-GO.**

Esse teste é importante para detectar regressões, **mas não é evidência de
IAM real**, ACL por empresa/recurso/campo, segredo protegido, replay
distribuído durável, API autenticada, staging homologado ou acesso a dados
reais. Nenhuma alteração em `main`, VPS, clientes, produção, segredos,
custos, implantação ou merge automático. A PR F229 permanece
DRAFT/NO-MERGE até a CI da HEAD exata e revisão dos gates.

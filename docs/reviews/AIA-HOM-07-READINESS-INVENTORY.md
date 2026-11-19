# HOM-07 — inventário documental, sempre sem autorização operacional

Esta entrega acrescenta um CLI **offline e somente leitura** para classificar as
evidências de HOM-01..HOM-06 da issue #414, sobre a candidata #483
(HEAD inicial `85a17f2df9f618735a42d8690528d46c78cc904e`).

## Uso

```bash
python3 infra/n8n-private/homologation/hom_readiness.py evidencias.json \
  --expected-candidate-sha 85a17f2df9f618735a42d8690528d46c78cc904e
python3 -m unittest discover -s infra/n8n-private/homologation/tests -p 'test_hom_readiness.py'
```

Envelope JSON restrito: `schema=IMAGINE_AIA_HOM_READINESS_V1`,
`candidate_sha` SHA40, `gates` objeto exatamente com HOM-01..HOM-06.
Cada gate recebe apenas `state` (PENDING, BLOCKED, REVIEWED) e
`evidence` (lista de 0..10 referências opacas sem texto privado).
**REVIEWED informa somente que alguém registrou documentos**, não
autentica revisão, não comprova a VPS e não concede autorização.

Saída: `NO_GO` se faltar documentação, houver incompatibilidade de
SHA ou entrada inválida; `REVIEW_REQUIRED` se os seis gates tiverem
referências bem formadas. Em todos os casos:
`operational_authorization=false`. O CLI não emite GO, não chama
rede, não lê segredos, não opera Docker, SSH, DNS, banco ou VPS.
Não substitui AppSec/DBA/LGPD, checklist humano, assinatura e
procedimento operacional de homologação.

## Próximas dependências reais

- HOM-01/02: inventário atual e impacto em serviços da VPS autorizada.
- HOM-03/04: hostname privado, MFA real e identidade S2S comprovada.
- HOM-05: backup externo recorrente, restauração real e RPO/RTO observados.
- HOM-06: revisão independente e execução privada NO_AI segregada.

Este checker é intencionalmente incapaz de remover bloqueios externos.
Não houve merge, deploy, instalação, credencial, dados reais ou custo.

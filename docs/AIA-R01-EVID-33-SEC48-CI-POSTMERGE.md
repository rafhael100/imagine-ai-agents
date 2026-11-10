# R01/EVID-33 — SEC-48 integrada: negação de reentrada OP-01

**Verificação:** 10/11/2026. **Decisão operacional: NO-GO.** Evidência somente do simulador PHP offline, sem CRM real.

## Evidência GitHub

- [PR #226 — SEC-48](https://github.com/rafhael100/imagine-ai-agents/pull/226): squash integrado exclusivamente à `dev/ft01-aia-core-mocks-2026-11-06` no commit `286cb03019e519dd13434f21ea779adec6fb256d`.
- [CI pós-merge #42924834951](https://github.com/rafhael100/imagine-ai-agents/actions/runs/42924834951): `completed/success` para o mesmo SHA, **613 testes PHP, lint de 83 arquivos PHP** e verificações Python offline.
- A fachada recusa `handle()` recursivo imediatamente, sem novo parser, chamada ao leitor, nonce ou auditoria. Um guard opcional no `MockCrmReadBoundary` impede que a leitura original seja registrada como sucesso depois de uma tentativa aninhada, inclusive se o adaptador descartar a resposta negada. A auditoria do procedimento externo registra somente `DENIED/OTHER`, sem texto bruto.
- Oito regressões **U70-01..08** cobrem tentativa idêntica e de outra empresa, várias reentradas, ausência de consumo adicional, recuperação com prova nova, invalidar recibo anterior e preservação de relógio monotônico.

## Limites

A implementação é um guard **em uma única instância PHP**. Não há lock ou cache distribuído, isolamento/ACL real de banco, autenticação, integração HTTP, concorrência multiprocesso, auditoria persistente ou revisão independente. O CRM deverá possuir sua autorização real e seus próprios controles de concorrência.

[CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) permanece OPEN/DRAFT; **13 cenários de homologação real NAO_EXECUTADO**, decisões OP01-R01..R07 e gates R01-G01..G11 / CRM CORE/SEG/AUT ABERTOS/NO-GO. Nenhuma alteração em `main`, VPS, produção, dados de clientes, credenciais ou custos.

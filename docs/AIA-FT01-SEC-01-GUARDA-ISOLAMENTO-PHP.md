# FT01/SEC-01 — Guarda preventiva de isolamento de código sintético PHP

**Classificação:** prevenção de regressões em laboratório. **Status de integração operacional: NO-GO**. Não é autorização CRM, autenticação, RLS, revisão jurídica, sandbox, SAST abrangente ou garantia formal de ausência de efeitos externos.

## Motivação e limites
A regressão dos 228 casos PHP verificava comportamentos fictícios, mas não reprovaria automaticamente novas funções de rede, processo, I/O ou banco no núcleo `src/` caso fossem introduzidas em código novo não coberto por testes. O novo `tools/check_synthetic_php_isolation.php` examina tokens PHP com `token_get_all(..., TOKEN_PARSE)` **sem incluir nem executar os arquivos escaneados**.

A guarda rejeita referências diretas a operações como `curl_init`, `fsockopen`, `stream_socket_client`, `file_get_contents`, `file_put_contents`, `exec`, `proc_open`, `getenv`, `new PDO`, `new Redis`, `eval`, `include/require` e superglobais. Strings e comentários não contam como chamadas. Rejeita symlinks e arquivos desproporcionais. A saída JSON inclui apenas categorias e contagens, sem nomes de arquivos nem conteúdo.

**Limitação:** o scanner detecta apenas construções diretas conhecidas. Não prova ausência de toda execução dinâmica, não confere dependências indiretas nem elimina a necessidade de revisão humana. Novos adaptadores reais exigiriam arquitetura separada, autorização expressa e atualização consciente dos controles, nunca o enfraquecimento silencioso da guarda.

## Reprodutibilidade
```bash
php -l tools/check_synthetic_php_isolation.php
php tools/check_synthetic_php_isolation.php
python3 -m unittest discover -s tests/Contracts -p test_synthetic_php_isolation.py -v
```

O workflow `.github/workflows/r01-dev-integrity.yml` executa esses comandos antes de `tools/validate_ft01_offline.sh`, mantendo a verificação de checkout no SHA do commit. Os testes são 100% offline; as fixtures contendo nomes de funções proibidas **não as executam**. **Uma saída PASS não implica que há autorização para CRM, VPS, provedores, credenciais ou produção.**

Gates R01-G01..G11 e CRM CORE/SEG/AUT permanecem abertos e [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) continua documental/DRAFT. Nenhuma alteração na `main`, dados de clientes ou serviços externos.

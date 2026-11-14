# F213 — Painel de acompanhamento do caminho crítico OP-01

**14/11/2026 · SPA estática sem JavaScript, rede ou dados reais.**

A página `examples/f213-op01-plano.html` disponibiliza uma visualização mobile-first das **cinco frentes F212**, no padrão leve e laranja oficial Imagine (#F9941D), reutilizando `examples/assets/logo-imagine.png` sem alterar a imagem. A navegação por âncoras internas mostra responsáveis sugeridos, dependências, gates, cenários e evidências. Cada seção utiliza `<details>` para consulta acessível sem executar código ou enviar dados.

**14 gates abertos, 13 cenários reais NAO_EXECUTADO, 7 decisões pendentes, 0 autorizações operacionais.** O painel é informativo: não tem botão de aprovar, formulário, armazenamento, API, rede, tokens, autenticação ou deploy.

A verificação automática `tools/check_f213_op01_view.py` compara conteúdo da página com `tools/plan_op01_release.py`, que valida os inventários e o pacote bilateral, rejeita gates/casos ausentes, evidências alteradas, supostas liberações e dependências externas. O gate CI exige essa verificação e oito regressões Python; baseline PHP 879/lint110 inalterado.

Abrir o arquivo HTML diretamente no navegador, com `examples/assets/logo-imagine.png` no mesmo repositório; não é necessário servidor. **CRM #57 OPEN/DRAFT e R01-G01..G11/CORE/SEG/AUT/LGPD seguem NO-GO.**

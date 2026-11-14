# Imagine AI Agents — identidade original nas telas do laboratório F108

Data: 12/11/2026. Diretriz da Direção: reutilizar a **primeira identidade Node do Imagine Portal**, com logomarca original e UX elegante, leve e navegável no desktop e no celular. Contrato: [Design System Imagine](https://github.com/rafhael100/imagine-portal/pull/589) e issue [#289](https://github.com/rafhael100/imagine-ai-agents/issues/289).

Apenas o laboratório `examples/f108-laboratorio-navegavel.html` foi alterado: marca original embutida (PNG completo verificado pelo objeto Git `678adeceb3a8ecdaeb6b576b9ee18d1bf0396f77`) e preservada também em `examples/assets/logo-imagine.png`; menu lateral branco com seleção laranja e navegação horizontal adaptativa; cabeçalho claro, cards suaves, botões laranja, hero grafite e foco visível. Sem alterar os botões, dados fictícios, JS de navegação, contrato de estados da fila ou fluxos de segurança.

**Segurança:** toda a navegação permanece sintética/offline, CSP `connect-src 'none'`, nenhuma requisição externa, formulários, CRM real, tenant, IAM, worker ou produção. Estados de erro/sucesso preservam semântica; HTML mantém avisos NO_GO. O teste Node `tests/Browser/f108_imagine_brand.cjs` verifica PNG original, CSP, tokens e menu e complementa `f108_smoke.cjs`.

**Pendências:** QA de navegador gráfico, regressões completas, outras telas independentes de agentes e eventual integração segura em dev. Branch apenas revisão; nenhum merge/deploy/custo automático.

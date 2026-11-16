# AUTO-IA-01 — Matriz de roteamento sem consumo de IA

**Diretriz:** [DEC-AUTO-IA-001 / issue #339](https://github.com/rafhael100/imagine-ai-agents/issues/339)

**Estado:** implementação isolada, exclusivamente sintética, em revisão. Nenhum deploy, n8n operacional, dados reais, credenciais, serviço de IA, token ou custo. Base fixada na PR #334; não incorpora nem altera PRs de demonstração comercial.

## Fluxo
- **NO_AI:** operação determinística resolvida por regras da aplicação, banco ou n8n; **não autoriza** alteração de estado por si.
- **AI_REVIEW_REQUIRED:** apenas classificação consultiva para futuro Imagine AI Gateway, **jamais autorização** de consumo. O gateway deverá verificar IAM, empresa, consentimento, finalidade, idempotência distribuída, limite financeiro, créditos comerciais separados de tokens e uso do fornecedor.
- **DENY:** operação desconhecida, esquema malformado, escopo sintético inválido ou justificativa sem correspondência. Não existe fallback para chamadas pagas.
- Toda classificação retorna **provider_calls=0** e **model_tokens=0**. Não há cliente HTTP nem SDK.

## Matriz inicial (exemplos)
| Sistema | Automação convencional | Candidata a revisão de IA |
| --- | --- | --- |
| Portal | Avisos de eventos e estado de contratos | Interpretação de mensagem de suporte |
| CRM | Entrada e scoring por regras de leads | Interpretação de mensagem livre |
| Sites | Receber briefing e notificar aceite | Propor texto de site |
| Comercial | Cálculo de orçamento e funil | Propor texto de campanha |
| Performance AI | Agregar métricas e calcular gestão | Propor texto publicitário |
| Universidade | Progresso e elegibilidade | Interpretar pergunta do tutor |
| SEO Engine | Auditoria hreflang e sitemap | Propor metadescrição |
| AI Agents | Avaliação de cota e projeção de status | Propor resumo de documento |

A lista fechada de nomes de operação está em `AutoIaRouter::matrix()`. Novas operações exigem revisão de código, testes e atualização desta documentação; nenhuma entrada pode escolher diretamente provedor, endpoint ou credencial.

## Verificação
```bash
php -l tools/auto_ia/AutoIaRouter.php
php -l tools/auto_ia/check_auto_ia.php
php tools/auto_ia/check_auto_ia.php
```

## Critérios que permanecem bloqueados
- A classificação não executa a tarefa determinística: cada serviço continua responsável por suas próprias permissões e resultados.
- Nenhum servidor, banco, IAM, ACL, gateway, saldo, reserva ou conciliação foi criado.
- Sem execução n8n ou integração com créditos; regras de uso do fornecedor e licenciamento ainda exigem validação.
- O controle futuro **AUTO-IA-02** deve exigir autorização e orçamento explícitos com expiração/revogação, falha segura, teto por operação e medida de consumo independente de créditos comerciais.
- Antes de liberar operação: revisão técnica independente, testes entre empresas, testes de falhas e homologação de ponta a ponta.

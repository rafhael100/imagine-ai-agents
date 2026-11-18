# AIA-RC65 — Central P23 e AI Agents no mesmo Git merge-tree temporário

Complementa [AIA-RC64 #454](https://github.com/rafhael100/imagine-ai-agents/pull/454), que executava as verificações da Central na versão P20. Esta atualização **não refaz a integração de receptor P19** nem modifica a `main`/VPS; fixa a [P23 #456](https://github.com/rafhael100/imagine-ai-agents/pull/456) com suas predecessoras [P21 #452](https://github.com/rafhael100/imagine-ai-agents/pull/452) e [P22 #455](https://github.com/rafhael100/imagine-ai-agents/pull/455).

## Nova prova
- `tools/run_rc65_virtual_tree.sh`: usa `git merge-tree --write-tree --name-only` entre a HEAD RC65 e o commit imutável P23, recusando conflitos. Exporta o objeto somente para `RUNNER_TEMP`, sem criar merge commit, branch ou push.
- Sobre essa **mesma árvore de código temporária**, executa a regressão original P20 de certificados/pacotes, validação P22 de TAR (inclusive manifesto válido com volume malicioso), assinatura Ed25519 P23 com verificação do signer público pinado e o fluxo P21 completo: criptografa CMS, autentica o recibo, descriptografa, verifica manifesto/TAR, restaura **um dump PostgreSQL17 n8n sintético e dois PostgreSQL16 CRM/Agents sintéticos** em bases novas.
- Executa também as verificações de domínio originais da linha Agents RC64: roteamento determinístico, regras, CLI de prévia e leitura de fila de revisão. Verifica sintaxe do receiver P19, sem expor um endpoint real ou executar dispatch n8n.
- O YAML usa duas instâncias PostgreSQL descartáveis com acesso ao container apenas pelo runner GitHub e conta temporária. O código/keys não são publicados como artefatos nem transferidos para nenhum host.

## Limites
Não há merge real de branches, aplicações em execução junto a clientes, consistência transacional distribuída, autenticação operacional de empresa, validade da custódia humana, backup externo ou recuperação em host distinto. O signer P23 é uma fixture de laboratório, não um custodiante verdadeiro.

Para homologação real continuar [issue #414](https://github.com/rafhael100/imagine-ai-agents/issues/414) e evidências P19/P20; revisar o escopo de licença n8n, host Leaseweb, identidade S2S e MFA, egress, backup externo/restauração e parecer independente. **NO-GO para merge, deploy, custo de IA ou dados reais.**

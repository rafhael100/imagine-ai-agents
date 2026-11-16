# CENTRAL-AUTO-P18 — Integração sem merge: confrontar três linhas GitHub reais

A P17 [#438](https://github.com/rafhael100/imagine-ai-agents/pull/438) e a trilha paralela AIA [P60 #444](https://github.com/rafhael100/imagine-ai-agents/pull/444) implementaram capacidades relevantes, mas **não foram integradas à `main`**. A candidata de identidade [Central P3/P4 #435](https://github.com/rafhael100/imagine-ai-agents/pull/435) também está em outra trilha. Antes de criar mais automações ou instalar n8n, é necessário auditar a convergência.

## O que P18 verifica
- Compara snapshots imutáveis da P17, do AIA P60, da PR integrada de identidade #435 e da `main` observada em 16/11/2026. **Se qualquer branch avançar, o teste recusa reutilizar a evidência antiga e exige atualizar os SHAs.**
- Obtém o merge-base real e o número de commits exclusivos, caminhos alterados e caminhos modificados em ambas as trilhas. Utiliza `git merge-tree --write-tree --name-only` para tentar uma fusão **somente em objetos efêmeros do runner**, sem atualizar HEAD, sem fazer merge, sem push e sem acessar GitHub Secrets.
- Se existirem conflitos, o job **falha fechado** e registra até quinze caminhos de conflito/overlap, para uma revisão independente da diferença. Mesmo que as duas simulações passem, o status final continua `NO_GO_OPERATIONAL`: compatibilidade de histórico Git não valida integração funcional, banco, segredos ou IAM real.
- Alerta explicitamente se a candidata Central acumular mais de 100 commits não integrados à `main`. Esse valor é um limiar **heurístico para auditoria**, não a quantidade adequada de um PR; exige um plano de revisão mais fino.
- A CI tem token somente leitura e `concurrency` que cancela execuções substituídas; não executa Docker, modelos, rede de clientes nem qualquer script de deploy.

## Snapshots que precisam de revalidação após novos commits
- `main`: `ec305688528fe35e9a9dc2d3b1bda1255cbd074a`
- Central P17: `f558e94abd11c802ab7d59321b9aae9f8bd74b2d`
- Agents P60 com correção de teste negativo [#449](https://github.com/rafhael100/imagine-ai-agents/pull/449): `a8277b7c9052d35e909e487823675ab0b823ccc5` (snapshot fixado para integração; não representa merge no P60 original)
- Central identidade P3/P4: `5f96750619e26bb860486a9f43bc83805a2e0ef4`

## Critérios de conclusão
1. Conflitos nomeados e classificados por área, com decisão de qual implementação manter/combinar, sem sobrescrever trabalho paralelo.
2. Candidata integrada de revisão com checklist de compatibilidade funcional, versão do n8n/PG17, mTLS, política NO_AI, contratos CRM/Portal/Agents e risco de lançamento.
3. Comparação incremental com `main` após as integrações: evitar um PR monolítico de centenas de commits sem parecer independente. Preferir recortes auditáveis e estratégias de redução de risco.
4. Só após homologação real na VPS, IAM/IdP/MFA, licença, backup externo e autorização humana explícita considerar instalação de staging; **CI de merge-tree é exclusivamente informativa**.

**DRAFT / NO-MERGE / NO-DEPLOY / NO-COST / NO-GO.**

# AIA-P26 — Regressão integrada da pilha P13–P25

16/11/2026. Esta candidata empilhada sobre P25 (#383) executa no mesmo job GitHub Actions um ciclo completo de testes dos contratos e das proteções entre P13 e P25, sem mesclar nada na main.

## Cobertura

- Convergência do adaptador CONV-02 fixado em SHA, P13/P14, roteamento P5 e bloqueios RC02.
- P15 para política por empresa e operação, teste P20 de fluxo manual NO_AI e roteador oficial sem chamadas de provedores.
- P8/P11 para créditos sintéticos assinados, P6 para fila, P16 para replay transacional, P17 para commit compartilhado e P18 para falhas e concorrência.
- P19 para isolamento de leitura com papéis restritos, P22 para identidade de banco, P23/P25 para auditoria de hashes/request/nonce e P24 para papéis de escrita.
- Relatório JSON que, por desenho, continua NO_GO, sem converter CI em autorização.

## Gates ainda abertos

1. Revisão independente de segurança do conjunto de PRs e dependências.
2. Identidade entre serviços, autoridade operacional de tenant, operações e consentimento no serviço dono da informação.
3. Credenciais S2S reais, mTLS/HTTPS onde exigido, rotação e revogação.
4. Homologação em infraestrutura autorizada, n8n real, backup/restauração, monitoramento e licenciamento.
5. Integração e aceites CRM/Portal com rollback e prova de zero chamadas indevidas de IA.

A pipeline não instala n8n, não acessa a VPS, não usa dados reais, não chama APIs pagas, não executa faturamento e não efetua merge. **DRAFT / NO-GO / NO-MERGE / NO-DEPLOY.**

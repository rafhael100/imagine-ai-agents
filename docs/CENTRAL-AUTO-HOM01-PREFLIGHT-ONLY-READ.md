# CENTRAL-AUTO-HOM01 — Inventário da VPS em modo estritamente somente leitura

Atende à primeira pré-condição da [issue #414](https://github.com/rafhael100/imagine-ai-agents/issues/414), baseada na candidata [RC07 #413](https://github.com/rafhael100/imagine-ai-agents/pull/413). Esta implementação **não acessa a Leaseweb** e não transforma evidências de CI em homologação operacional.

## Ferramentas incluídas
- `infra/n8n-private/readonly_inventory.py --probe-host`: coleta apenas quantidade de contêineres/redes/volumes Docker, hashes truncados dos nomes, versões Docker Engine/Compose, CPU, memória disponível, disco livre e observações de escuta das portas **17990** (antiga candidata de administração n8n), **18081** (CRM citado) e **18090** (Sites citado). Todos os comandos são de consulta; não lê docker inspect, configurações de ambiente, logs, chaves, volumes, arquivos de clientes ou dados de rede externos.
- `infra/n8n-private/assess_readonly_host.py caminho.json`: valida o esquema estrito do relatório, recusa chaves extras, segredo inserido, falsificação de status GO, tipos impróprios e portas desconhecidas, produzindo **sempre NO_GO**, com riscos de capacidade/portas e lista de pré-condições.
- Testes `test_readonly_inventory.py` e `test_assess_readonly_host.py`: execuções sintéticas e casos negativos de falso hostname, segredos, versões malformadas e alterações indevidas. A CI **não usa --probe-host** para não confundir o host GitHub com a Leaseweb.

## Uso controlado — apenas operador com acesso aprovado
Executar no **host Leaseweb identificado por canal independente**, no ambiente já autorizado do Codex/SSH, caso exista:

```bash
python3 infra/n8n-private/readonly_inventory.py --probe-host > /tmp/imagine-n8n-inventory.json
python3 infra/n8n-private/assess_readonly_host.py /tmp/imagine-n8n-inventory.json
```

Não publicar o JSON original ou os hashes de contêineres em issues públicas; não anexar arquivos `.env`, credenciais, saídas completas do Docker, dados pessoais, IPs ou chave do host. Limpar arquivo local segundo política aprovada. A avaliação é um **indício técnico não autenticado** e não certifica a identidade da VPS por si só.

## Regras e limitações
- A marcação de porta livre significa apenas que nenhum listener TCP foi observado naquele instante: não reserva a porta e não comprova titularidade, rota ou acesso administrativo. Como o Compose endurecido da RC07 **não publica nenhuma porta do host**, o futuro ingress do n8n deve ser autorizado independentemente.
- Uma porta 18081 ou 18090 ocupada é apenas observação; **não deve ser atribuída automaticamente ao CRM/Sites** sem prova independente. Não interagir com os serviços existentes.
- Os limites provisórios de avaliação (4 CPUs, 4 GiB de memória livre, 30 GiB de disco livre) não são dimensionamento homologado; o planejamento real depende de workloads concorrentes e política de backup.
- Não coletar hostname, machine-id, endereços, arquivos de chave, nomes completos dos contêineres ou segredos. O host deve ser atestado por inventário administrativo confiável separado, e a revisão AppSec/DBA/LGPD continua necessária.
- Este trabalho não instala n8n, Portainer ou banco na Leaseweb, não atualiza Docker, não altera DNS ou firewall, não faz merge, deploy ou chamada paga de IA.

**DRAFT / NO-GO. A entrega consiste em código de inventário, avaliação e testes em CI — não um inventário real da VPS.**

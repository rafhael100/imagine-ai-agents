# Arquitetura inicial — proposta para revisão

## Mapa lógico

```text
[Imagine CRM]      [Portal Imagine]      [Performance AI]      [Universidade]
       \                 |                    |                    /
        \------- APIs autenticadas / eventos / contratos versionados ----/
                                      |
                            [Imagine AI Agents]
                 Agentes e versões / Políticas / Orquestração
                 Execução / Aprovações / Conhecimento / Memória
                     Avaliações / Auditoria / Custos
                                /         \
                         [Modelos IA] [Ferramentas autorizadas]
                                            |
                                  [APIs dos produtos]
```

## Fronteiras dos domínios

| Domínio | Dono da regra e do dado | Responsabilidade de Imagine AI Agents |
|---|---|---|
| Leads e funis | CRM | Interpretar contexto autorizado e solicitar ferramentas oficiais |
| Contratos e cobranças | Portal/produto responsável | Sugerir ações; não alterar registros sem validação |
| Campanhas | Performance AI | Planejar, analisar e acionar ferramentas autorizadas |
| Cursos e conteúdo pedagógico | Universidade | Apoiar tutoria e recuperar conhecimento aprovado |
| Identidades e permissões | Produto/serviço de identidade | Validar escopos, tokens e políticas correspondentes |
| Automações visuais dos clientes | CRM ou produto dono do fluxo | Executar capacidades IA sob contratos, sem duplicar o motor |

## Segurança e execução — requisitos candidatos

- Autenticação serviço-a-serviço, escopos por empresa/produto/operação e autorização final no backend proprietário do dado.
- Segregação de contexto, arquivos, recuperação semântica, memória, ferramentas, métricas e logs por tenant.
- Configurações versionadas, histórico de execução imutável conforme política e trilhas auditáveis.
- Execução assíncrona, idempotência, retentativas seguras, limites de concorrência e contingência.
- Aprovação humana para operações externas, financeiras, irreversíveis ou de elevado risco.
- Conteúdo externo não confiável e mecanismos de proteção contra injeção de instruções.
- Avaliações offline, testes de regressão, análise de custo e rollback de versões.

## Tecnologias: decisão aberta

O CRM tem Laravel/PostgreSQL como diretriz, mas a stack do núcleo compartilhado **ainda não está aprovada**. Avaliar compatibilidade com as APIs, segurança, custo e operação. Evitar banco de dados compartilhado entre produtos. n8n pode ser usado para fluxos internos, desde que o enquadramento comercial seja validado.

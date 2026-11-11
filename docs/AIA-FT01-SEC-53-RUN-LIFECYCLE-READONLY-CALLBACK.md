# FT01/SEC-53 — proteger o ciclo de vida de Run durante sugestão

**Em 10/11/2026. Estágio:** laboratório PHP com dados sintéticos, sem rede nem integração CRM real. **Operação: NO-GO.**

## Lacuna concreta

As proteções SEC-51 (instância de ferramenta) e SEC-52 (coordenador compartilhado) negam chamadas recursivas ao despachante e ao coordenador. Entretanto, `AiSuggestionProvider::suggest(Run $run, ...)` recebe a própria instância mutável de `Run`, que expunha `transition()` e `step()` diretamente. Um provedor injetado — ou closure que já conhece essa instância — poderia tentar completar, cancelar, suspender ou criar um Step fora do coordenador e do seu trace.

## Implementação

- `Run::withReadOnlySuggestion(AiSuggestionProvider, array)` executa uma chamada tipada e explícita a `suggest()`; ativa e encerra em `finally` uma guarda **em memória por objeto** enquanto o provedor gera a sugestão. É uma zona de leitura, não permissão real.
- `Run::transition()` e `Run::step()` negam imediatamente com `PROVIDER_CALLBACK_DENIED` enquanto a guarda está ativa, **antes** de qualquer transição ou criação de Step.
- A tentativa de mutação permanece marcada até sair do callback. Mesmo se o provedor capturar uma exceção e retornar uma proposta aparentemente correta, a sugestão externa será rejeitada; nenhuma trilha `SUGGESTED` é registrada.
- `SupervisedCoordinator::suggest` chama o provedor dentro de `Run::withReadOnlySuggestion`, além de manter a guarda SEC-52 contra reentrada nas suas próprias APIs.
- A guarda é liberada mesmo com falha; o despachante pode então encerrar o Run com `FALHOU` e registrar o evento de ciclo de vida correspondente. Provedores podem consultar `state()` e `assertActive()` sem alteração.

## Testes U75-01..08

1. `transition('CONCLUIDA')` direto, negação capturada: Run final `FALHOU`, sem `SUGGESTED`.
2. `step()` direto, negação capturada: nenhum Step na trilha.
3. Tentativa de reinicializar a guarda via chamada aninhada a `withReadOnlySuggestion` negada.
4. Transição direta não capturada nega retorno, sem execução de ferramenta.
5. Transição de ciclo inválida dentro do callback também é negada e deixa marca persistente.
6. Uso **direto** do coordenador (sem despachante) com captura de exceção não permite alterar Run nem registrar `SUGGESTED`.
7. Após negação e limpeza, novo Run aceita uma sugestão válida e transição normal fora do callback.
8. Leitura do estado do Run pelo provedor continua válida, com saída fixa sintética.

**Gate:** 648 testes PHP, lint de 83 arquivos PHP e verificadores Python offline. Requer CI verde no SHA exato do PR e depois do merge, a registrar separadamente em EVID-38.

## Limites e bloqueios de liberação

A guarda é uma barreira **somente no processo local PHP**, não uma sandbox de execução de código não confiável. O provedor injetado ainda é código PHP no mesmo processo e poderia procurar outros objetos/canais externos; portanto, **não** significa isolamento de segurança de um modelo ou adaptador real. Não cria IAM, ACL/RLS, auditoria assinada, isolamento multiempresa, token, API, credencial ou autenticação.

O [CRM PR #57](https://github.com/rafhael100/imagine-crm/pull/57) segue OPEN/DRAFT e 13 cenários operacionais OP-01 estão `NAO_EXECUTADO`. Gates R01-G01..G11 / CORE/SEG/AUT em NO-GO. `main`, VPS, produção, clientes, dados reais e gastos ficam intocados.

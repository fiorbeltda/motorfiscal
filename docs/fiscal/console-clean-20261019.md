# Console fiscal — reorganização de 19/10/2026

Entrega de manutenção concluída: organização, revisão dos registros e validação
técnica/visual (3/3). Não representa avanço percentual funcional da Fase4B.

- Visão geral: estado atual, marcos da entrega, duas entregas resumidas, duas
  pendências e próximo passo; aceite local separado da produção.
- Fases e objetivos: sete linhas de roadmap e oito grupos4B; cobertura,
  entregas e controles acessíveis sob demanda.
- Histórico e evidências: histórico integral pesquisável, registros detalhados
  e documentos recolhidos. Não houve exclusão de evidências.
- Refresh preserva área, detalhes e busca. Sem comandos fiscais, acesso ao
  banco ou escrita do observador; rotas e runtime fiscal não foram alterados.

28 checks do observador e 17 verificações de navegador passaram; telas desktop
e celular inspecionadas. Sem erros JavaScript ou transbordamento horizontal
nas resoluções testadas (1440 e390px). Ferramentas de navegação/desktop não
inicializaram por erro de assets; utilizado Edge headless local com Playwright.
A primeira asserção contava também os marcos recolhidos; corrigida para contar
apenas itens visíveis, sem mudar a interface para contornar o teste.

Evidências: `D:/ProjetoSaasExports/console-clean-20261019/qa-result.json`,
script `qa.cjs` e capturas `overview-desktop.png`, `objectives-desktop.png`,
`history-desktop.png`, `overview-mobile.png`, `objectives-mobile.png`.

Corte inicial da reorganização:108 aceito localmente com7.200 testes/107.350 verificações,
13.395 entradas inalteradas e83 hashes. Fases3/4A encerradas em Sandbox,
4B com oito grupos parciais,5–7 planejadas; produção bloqueada.110 isolado.
Próximo escopo: contrato próprio de preço/baseISS e qualificação jurídica111.

O diagnóstico111 da API existente foi executado sem mudar runtime ou entradas:
software sem dedução calcula1000/20 com alíquota2% **sintética**;
`allowed_deduction`10 ou0 recusa `DEDUCTION_NOT_SUPPORTED_FOR_SERVICE`.
Isso não demonstra suporte a desconto incondicional, nem alíquota aplicável.
Resultado em `D:/ProjetoSaasExports/phase4b-iss-base111-primary-research-20261019/existing-iss-base111-probe.json`.

Atualização16:13:os oito objetivos agora têm até três entregas atuais,
sem o acúmulo de relatos antigos e de pendências já superadas. Os textos
anteriores completos foram preservados em `history_reviews`. Visão geral
mantém dois concluídos e duas pendências;histórico/evidências continuam
separados. Nenhum estado de encerramento foi promovido pela simplificação.
TrêsJSONs atualizados,28 checks aprovados e endpoint conferido:integração112
em4/7 marcos (57%),3/4A encerradas,4B com oito grupos parciais e5–7 planejadas.
CorpusCLI112 ativo;108 permanece último aceite local,produção bloqueada.

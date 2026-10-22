# Importação129 — contribuinte, responsabilidade e fundamento

Retomada em22/10 após esclarecimento do usuário:prosseguir com o desenvolvimento.
A interpretação anterior de pausa foi incorreta. Alterações126–128 preservadas.
Bundle:`CONSUMPTION-PHASE4B-2026.10.22-129-draft1`,SANDBOX_CANDIDATE.
Esta entrega corrige uma lacuna da frente de importação;não encerra essa frente
nem substitui o objetivo de concluir todos os requisitos originais4B.

## Problema e comportamento

A rota anterior de importação apenas exigia fundamento não nulo e método
ACQUIRER/RECIPIENT. Um texto sem relação com a operação ultrapassava essa guarda.
O corte129 exige referência explícita compatível com o contribuinte:
adquirente brasileiro no art64§5ºV;destinatário brasileiro quando o adquirente
é estrangeiro,no incisoVI. As três grafias aceitas por hipótese são enumeradas
no contrato,sem busca por substring ou interpretação de texto livre.

`ImportResponsibility129` foi integrado após qualificação de incidência/local,
antes de prosseguir com cálculo e crédito. Registra contribuinte,importador,
rota declarada,fundamento,hash da fonte e responsabilidade solidária do
fornecedor estrangeiro sujeita aos arts21/23. A responsabilidade de plataforma
é preservada como decisão separada do componente existente,não como troca
automática do contribuinte. Não concede quitação,cadastro autenticado ou crédito.

Identificadores conflitantes,residências incompatíveis para a mesma parte,
contribuinte explicitamente divergente e fundamento de outro papel são
recusados. Fundamento desconhecido ou em branco exige correção do fato.
O fundamento doméstico do art36 não substitui o fundamento da importação.

A nova versão exige a política129 e todas as herdadas128/127/126/122/119,
mesmo com seus marcadores removidos. Bundles históricos não recebem a nova
guarda. Um testemunho128 preserva a passagem do fundamento genérico;129 passa
a recusá-lo,sem reescrever o resultado histórico.

## Fonte e limites temporais

Fonte primária:[LC214 compilada](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm),
art64,especialmente§5ºV,VI,VIII eIX. Pesquisa31 revisada foi lida como
especificação;as qualificações de importação de cada tributo permanecem separadas.

Original preservado:
`resources/fiscal/sources/official/import129-lc214-compilado-observed20261022.html`.
4.604.674bytes;SHA256
`027797fa2a1f5ce9ebe34c7236766be4ef850127beb510eedb3b8315cf18d6ed`.
Captura completa:`2026-10-22T16:40:39.031505+00:00`.
O texto normalizado do art64 coincide integralmente com o original observado119;
o novo arquivo bruto tem hash próprio e não substitui a captura histórica.
A observação controla disponibilidade de conhecimento129;não inventa vigência
jurídica nem aprovação de produção. Alíquotas,cálculo e bases não foram alterados.

Proveniência/bytes e trecho revisado:
`D:/ProjetoSaasExports/phase4b-import129-20261022-v1/primary-source-v2/`
e`preparation-v1/`. A política tem hash canônico
`a4612d5398292f5cd946388027d0fc970e78a31e4bb1562e8e0a04bae578ab2a`.

## Verificações observadas

- 318 testes dirigidos/1220 verificações,exit0,incluindo integração129,
 guardas herdadas,plataforma,crédito122 e projeções126–128.
- PHPStan geral sem erros;Pint geral aprovado em626 arquivos. Primeira checagem
 Pint recusou alinhamentoPHPDoc no fixture;correção apenas de apresentação,
 logs anteriores preservados. Não foi repetida a suíte ampla por formatação.
- 28 snapshots nativos/28 replays exatos:14 atuais129 e14 comparações128,
 sete cenários nos dois executores. Fatos e datas literais coincidem entre
 versões;os traces anteriores à guarda são comparados integralmente.
- Nas três hipóteses com responsabilidade qualificada,o assessment inteiro
 permanece igual após remover somente as duas novas etapas de trace.
 IBS nominal1.00/CBS9.00 preservados nos testemunhos sintéticos de1000.00.
 As demais hipóteses verificam recusas antecipadas deliberadas.
- 36 replays históricos exatos:12 snapshots122 e24 snapshots127/128 preservados.
 Não é nova execução dos1441 históricos ou da regressão integral122.
- Auditoria322 conferências,103 hashes de runtime atuais;cinco alterações
 herdadas declaradas,demais97 hashes128 intactos e um componente novo.
- 107 fontes conferidas;130 bloqueios anteriores mantidos,ready=false e
 eligible_for_local_validation=true. Nenhum parâmetro futuro foi inventado.

Recibo compacto:
`storage/app/phase4b-import129-development-20261022/import129-scoped-validation.json`.
Logs,corpus e inventários noD:,raiz`phase4b-import129-20261022-v1`.

## Continuidade obrigatória

A rota ainda retorna`RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN` depois das
qualificações atualmente implementadas.129 não cria XML de importação,não
conclui obrigação documental,dispensa de recolhimento,comprovação de extinção
ou efeitos de crédito. O registro de responsabilidade é consumível pelo
próximo desenvolvimento,que deve completar o fluxo documental real,com
emitente tomador,fornecedor estrangeiro,COMEX,NBS e bases/ISS por documento.
Conferir as regras documentais e municipais aplicáveis;XSD-válido isoladamente
não resolve a obrigação. Manter também a exportação parcial no escopo4B.

Matriz80 recebe três atualizações de sujeitos,sem encerrar requisito ou grupo.
Fases3/4A encerradas emSandbox;4B parcial,0/8 grupos encerrados;5–7 planejadas.
122 permanece último aceite integral. Não houve assinatura,transmissão,
recolhimento,commit oupush.

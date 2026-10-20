# Fase 4A — retenções federais

## Fechamento funcional para Sandbox — 24/09/2026

O desenvolvimento funcional da Fase 4A está implementado no bundle `FEDERAL-PHASE4A-2026.09.24-4`, com estado `SANDBOX_CANDIDATE`. Os bundles anteriores continuam reproduzíveis. A publicação em produção continua bloqueada por validação temporal e atos locais, sem bloquear o uso auditável em Sandbox.

A Administração Pública tem Anexo I completo com nove linhas versionadas, pagamentos parciais, glosa, juros, atividades mistas, limite público estritamente inferior a R$ 10, exceções individuais por tributo, Simples documentado e separação de IR local e contribuições federais por convênio do art. 33. Entidades subnacionais não abrangidas pelo art. 2º-A, como empresas públicas e fundações sem qualificação, entram no perfil privado após prova da natureza jurídica. A qualificação de fundação exige natureza autárquica ou mais de 50% da receita do mantenedor, com documento de origem. A visão multivigente do sistema Normas, espelhada por órgão governamental em 17/03/2026, lista as alterações até a IN RFB 2.257/2025; o PDF do Anexo I permanece como artefato separado. Uma alteração posterior a março de 2026 ainda deve ser descartada antes de aprovação em produção.

Cooperativas como tomadoras usam as regras privadas. Como prestadoras, cooperativas de trabalho e médicas exigem segregação de faturas, componentes, beneficiários e documentos; o art. 26/27 público separa serviço pessoal de cooperado PF, PJ cooperada ou terceira, material/hospital qualificado, taxa e prestador PF não cooperado. Reembolsos públicos são classificados pelo serviço subjacente documentado. O IR progressivo do prestador PF não cooperado mantém seu próprio tratamento e não entra no limite de R$ 10 da retenção pública de PJ. O perfil médico privado diferencia operadora de plano preestabelecido ou pós-estabelecido de cooperativa singular intermediária, aloca retenções da PJ beneficiária pelo CNPJ e impede exclusão de material ou reembolso sem fundamento. Ato cooperativo e base privada de PCC são conciliados por linha, documento e fundamento. Produção de bens classificada não recebe retenção privada de serviços. Transporte de carga e passageiros usa bases próprias do IR 3280. A retenção previdenciária de 15% sobre nota de cooperativa não foi restaurada; obrigações do cooperado ficam em perfil separado.

Construção previdenciária exige qualificação da empreitada total, escopo integral, contrato e obra principal na subempreitada, CNO, titular legal e fundamento de dispensa. Classificações de ISS e INSS continuam independentes. Empreitada total distingue solidariedade de retenção facultativa; empreitada parcial e subempreitada usam o responsável pela obra documentado.

O projetor versionado emite itens auditáveis R-2010, R-2020, R-4010, R-4020, R-4080, eSocial S-1210 e DCTFWeb, por competência, matriz, beneficiário e tributo, sem transmissão. Pagamento público a beneficiário no exterior segue o art. 35, identifica o contribuinte estrangeiro e, no código 0422, projeta recolhimento diário conforme a agenda oficial da RFB. O calendário nacional de 2026 diferencia o dia 10 do IR público 3280, dia 15 antecipado de EFD-Reinf, dia 15 prorrogado do S-1210, dia 20 de arrecadação e último dia útil de DCTFWeb. Fora da cobertura temporal, datas permanecem abertas. IR local exige ato, código, vigência e calendário local. A liquidação do empenho não integrante do Siafi é fato próprio do recolhimento previdenciário e da competência do R-2010.

O corpus v4 contém cenários e resultados esperados para plano médico misto privado, cooperativa pública com serviço pessoal, terceiro com materiais e taxa, e pagamento público de serviço técnico a beneficiário estrangeiro. A suíte anterior cobre 2026/2027, R$ 10, PF, exterior, CPRB e a transição em 2028; os testes v4 cobrem segregação, evidências, titularidade, vencimentos e bloqueios de 2027. No fechamento, 123 testes e 444 asserções passaram; PHPStan, Pint, `git diff --check`, os três cenários do corpus v4 e a verificação de integridade dos 14 artefatos oficiais também passaram. O histórico de cortes abaixo explica a construção do bundle e suas limitações à época; não descreve pendências funcionais atuais.

Condições para publicação: conferir no sistema Normas se houve alteração posterior a 17/03/2026 do Anexo I ou dos dispositivos aplicados; aprovar formalmente cada versão legal e calendário; obter os atos locais e convênios usados em cada operação. O motor retorna `ACTION_REQUIRED` quando falta esse documento ou quando uma hipótese exige classificação jurídica específica, e não reutiliza uma alíquota por aproximação.

Reprodução do corpus deste corte: `php artisan fiscal:corpus:run resources/fiscal/scenarios/federal-phase4a-v4 --expected=resources/fiscal/expected/federal-phase4a-v4 --bundle-manifest=bundles/federal-phase4a-2026.09.24-4.json`.

Data de corte: 17/09/2026.

## Corte de desenvolvimento de 24/09/2026

### Correção após releitura das pesquisas definitivas

As Pesquisas 22, 24, 25 e 26 já especificam os prazos previdenciários, a distinção entre Siafi e liquidação do empenho, os perfis de empreitada e a segregação de componentes de cooperativas. A redação anterior tratava parte desse conteúdo como informação ausente; isso estava incorreto. O bundle `FEDERAL-PHASE4A-2026.09.24-3` mantém as versões anteriores e incorpora a projeção do recolhimento federal público pela IN RFB 1.234/2012 e do INSS conforme o perfil do pagador. A projeção separa vencimento de EFD-Reinf/DCTFWeb de vencimento do tributo; para ente público não integrante do Siafi exige a data da liquidação do empenho. Conforme a FAQ oficial da EFD-Reinf, quando a liquidação ocorre em competência posterior à emissão, o R-2010 usa a competência da liquidação e preenche `dtEmissaoNF` com essa data; o R-2020 da prestadora mantém sua competência própria. O calendário bancário nacional de 2026 resolve o dia 20 antecipado quando necessário. O cenário `public-general-collection-2026.json` demonstra a separação das datas.

Continuam como dados de cada operação o convênio efetivo, CNO e titular, segregação comprovada da nota de cooperativa e data de liquidação do empenho. A revisão da cadeia temporal do Anexo I e de alterações posteriores é uma checagem normativa, não uma lacuna das pesquisas. Os perfis detalhados documentados nas Pesquisas 24 e 26 ainda precisam ser convertidos em regras executáveis e cenários antes de declarar a Fase 4A encerrada.

O bundle adicional `FEDERAL-PHASE4A-2026.09.24-2` preserva o bundle anterior e incorpora um catálogo próprio para o Anexo I da IN RFB 1.234/2012. O PDF oficial do anexo foi preservado em `resources/fiscal/sources/official` com SHA-256 e nove linhas completas. O catálogo é `SANDBOX_CANDIDATE`, pois o arquivo disponibilizado pela Receita não prova sozinho toda a cadeia de alterações e vigência até 2026. O DOU da IN RFB 2.239/2024 foi preservado na mesma pasta (SHA-256 `974a3e583c34bc43e183a48e978533dff2bfa924136fd5b6bafcf7dc4dc2ccdd`). Ela restringe as fundações subnacionais do art. 2º-A às de natureza autárquica ou com mais da metade das receitas oriundas do poder público mantenedor; o perfil fica bloqueado até que esses dados e suas provas sejam modelados. A IN RFB 2.257/2025 alterou o art. 34 para aluguel de imóvel pertencente a FII. A validação temporal integral e a representação dessa hipótese continuam pendentes. O catálogo não está autorizado para publicação em produção.

`PublicAdministrationRuleSet` separa a retenção federal integral da retenção de IR dos entes subnacionais. PIS, Cofins e CSLL de Estado, DF e Município exigem convênio comprovado do art. 33, com identificador e período de vigência; sem convênio, essas contribuições não são presumidas. A decisão indica o destino local do IR, sem inventar código de arrecadação municipal. O limite público é estritamente inferior a R$ 10,00; R$ 10,00 é retido. O cálculo usa o valor deste pagamento, inclusive parcial ou adiantamento. Na glosa sem nova nota, a base é o valor original da nota; juros e multas por atraso documentados entram na base. Esses casos exigem conciliação exata de nota, glosa, acréscimos e pagamento. Pagamentos de contratos com várias atividades exigem linhas individualizadas com categoria, valor e documento; cada linha é calculada separadamente e o total deve conciliar com a base de retenção. Beneficiário e componentes com exceções ainda não modeladas são bloqueados explicitamente. As regras de PIS/Cofins a partir de 2027 permanecem bloqueadas até nova tabela validada.

O perfil previdenciário de construção agora distingue obra e serviço isolado, registra CNO, titular e dispensa fundamentada, sem usar o CNO como gatilho de retenção. Cooperativa como tomadora entra no escopo das regras privadas de IRRF e PCC. Para cooperativa de trabalho prestadora, PIS/Cofins exigem base segregada e comprovada, enquanto a CSLL é excluída do art. 30 pelo art. 32, I, da Lei 10.833/2003. O INSS não recria os 15% invalidados pelo STF nem aplica automaticamente 11% sobre a nota; a contribuição do cooperado permanece perfil separado. Cooperativas médicas e demais tipos sem separação de serviços pessoais, hospitais, terceiros e repasses retornam `ACTION_REQUIRED` para IRRF/PCC.

O catálogo `federal-obligations-2026-sandbox.json` projeta R-2010, R-2020, R-4010, R-4020, R-4080, eSocial e DCTFWeb conforme o papel e o evento tributário, sem transmitir. A projeção separa a matriz responsável do pagador da matriz do beneficiário autorretentor, o período e o código de receita. R-4020 com mesmo código COSIRF é consolidado em um item, com componentes por tributo; contratos mistos preservam códigos por linha. O calendário bancário nacional de 2026, versionado a partir da Febraban, calcula as datas de EFD-Reinf e DCTFWeb cobertas pelo ano. No bundle `-3`, também calcula o recolhimento público federal e previdenciário dentro da cobertura de 2026. Para eventos fora da cobertura, eSocial e recolhimentos privados específicos, o vencimento permanece pendente. O calendário não cobre feriados locais.

Quando existe retenção previdenciária em obra, o perfil resolvido do CNO e de seu titular é transportado para a projeção R-2010. A projeção R-2020 exige a identificação da matriz da prestadora. O código da retenção de INSS fica explicitamente pendente de derivação pela DCTFWeb a partir do R-2010.

Validações deste corte: sintaxe PHP, carga do bundle no PHP 8.5 (`133` fatos, `127` perguntas), testes direcionados de IRRF privado, pessoa física, exterior, CPRB e fundações subnacionais. A suíte completa passou com 100 testes e 351 asserções, incluindo os 21 cenários integrados nos respectivos bundles. A nova captura do cenário público geral reproduziu o mesmo hash no replay. `git diff --check`, Pint em 239 arquivos e PHPStan sem ocorrências passaram.

As exceções individuais de PIS, Cofins e CSLL no regime público exigem fundamento legal e destaque na nota, preservam os tributos aplicáveis e usam os códigos diferenciados `6256`, `6228`, `6243` e `6230` quando cabíveis. Ainda falta para fechar a Fase 4A: confirmar a cadeia temporal vigente do Anexo I; cobrir as exceções do beneficiário e converter os demais perfis de cooperativas das pesquisas; completar os perfis de construção documentados; validar vencimentos privados específicos e calendários locais; ampliar o corpus federal privado e previdenciário. O bundle permanece candidato de Sandbox.

## Histórico do corte de 17/09/2026

As seções abaixo descrevem o bundle inicial `FEDERAL-PHASE4A-2026.09.17-1`; o corte de 24/09/2026 acima é o estado atual da etapa.

### Estado

O bundle `FEDERAL-PHASE4A-2026.09.17-1` está em desenvolvimento e permanece como `SANDBOX_CANDIDATE`. Ele reutiliza integralmente o ISS da Fase 3 e acrescenta um nó próprio de retenções federais antes da projeção da DPS.

As pesquisas definitivas 17 a 23 da pasta indicada para o projeto estão vinculadas no manifesto do bundle por nome, tamanho e SHA-256. Isso torna explícita a origem das decisões codificadas sem confundir material de pesquisa com ato oficial aprovado para produção.

### Implementado

- IRRF doméstico de PJ privada para PJ privada: serviços profissionais, limpeza/conservação, segurança/vigilância, locação de mão de obra, gestão de crédito, comissão/corretagem, publicidade, cooperativa de serviços pessoais e programação de software;
- evento pelo primeiro pagamento ou crédito contábil incondicional;
- bases especiais, autorretenção, dispensa quando o imposto é igual ou inferior a R$ 10 e códigos de receita por categoria;
- dispensa documentada do beneficiário Simples/MEI e não incidência da licença doméstica de software sem programação;
- IRRF de pessoa física pela tabela progressiva mensal de 2026, incluindo a redução mensal, código `0588` e roteamento entre eSocial e R-4010;
- IRRF sobre remessas ao exterior para serviços gerais, técnicos/administrativos, software sob encomenda e licença de software, com 15%/25%, jurisdição favorecida, validação explícita de tratado e código de receita versionável informado;
- PCC com PIS, Cofins e CSLL calculados separadamente, agregação apenas quando cabível, exceção individual com fundamento obrigatório, limite de R$ 10 e códigos `5952`, `5987`, `5960` e `5979`;
- transição em 01/01/2027: o fluxo do art. 30 permanece somente com CSLL de 1%;
- INSS com listas exaustivas dos arts. 111 e 112, classificação de cessão de mão de obra, empreitada e construção;
- valor bruto próprio da nota/fatura, separado do valor do evento de pagamento;
- base previdenciária com materiais/equipamentos documentados, percentuais mínimos legais, outras deduções comprovadas e abatimento de subcontratada;
- retenção de 11%, CPRB de 3,5% entre 2025 e 2027, adicionais de aposentadoria especial e empreitada total facultativa;
- dispensas legais documentadas para serviço pessoal do titular/sócio sem empregados e para profissão regulamentada ou treinamento;
- tratamento de Anexo IV do Simples, MEI, entidade beneficente qualificada, cooperativa e perfis fora do escopo empresarial doméstico;
- metadados de período, vencimento e eventos R-2010/R-2020 para INSS e R-4020/DCTFWeb para PCC;
- total federal e valor líquido, com bloqueio se a retenção total superar o valor bruto.

### Comportamento conservador

O motor não reutiliza a tabela de PJ privada em pagamentos públicos. A Administração Pública retorna `ACTION_REQUIRED` até receber o catálogo completo da IN RFB 1.234/2012. Cooperativas previdenciárias também permanecem em rota especializada.

A tabela progressiva de pessoa física foi deliberadamente limitada a eventos de 2026. Outro período bloqueia por ausência de versão, em vez de reaproveitar faixas vencidas. Remessas ao exterior exigem qualificação da renda, base, jurisdição, código vigente e validação do tratado quando invocado.

Isenção, alíquota zero ou suspensão individual de PIS, Cofins ou CSLL exige status explícito e fundamento legal. A ausência desses fatos não é convertida em alíquota padrão.

### Bundle

```bash
php artisan fiscal:scenario:run caminho/cenario.json \
  --bundle-manifest=resources/fiscal/bundles/federal-phase4a-2026.09.17-1.json
```

O bundle contém 100 definições de fatos e 94 perguntas após a composição do overlay federal. As regras declarativas de ISS são reutilizadas da Fase 3; a lógica federal especializada está nos motores tipados do módulo `FederalWithholding`, portanto não é publicado um segundo bundle de regras com hash idêntico no PostgreSQL.

### Próximos cortes da Fase 4A

1. tabela completa e versionada de retenções da Administração Pública para IR/PIS/Cofins/CSLL;
2. perfis especializados de cooperativas e construção previdenciária/CNO;
3. projeções completas de EFD-Reinf/DCTFWeb e calendários de vencimento versionados;
4. corpus federal e validação ampla, deliberadamente adiados para depois do desenvolvimento funcional.

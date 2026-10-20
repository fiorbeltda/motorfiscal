# DeRE — deduções documentadas D1121

## Candidato20 — vínculo documental obrigatório no fechamento

Candidato20: D1121 obrigatório411011105 reconciliado ao PGCC, lançamento e documento, com dedução financeira integral separada da parcela declarada. Inclui original, continuação, competência sem apropriação e captação isolada. Provas positivas sintéticas. Suíte860/2.752, PHPStan sem erros, Pint358, corpus19/19, quatro novos replays e18 corpos XML aprovados; regressões20/19/18/17 e1.925 históricos preservados. Gate42/42 fontes, elegível local, `ready=false`,119 bloqueios. Fase4B parcial e defaults preservados. [Aceitação e limites](phase4b-dere-d1121-pgcc-binding.md). O comando isolado16 mantém seu contrato; a seleção e reconciliação por PGCC são exigidas no mensal20. As medições seguintes são históricas.

## Histórico da implementação local em 2026-10-02

`DereDeductionQualifier` confere deduções documentadas das famílias financeiras192/193. Exige operação calculada, fornecedor/documento/competência vinculados, documentos e alocação previamente qualificados e histórico completo antes de aceitar uma continuação. Cada lançamento financeiro do grupo `DEDUCTION` precisa ser vinculado uma vez; receita e principal excluído não podem ser usados como dedução.

No mês de emissão, o documento declara `vDedTotal`, limitado a `vOper`. Em meses posteriores, o total não pode ser redeclarado: é exigida conta anterior inaugurada no mês de emissão, vinculada ao mesmo documento, fornecedor, tipo, atividade e valor, com histórico até a competência anterior. Acumulado mais dedução atual não pode ultrapassar o teto.

Detalhamento de itens é exigido nos casos previstos no leiaute para documentos01/03/04 parcialmente dedutíveis e para continuação de documentos com itens. São conferidos identificação dos itens, limites individuais, somas atuais e coerência dos totais históricos por item com a conta do documento.

`DereDeductionXmlProjector`, contrato `DERE-DEDUCTIONS-LOCAL-2026.10.02-1`, repete a qualificação e projeta inclusão original em ambiente restrito. Os grupos `itemDFe` são irmãos de `detDeducao`, conforme o XSD. Não cria assinatura, recibo ou transmissão. Projeções de registro inicial e continuação foram conferidas contra `evtRelDeducoes-v0_0_1.xsd` original: somente assinatura ausente, `RTC_DERE_UNSIGNED_LOCAL_CANDIDATE`, `production_ready=false`.

## Evidência dirigida

`tests/Fiscal/Unit/DereDeductionQualifierTest.php`:23 testes/45 asserções. Os casos executam o cenário financeiro de empréstimo do candidato15 e ligam sua dedução de captação200.00 a uma fixture documental. Cobrem registro inicial, continuação, XML/XSD e rejeição de documentos repetidos, mês futuro, histórico ausente ou divergente, excedentes, itens, vínculos e período já fechado. Pint aplicado; análise estática dos componentes conferida separadamente.

Fontes: arquivo de leiautes1.2.0 SHA-256 `7adbc82a55f0d60dfc2a63771a4c65c67d479e6a50a7821079e73f38b21873c7`, regras de validação SHA-256 `b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb`, XSD ZIP SHA-256 `c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818`. Identificam bytes preservados, sem provar aprovação formal ou calendário obrigatório de produção.

## Candidato16: captura e replay

O bundle `CONSUMPTION-PHASE4B-2026.10.02-16` vincula `dere_deduction_projection`, fontes e controles. O comando `fiscal:dere:project-deductions {snapshot} {deductions}` confere o bundle selecionado, reproduz o resultado integral do snapshot, exige a etapa financeira de documento pendente, requalifica as deduções e valida o corpo no XSD original. A entrada documental e a identidade são vinculadas ao relatório por hash; candidato e relatório existentes não são sobrescritos.

`scripts/fork_phase4b_dere_deduction_bundle.py` materializa16 sem alterar predecessores. `scripts/verify_phase4b_dere_deduction_cut.py` preserva snapshot e dois candidatos/relatórios em `storage/app/phase4b-dere-deductions-20261002-16-*`, repete as projeções e confere campos e hashes. SHA-256 dos XMLs: inicial `e76f7ae1a1b2521f976f09344b65e062ed230f3db7e9acc6f9bbc4e234d2de25`; continuação `0a9177c84766d5cc28d33a7be2873e9d153dfa4a03a4839200aafce1318b4751`.

Teste do comando4/15, somado ao qualificador/projetor23/45:27 testes/60 asserções dirigidos. PHPStan do comando passou; Pint aplicado. Regressão16:15/15 casos RTC parciais,3/3 casos gerais integrados,8 replays herdados e3 candidatos DPS ainda com o conflito oficial de série. Nos gerais: IBS1.00, CBS9.00, ISS20.00, 4A61.50, conforme fixtures. Esses casos não demonstram integração final das famílias financeiras específicas.

Gate16 conferido com controles16:32/32 hashes oficiais íntegros, elegível para validação local, `ready=false`,119 bloqueios de produção. O materializador foi repetido sem divergências. O padrão geral dos comandos anteriores continua14; o D1101 mantém padrão15 e o novo D1121 utiliza16.

## Pendências

Ainda falta captura/replay do conjunto mensal completo e fechamento. Os documentos e históricos usados nos testes são sintéticos; os indicadores de qualificação não comprovam veracidade, assinatura, autorização ou existência externa do documento. Falta validar chaves conforme cada espécie documental e integrar consulta/verificação de origem. Não implementados imóvel sem DF-e, demais atividades02–07, estornos cooperativos, divisão de um lançamento entre documentos, alteração/exclusão/retificação pós-fechamento, D1198/D1199 e homologação mensal completa. A Fase4B continua aberta.

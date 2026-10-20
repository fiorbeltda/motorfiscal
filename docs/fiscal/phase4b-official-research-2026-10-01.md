# Fontes oficiais observadas em 01/10/2026

Inventário deste corte: `resources/fiscal/sources/official/phase4b-manifest-2026.10.01-6.json`, com 18 fontes de hashes conferidos. O candidato de 02/10 conserva as mesmas fontes em `phase4b-manifest-2026.10.02-1.json`. O inventário do corte `-2` conserva suas 17 fontes e disponibilidade `2026-10-01T16:57:14+00:00`. As fontes históricas permanecem preservadas.

## Conferência de créditos em 02/10/2026

A íntegra oficial da LC 214 foi novamente consultada para os arts. 47 a 52. O corte `2026.10.02-1` incorpora a vedação do art.49 e o limite ao principal extinto do art.50, com tratamento documental da aquisição separado por IBS/CBS. O arquivo integral observado em 01/10 e seu hash permanecem a fonte incorporada, sem criar alíquota ou benefício novo. A anulação proporcional do art.51 e a preservação do art.52 pertencem à apuração do fornecedor e não estão implementadas por esta qualificação do adquirente.

## Educação, saúde e local de prestação

A mesma íntegra da [LC 214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), com hash `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`, forneceu os arts. 11, III, 129/130 e 348 e os anexos II/III. A extração determinística preserva 9 linhas de educação e 30 de saúde, inclusive os prefixos NBS oficiais, códigos repetidos por descrição e o item de educação especial sem código NBS preenchido. Os qualificadores exigem provas da operação; não convertem uma linha de anexo em decisão positiva automática. As classes 200028/200029 e os indicadores de local são conferidos nos workbooks já preservados. As 80 regras que relacionam serviço, local e tratamento são interpretações internas, marcadas `SANDBOX_REVIEW_REQUIRED`.

## Redução profissional

A [LC 214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html) foi obtida integralmente em HTML, preservada como `lc214-camara-observed-20261001.html`, SHA-256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`. Os arts. 127 e 348, III, a, fundamentam a elegibilidade e aplicação da redução de 30% às taxas de teste; o art. 344 mantém a redução proporcional do IBS em 2027–2028. A nova regra só está disponível após a observação vinculada ao catálogo. A classe oficial 200052 já consta no workbook preservado `nfe-cclasstrib-it2025.002-v1.60-20260623.xlsx`. Sua indicação não dispensa os requisitos da operação.

## Documentação NFS-e

A [página RTC](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc) e a [documentação de produção](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual) foram verificadas. A listagem mantém o pacote XSD v1.01 de 09/02/2026, SHA-256 `e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072`. A listagem atual não comprova a primeira data de implantação. A implantação da NT009 permanece não comprovada no catálogo.

O [manual de contribuintes v1.2](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/manual-contribuintes-emissor-publico-api-sistema-nacional-nfs-e-v1-2-out2025.pdf) foi preservado em `nfse-manual-contribuintes-api-v1.2-observed-20261001.pdf`, SHA-256 `ac2f36e34ff565cc36d67c5d67415c33cc09f27b2dea006e8da9bcd2ddce5581`. Orienta os componentes do identificador DPS e distingue schema das validações de negócio e municipais. Não é fonte de alíquotas.

## Cronograma documental

A [orientação oficial da RFB](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/orientacoes-da-reforma-tributaria), publicada em 18/09 e atualizada em 21/09/2026, foi preservada em `rfb-rtc-obrigacoes-observed-20261001.html`, SHA-256 `1ec6502ba7247c26d7034544b777f8154b9f11638dc79a65ebd3006e3d9026f1`.

O catálogo registra obrigatoriedade geral de campos RTC na NFS-e em 01/10/2026 e a dos subitens 1.03, 1.05, 1.09 e 16.01 em 01/12/2026. A fonte é orientação oficial, com `full_act_review_required=true`; a íntegra do ato conjunto citado ainda exige revisão. Essa regra trata o cronograma de declaração RTC, sem resolver a obrigação municipal de emissão ou provar implantação de layout.

## Supersessão dos prazos do Simples

A [notícia oficial do Ministério da Fazenda](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/setembro/simples-nacional-2027-tem-novos-prazos-para-microempresas-e-empresas-de-pequeno-porte/), publicada em 29/09/2026, foi preservada em `mf-simples-prazos-2027-observed-20261001.html`, SHA-256 `bd0b7a81f841fcdcdda3ed9dcd4401144a7750e6b8dc11da0d3141434e4d7c1c`. Anuncia a CGSN 194 e alteração dos prazos para o primeiro semestre de 2027.

O [ato vinculado no DOU](https://www.in.gov.br/web/dou/-/resolucao-cgsn-n-194-de-25-de-setembro-de-2026-735097948) retornou HTTP 403. A consulta alternativa de normas também não disponibilizou a íntegra. Não há hash de ato integral nem ativação dos prazos anunciados. O novo catálogo conserva as janelas históricas para replay e suspende sua qualificação positiva após a observação do aviso: `RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED`. Trata-se de proteção contra norma potencialmente substituída, não de implementação presumida do ato inacessível.

## Observação de 02/10 — créditos do fornecedor e imunidade institucional

A íntegra preservada da [LC214 consolidada](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html) fundamenta a periodicidade, anulação/manutenção e prazo de utilização dos créditos nos arts.43,47 §10,51,52 e54. O corte `-3` confere a proporção por tributo sobre livro qualificado; não homologa automaticamente as classificações de suas saídas nem calcula saldo consolidado.

Foi preservada a íntegra do [CTN consolidado na Câmara](https://www2.camara.leg.br/legin/fed/lei/1960-1969/lei-5172-25-outubro-1966-358971-normaatualizada-pl.html), `ctn-camara-observed-20261002.html`, SHA256 `dc32f69ddba5b4c0cdd7952091c74d755c1e0e699a444aa3566819b94422b650`. O art.14 é a referência cumulativa da LC214 art.9 §3 para a imunidade das instituições do incisoIII. Os §§2/4 da LC214 distinguem requisitos religiosos e fornecimentos das aquisições. As classes410006/410007 vêm do catálogo oficial já preservado. O corte `-4` vincula a fonte a19 artefatos íntegros; quatro novas regras educacionais são interpretações candidatas, sem aprovação jurídica. A consulta ao Planalto foi lida no navegador, mas a obtenção direta expirou; a fonte integral vinculada por hash é a Câmara.

## Pendências externas mantidas

### Cooperativas em02/10

Complemento10: a leitura da íntegra preservada confirma art271 §1 I para operações entre cooperativas singulares, centrais, federações/confederações e originadas dos respectivos bancos cooperativos participantes. Art272 e seu parágrafo limitam a proposta de transferência aos créditos apropriados pelo associado regular, incluindo presumidos previamente qualificados, de insumos vinculados à produção do fornecimento à cooperativa. O contrato preserva data e prazo54. Não se obteve ato regulamentar novo; exige prova de validação regulamentar da operação e não efetiva transferência. Financeiro188/197 e cooperativas agropecuárias continuam pendentes. Contrato: `phase4b-cooperative-network-credit-transfer.md`. O parágrafo seguinte é histórico do9.

A íntegra preservada da LC214 art271 I/II e §3 fundamenta as duas rotas de serviços não financeiros no corte9. Os efeitos temporais da opção e sua validação regulamentar são fatos documentados da operação; não se inventou calendário ou ato de registro. O catálogo oficial preservado contém CST200/classe200020. Arts49/52 distinguem o crédito da aquisição e o anterior do fornecedor. Os demais perfis271/272 e financeiro188/197 não estão cobertos por esse qualificador. Contrato: `phase4b-cooperative-zero-rate.md`.

### Consolidação mensal em02/10

A LC214 preservada fundamenta o cálculo separado de IBS/CBS e a consolidação de todos os estabelecimentos (42–45), prioridade de uso dos créditos (53), segregação (47) e prazo de utilização (54). O corte `-7` aplica essas operações a livro completo previamente qualificado; não qualifica automaticamente regimes presumidos/específicos ou ajustes regulamentares. Não materializa entrega, apuração assistida ou confissão de dívida. Vencimentos e prazos regulamentares permanecem sem valor. Contrato: `phase4b-monthly-assessment.md`.

### Constituição e ISS institucional em02/10

Íntegra da [Constituição consolidada na Câmara](https://www2.camara.leg.br/legin/fed/consti/1988/constituicao-1988-5-outubro-1988-322142-normaatualizada-pl.html) preservada em `cf1988-camara-observed-20261002.html`, SHA256 `654bbf1797b5624339021f53d5a8f612c917135f0c23a232c9bc39ce86828744`. CF150 VI b/c e §4 fundamentam a exigência independente das finalidades essenciais na política de ISS. O CTN9/14 mantém o hash já registrado. O inventário do `-6` tem20 fontes; essas políticas continuam interpretações candidatas.

A leitura do XSD oficial v1.01 preservado confirmou `tribISSQN=2` para imunidade e `tpImunidade=2/3` para as categorias materializadas. Não se transplantou a codificação da versão1.00. A qualificação e os XMLs do novo contrato estão em `phase4b-integrated-institutional-immunity.md`; o conflito de série permanece.

Nenhuma alíquota futura, redutor de compras públicas, implantação de split, aprovação jurídica ou ato municipal foi presumido. A origem, versão, obtenção, disponibilidade e limites das novas fontes estão no inventário e nos catálogos. As implementações restantes continuam necessárias independentemente dessas pendências.

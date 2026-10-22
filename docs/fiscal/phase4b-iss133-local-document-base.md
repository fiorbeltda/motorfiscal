# ISS133 — base e imposto locais por documento

Em22/10/2026 foi implementado `IssDocumentBase133`, com o comando headless
`fiscal:iss:document-base {input}`. O componente reexecuta111 e106 sobre as
mesmas provas e fatos correntes; não aceita um resultado anterior como prova.
Calcula separadamente cada documento do par de exportação parcial. Nenhum
componente histórico ou bundle fiscal foi alterado; a integração ao fluxo
versionado/DPS é o próximo trabalho, e não uma entrega concluída neste corte.

A entrada contém `input111` (`input110`, `records`, `proof`), duas `proofs`
tributárias por documento, `knowledge_at` e a política exata133. A política
está incorporada às entradas preservadas em `native-v2` noD:. O comando confere
os cinco arquivos municipais originais antes de executar. Sua saídaJSON contém
preço/composição, tratamentoISS, base, alíquota, imposto e hashes por documento.
Não há banco, frontend, assinatura ou transmissão nesse percurso.

## Cadeia de fonte e aplicação

Foram capturados cinco originais em22/10 entre18:19:20 e18:19:21UTC, comURL,
resposta, bytes eSHA256 preservados em
`D:/ProjetoSaasExports/phase4b-iss133-20261022-v1/primary-v1/capture.json`:
Lei13701 consolidada, Decreto53151 consolidado, IN8/2011 consolidada,
Anexo1 atualizado comIN3/2026 e Lei18066/2023 consolidada. Cópias byte-idênticas
estão em `resources/fiscal/sources/official/iss133-*`. São cinco fontes
acompanhantes do componente; não foram incorporadas retroativamente às107
fontes dos bundles129/131.

A página9 doAnexo1, conferida por extração e renderização, distingue2660/1.01,
análise e desenvolvimento de sistemasPJ, de2692/1.04, elaboração de programas.
A linha selecionada registra2,90% e preço do serviço. A nota23, página35 também
renderizada, registra início do código em13/02/2018. O contrato usa `02660`
como código municipal de cinco posições e mantém `2660` como grafia da fonte.
Não converte família1 em classificação granular ou associa1.04 à linha1.01.

A Lei13701, art14 caput/§4, mantém preço bruto menos descontos incondicionais
e oISS integrante do preço; o art16III dá suporte à alíquota selecionada.
O Decreto53151 art17 integra a cadeia de preço. Esta revisão delimita a
aplicação ao período material de2026 até22/10, PJ privada em regime geralISS,
serviço canônicoSOFTWARE_DEVELOPMENT, subitem01.01/cTribNac010101 eSãoPaulo.
A disponibilidade da política é22/10 às18:19:22UTC; não retrocede a observação
nem redefine como2026 a vigência histórica do código. Não é revisão exaustiva
de todas as normas, benefícios ou períodos municipais.

Além das provas105–111, cada documento exige prova de classificação/regime e
contexto tributário com vínculo a contrato, unidade, linha, partes, município,
dataISS e hashes frescos106/111. O contexto declara tributação ordinária sem
benefício ou regra especial de base, comISS incluído. Esse contexto não é
deduzido apenas de um resultadoBR. Revogação, supersessão, cobertura temporal,
observação anterior ao preço vinculado ou posterior ao conhecimento impedem
o cálculo. Reenvelopar uma prova conflitante com novos hashes não a aprova.

## Comportamento e limites

O tratamento segue o resultado municipal fresco106/97, inclusive quando os
papéisRTC sugerem o contrário. Candidato local à não incidência recebe base,
alíquota e imposto nulos. Documento tributável de preçoBRL recebe a base
composta111 e imposto a2,90%, sem deduzir novamenteISS, desconto condicionado
ou usar parcela de consumoRTC. Base monetária zero permanece diferente de
não incidência. O parâmetro sintético antigo2% não foi substituído globalmente.

A multiplicação usaDecimalValue, divisão exata na escala necessária e um único
arredondamento finalHalfUp para centavos. Esse é o contrato técnico local de
cálculo, com `municipal_rounding_approved=false`; não declara homologação da
regra de arredondamento pelo município. O controle4,31×2,90%=0,12499 produz0,12,
evitando o erro0,13 de um arredondamento intermediário. Preços estrangeiros de
documentos tributáveis exigem contrato cambialISS próprio e continuam recusados.

Os resultados são locais, com provas sintéticas explicitamente identificadas,
`legal_applicability_approved=false`, `government_evidence_authenticated=false`,
`production_ready=false` e obrigação documental incompleta. Não autenticam prova,
registro cadastral, pagamento ou emissão. Isso não impede desenvolver/testar a
integração local seguinte; tampouco transforma a ausência de prova em aprovação.

## Evidência observada

338 testes/2.599 asserções passaram, incluindo267 resultados históricos110/111
e a integração132. PHPStan sem erros;Pint648 arquivos. Capturados52 resultados
com replay exato entre componente puro e comandoCLI:42 pares calculados,
oito recusas por câmbioISS ausente e duas por provas anteriores não requalificadas
no conhecimento atual. Nesses dois controles, a geração de transporte fresco
encontrou provas de composição agora revogadas/supersedidas; o material antigo
foi preservado e sua reapresentação ao componente continuou recusada. Nenhum
hash foi atualizado para contornar esses eventos.

Um oráculoPythonDecimal independente conferiu84 documentos calculados a partir
de principal/acréscimos/descontos e do resultado municipal material. Inclui
inversão dos papéisRTC, zero tributável, valores acima da precisão monetária de
float e arredondamento final. Mais19 snapshots integrados131/132 tiveram replay
histórico exato. Os52 resultados133 são de componente/CLI, não52 decisões
integradas ou documentosXML. Não houve nova suíte integral neste corte.

Recibo: `storage/app/phase4b-iss133-development-20261022/iss133-scoped-validation.json`.
SHA256: `748c10c778f20ab857717c2ed6ae9cb7075ed9f86f0a7e4b8a359ad1eeca686b`.
Auditoria463 conferências,110 hashes correntes,107 fontes anteriores intactas
e cinco acompanhantes verificadas. A matriz conserva80 revisões próprias e
acrescenta evidência ao item4B.7.1. Permanecem pendentes integração versionada,
projeçãoDPS dos valores próprios, câmbioISS, importação e demais perfis.
0/8 grupos encerrados;122 permanece o último aceite integral.

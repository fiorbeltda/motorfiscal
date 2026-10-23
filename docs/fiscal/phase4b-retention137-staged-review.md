# 137 — retenções agregadas e tolerância de débitos próprios em preparação

Checkpoint solicitado em 2026-10-23T18:26:42.172423+00:00: desenvolvimento parado após auditoria escopada137. 41 testes/118 asserções;CLI real repetido,4 XML/XSD idênticos,PHPStan global0 ePint aprovados. 5.916 entradas preservadas no ensaio nativo e130 hashes de runtime auditados. Recibo `storage/app/phase4b-retention137-development-20261023/retention137-integration-scoped-validation.json`, SHA256 `2c5f1c7013451ddd7522bf3fd8b90964a70857ced1418e1023ef47c190ef6398`. Regressão integral137 não iniciada. Última integral136:9.569 testes/145.843 asserções. 0/8 grupos encerrados;produção bloqueada.

## Avanço documental em23/10,14:53 deSãoPaulo

Atualização às15:04 deSãoPaulo: composição externa aprovada e auditada,
com seis validações deXML completo/XSD e12 recusas adicionais. Os campos
próprios permanecem4,05/18,68,enquanto a retenção social é29,82. A troca
do pagador pelo adquirente estrangeiro elimina essa retenção sem modificar
os débitos próprios. Auditoria: `composition-audit-v1.json` noD:.

O originalNT007 foi obtido por doisGET anônimosPowerShell,HTTP200,377.947bytes,
SHA256 `24e46b39fcf73d40b8241147b2c0607159893fd5934f16f63f3103343972cf05`.
O segundoGET foi observado em2026-10-23T17:59:29.8420982UTC. As dez páginas
foram extraídas;as páginas4/6 foram renderizadas e inspecionadas visualmente,
confirmando a separação de débitos/retidos,a referênciahalf-even/tolerância
e a soma das contribuições retidas. Recibo `primary-review-v5.json`;
original copiado sem transformação para
`resources/fiscal/sources/official/retention137-nt007-v1-0.pdf`.

A captura supera a pendência de obtenção dos bytes, mas não requalifica os
resultados anteriores. O candidato `qualified-candidate-v2`, disponível a
partir de18:02:44UTC, fixa o novoSHA na política e exige os bytes em runtime.
Sua validação está em execução. Integração,CLI,qualidade e regressão continuam
pendentes. Os cortes abaixo são preservados como histórico da preparação.

A regressão integral136 terminou com9.569 testes/145.843 asserções e5.907
entradas preservadas;recibo `own136-full-validation.json` na pasta de
desenvolvimento136. O desenvolvimento137 prossegue exclusivamente noD:.

O coordenador `document-candidate-v4/DocumentFederalPayment137.php` reexecuta
o par135 e duas avaliações4A por documento. Exige capturas distintas comJSON
original,SHA,lifecycle,cobertura,contexto documental e dependências temporais.
Cada pagamento separado emBRL vincula banco,preço bruto,líquido,fornecedor e
pagador explicitamente declarado como adquirente ou destinatário nacional.
As matrizes dos contribuintes exigem evidência própria. Fatos adicionais não
podem reescrever partes,valores,eventos ou ativarRTC no ensaio4A.

O crédito contábil do pagador é declarado com data/evidência ou ausência
evidenciada. Um caso positivo manteveIRRF no crédito de22/10 ePCC no pagamento
de23/10. Não se preenche data ausente com a data de pagamento por conveniência.
O resultado completo auxiliar fica preservado, mas somente seu ramo de
retenções federais participa desta composição;ISS eRTC continuam vinculados
ao replay documental anterior.

Nos casos sintéticos400,00/641,33, o pagamento doméstico produzPIS4,17,
Cofins19,24 eCSLL6,41:retenção social29,82 e líquido611,51. OIRRF9,62
é dispensado pelo limite do perfil existente. Os débitos própriosPIS4,05 e
Cofins18,68 permanecem separados. Outro caso troca o pagador do documento
de consumo doméstico pelo adquirente estrangeiro:retenção zero,líquido641,33,
mesmos débitos próprios. País do pagador e papelRTC não concedem exoneração
automática das contribuições próprias.

O ensaio revelou uma lacuna anterior:as provas sintéticas135/136 podiam informar
paísBR para o cliente do documento de consumo doméstico, embora a identidade
do adquirente e seu paísUS fossem os mesmos nos dois documentos. O projetor135
aceita essa combinação. O candidato137 recusa explicitamente essa divergência
antes de usar as provas de pagamento;os novos ensaios reemitem as provas
sintéticas com paísUS sem alterar o tratamento ordinário da parcela doméstica.
As evidências históricas e o resultado136 continuam preservados. Uma regressão
aprovada não substitui essa verificação de coerência material.

Resultadov4:42 recusas,duas identidades e três cenários de pagamento/crédito.
AuditoriaPython independente verificou seis documentos,componentes monetários,
líquidos,capturas eSHA;124 hashes do runtime136 permaneceram intactos.
Recibo: `D:/ProjetoSaasExports/phase4b-retention137-20261023-v1/document-audit-v4.json`.
As provas são sintéticas;consistência/SHA não autenticam banco ou partes reais.

Composição dos campos próprios e retidos emXML está em validação no candidato
externo `composition-candidate-v1`. Não há classes137 instaladas na aplicação.
A captura originalNT007 continua pendente e impede qualificação para integração.

Preparação exclusivamente noD:, durante o congelamento da regressão136.
Nenhuma classe137 foi instalada na aplicação.0/8 grupos4B encerrados.

## Divergência observada

O projetor anterior `DpsFederalProjection` leva somente a parcelaCSLL ao campo
`vRetCSLL`. Seus testes históricos também esperam ausência desse campo quando
somentePIS/Cofins são retidos. O leiaute atual descreve retenções das contribuições
sociais conforme o indicador conjunto;E0724 exige o campo nos indicadores de
retenção distintos de0/2. Os códigos0 e3–9 discriminam os três componentes.

A leitura pública daNotaTécnica007 confirma, na página6, a soma dePIS,Cofins eCSLL
retidos nesse campo. A mesma nota separa os débitos próprios das retenções e,
na página4, informa referência half-even com tolerância de um centavo.
Fonte central consultada:
https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nt-007-se-cgnfse-v1-0.pdf

Os bytes do leiaute1.04.01 já estão preservados comSHA256
`103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39`.
Locadores: `LEIAUTE DPS_NFS-e - RT!H359:I362` e `RN DPS_NFS-e!D677:I678`.
A leitura doPDF no serviço de pesquisa não equivale à captura local dos bytes:
as tentativas anônimas de download central,do link público e de uma republicação
municipal receberamHTTP403. O navegador integrado também não inicializou por
falha de infraestrutura. Não foi atribuído hash fictício aoPDF nem alegada
conferência visual de páginas cujo arquivo local não foi obtido.

## Protótipos e evidência

`D:/ProjetoSaasExports/phase4b-retention137-20261023-v1` preserva os protótipos,
driversPHP, snapshots, resultados e auditoria. O candidato de retenções v2:

- Aceita somente avaliação federal resolvida,com as três decisõesPCC distintas,
  identidades explícitasIRRF/INSS e componentes finais.
- Recusa componentes pendentes,totais conflitantes,valores retidos inválidos,
  fatos faltantes e valores positivos que seriam descartados como não retidos.
- Agrega os componentes sociais principais e adicionais em `vRetCSLL`, mantendo
  IRRF/INSS separados. Não produz `vPis`/`vCofins` a partir de valores retidos.

Um cenário4A reexecutado de forma determinística gerou retençãoPCC46,50 eIRRF15,00.
O candidato calcula `vRetCSLL=46,50`;o projetor anterior informa10,00.
O cenário completo com conhecimento/entrada/resultado está preservado em
`native-v2/fresh-source-snapshot.json`. Foram conferidas oito combinações
sintéticas de retenção e24 controles negativos. Essas combinações verificam
o formatador; não concedem enquadramentos fiscais ao contribuinte.

O candidato de tolerância usa aritmética decimal e referência half-even.
Preserva o valor próprio informado quando a diferença está dentro de um centavo
e recusa divergência maior. Não ajusta o débito, o preço ou a baseRTC para passar
na validação. A não incidência mantém base/alíquota ausentes. Foram observados
dez casos positivos e doze recusas, incluindo empates de arredondamento.

Auditoria independentePythonDecimal:18 conferências, somando oito combinações
de retenção e dez referências de arredondamento. Recibo externo:
`D:/ProjetoSaasExports/phase4b-retention137-20261023-v1/draft-audit-v1.json`.
Os originais do candidato v1, a revisão v2 e os resultados de ambos permanecem
separados. O código136 e seus hashes não foram alterados por este ensaio.

## Trabalho ainda necessário

Preservar os bytes originais daNotaTécnica007 e sua proveniência; vincular cada
avaliação federal à identidade,partes e pagamento do documento;reexecutar os
perfis4A aplicáveis e compor contribuições próprias/retidas sem misturá-las.
Então integrar uma versão qualificada, validarXML/RN e compatibilidade dos
comandos históricos. Não alterar retroativamente snapshots ou testes aceitos
para ocultar a divergência do projetor anterior.

Todos os resultados137 conservam `eligible_for_integration=false` e
`production_ready=false`. Uma conferência aritmética de retenções não qualifica
o documento, a obrigação inteira ou os demais requisitos da matriz80.

# 142 — modelo de camposNT009 e recusa de schema incompatível

Validação local em27/10/2026: **54 testes/123 asserções**,sendo21 novos casos142
e33 casos141 preservados;PHPStan global0 ePint4. Não houve regressão integral142.
OCLI foi executado duas vezes com modelos idênticos e replay completo;identificador
alfanumérico e snapshot adulterado foram recusados em duas execuções adicionais.
Todos esses comandos retornamexit1;incompatibilidade documental não é sucessoXML.

Recibo e matriz80:
`storage/app/phase4b-import142-development-20261027/import142-integration-scoped-validation.json`
e `requirements80-current-matrix.json` na mesma pasta.
Evidências volumosas:
`D:/ProjetoSaasExports/phase4b-import-nt009142-20261027-v1`.
Entradas e runtime auditados:5.952 arquivos e138 hashes;os136 hashes141 e as
5.948 entradas anteriores permanecem idênticos. Fontes e históricos preservados.

## Campos, fonte e escopo

O comando `fiscal:import:project-nt009-fields snapshot invoice evidence comext`
exige conhecimento141 exato e replay completo. Reutiliza a conciliação141 e
recebe declaraçãoCOMEX explícita vinculada à fatura,com evidência e data.
O contrato142 possui disponibilidade própria;dados futuros ou contrato ainda
indisponível não passam. Não acrescenta uma regra tributária ao catálogo141.

Fonte primária: [NT0091.01](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf),
SHA256cc86c9402e317e84eb3a9f48bbd5e0b8e3dcd84f2645700190c84d45bf1ea58e.
Páginas16–18 foram extraídas,renderizadas e conferidas visualmente. FontesPDF,
tabelaBCB eZIP oficial exigem os hashes preservados. O modelo usa os caminhos
documentais daNT009 para fatura,moeda e ajuste,sem misturar os preçosISS/RTC.

O modelo contempla os camposCOMEX de modo,vínculo,moeda,fomento,movimentação
de bens eMDIC do perfil declarado,além denFatura/vFatura/nContCambio e
vServ/vAjusteBCIBSCBSComExt. O exemplo é transfronteiriçoUSD,sem mecanismo
de fomento e sem movimento temporário de bens. Outros perfis são pendências
explícitas. COMEX é uma declaração documental;autenticidade,modo fiscal e
validade temporal da tabela de moedas não são presumidos.

A faceta observada denFatura/nContCambio é numérica1–20. O cenário positivo
é uma **nova fatura sintética** com identificador original0002026001,que permanece
igual na saída. Não se remove texto ou pontuação do identificadorforeign-invoice-1
do cenário141. Esse identificador é recusado para o campo observado.
Referência de pagamento é opcional;quando declarada exige identificação numérica
e evidência correspondente,sem alegação de pagamento autenticado.

vFatura mantémUSD1000,00 comtpMoeda220,conforme a tabelaBCB preservada.
vServ mantémBRL5199,10;ajuste cambial−19,60 e exclusões legadas declaradas
preservam a baseRTC4440,44. Não se cria valor de tributo,benefício ou câmbio para
preencher campo. A conciliação completa das memórias é reutilizada de141.

## Gate e pendências

O gate abre oZIP oficial imutável e inspeciona as declarações de elementos
dosXSD usando o namespaceXMLSchema. Não altera o arquivo,não injeta schema
local e não usa comentários ou textos doPDF como se fossem elementosXSD.
A inspeção confirma a ausência dos quatro campos novos. O relatório contém
`FIELD_MODEL_PREPARED_OBSERVED_SCHEMA_INCOMPATIBLE`,campos,evidências e
recusa verificável. Presença de nomes em uma versão futura também não bastará:
caminhos,facetas e documento completo exigirão validação própria.

Este é um modelo de **camposCOMEX e monetários**,ainda sem montagem completa
de partes/identidade/DPS. `xml_generated=false`,obrigação documentalfalse,
decisão fiscalfinalfalse,produçãofalse. O escopo original80 e os oito grupos
permanecem preservados;0/8 encerramentos formais. Fases3/4A continuam encerradas
emSandbox,4B em desenvolvimento e5–7 planejadas.

Próximo trabalho:conferir oAnexoVI aplicável e integrar identidade/partes/DPS
ao modelo versionado,com validação estrutural compatível e demais perfis.
Desenvolvimento segue autorizado e ativo;este documento não registra parada.

## Tentativas preservadas

O primeiro teste142 identificou uma fixture inconsistente:a nova identidade
não havia sido atualizada emforeign_document_id da provaFX. A fixture foi
corrigida e os testes negativos passaram a exigir motivos da camadaNT009,
evitando que uma recusa anterior mascarasse o comportamento esperado.
Rodada inicial5 falhas/49 passes;segunda rodada54/123 aprovada.

O capturadorPython executou os quatroCLI e suas asserções,mas falhou no último
passo de conferência ao usarall como nome de um dicionário. Driver e saídas
foram preservados. Uma auditoria independente somente leitura conferiu os
relatórios e nove hashes de entrada no host e no contêiner,sem repetir osCLI.
O erro do capturador não foi ocultado nem tratado como aprovação por si só.

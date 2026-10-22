# ISS134 — integração versionada e grupo de valores daDPS

Checkpoint em 2026-10-22T16:43:08.519378-03:00: parada segura solicitada pelo usuário. Regressão134 encerrada:9.406 testes/145.560 asserções,5.179 entradas intactas;auditoria415/114 hashes. Corte134 validado;4B parcial,0/8 encerrados. Ensaios135 externos não integrados. Ver `phase4b-day-closure-20261022.md`.

Em22/10/2026 foi instalado o bundle
`CONSUMPTION-PHASE4B-2026.10.22-134-draft1`, sucessor imutável131-draft2.
Integra o cálculo133 ao motorRTC e ao percurso fiscal integrado. Acrescenta
as cinco observações municipais133 às107 fontes anteriores, sem mudar seus
bytes ou datas. A disponibilidade do contrato134 é18:36UTC de22/10.

## Comportamento integrado

A definição tipada `rtc.export_iss_tax_context_proof_records` transporta duas
provas tributárias por documento. Apenas esse portador novo fica fora do hash
de qualificação dos antecessores; os demais fatos, valores financeiros e provas
materiais continuam vinculados. Os arquivos de definição/perguntas recebem
somente uma entrada nova cada. A ausência de provas gera pergunta nos dois
runners. O novo bundle exige também as políticas herdadas122/126–129/131 por
versão, mesmo que seus marcadores opcionais sejam removidos.

No percurso de preços documentais da exportação parcial,134 reexecuta133 e seus
antecessores106/111 e registra `PARTIAL_EXPORT_DOCUMENT_ISS134`. O resultado
mantém base, imposto, composição, tratamento municipal, código e hashes por
documento. Não altera o cálculoISS da operação nem substitui a base nominalRTC
pela baseISS documental. A decisão continua não final quando faltam os demais
elementos documentais. A classe133 e os resultados das versões anteriores
permanecem intactos; a fixture133 ganhou somente parâmetro opcional de data de
conhecimento, mantendo seu valor padrão anterior.

## Projeção permitida pelo schema

O comando `fiscal:dps:project-document-values {snapshot} {options}` exige snapshot
integrado134, conhecimento idêntico ao bundle e replay completo exato. Confere
os cinco arquivos municipais e oXSD original. As opções são duas declarações
explícitas por documento:papel, referência, retenção, origem da alíquota, país do
resultado quando aplicável, indicador de tributos totais e referência de prova.
Não há padrão para retenção ou parametrização municipal.

Cada fragmento contém o grupo `valores`:preço bruto próprio, descontos
incondicional e condicionado separados, tratamentoISS, retenção, país do
resultado exportado e indicador de tributos totais. `pAliq` só é projetado
quando a declaração indica fornecimento pelo emitente; se a alíquota é
parametrizada pelo sistema nacional, permanece no relatório e não é enviada.
País e opções são declarações locais: não houve qualificação da lista de países
nem autenticação dessas provas neste corte.

O código municipalSP `02660` tem cinco dígitos; o tipo nacional `TCCodTribMun`
aceita três. Não há truncamento nem mapeamento inventado. Esse campo opcional
não é projetado, e o código original permanece no relatório. `vBC` e `vISSQN`
não são entradas do tipo `TCInfoValores`; base e imposto calculados também
permanecem no relatório. Isso não impede montar depois o documento completo
com os campos de entrada próprios e a seleção documental cabível.

A validação usa um elemento-raiz local `valores`, declarado em um invólucroXSD
local e associado ao tipo oficial `TCInfoValores` extraído doZIP127 intacto.
O relatório informa o hash desse invólucro e `full_document_validated=false`.
Não se altera o schema oficial. A API de validação deDPS completa continua
rejeitando os fragmentos. `--output-dir` aceita apenas diretório novo e grava
dois fragmentos e relatório com exclusividade efsync, após todas as validações.

O resultado é `LOCAL_DOCUMENT_VALUES_VALIDATED`, com decisão fiscal ainda
`ACTION_REQUIRED`, `complete_dps_built=false`, obrigação incompleta e produção
bloqueada. Um grupo de valores válido não é umaDPS completa, aprovação de
emissão ou evidência de cumprimento da obrigação.

## Evidências já observadas

355 testes dirigidos/2.755 asserções passaram. PHPStan passou com512MiB
(a primeira tentativa com128MiB encerrou por memória insuficiente, preservada).
Pint655 arquivos. Capturados22 snapshots com replay exato nos dois runners,
comparando131/134 em controles de preço, desconto, zero, inversão dos papéisRTC,
câmbio ausente e prova ausente. As comparações confirmam os demais estágios
fiscais e o cálculoISS da operação inalterados. Dezesseis arquivosXML são duas
execuções de oito fragmentos de valores; não são16DPS completas. Mais19
snapshots históricos131/132 tiveram replay exato.

Evidências em `D:/ProjetoSaasExports/phase4b-iss134-20261022-v1`.
A avaliação do bundle conferiu112 fontes, elegibilidade para validação local,
130 bloqueios de produção e `ready=false`. Os grupos4B não foram encerrados.

Regressão integral iniciada em22/10 às18:51:52UTC, após inventário dos arquivos
do motor, testes e recursos fiscais. Processo e logs em `full-suite-v1` noD:.
Encerrada em22/10 às19:40:16UTC com código0:9406 testes/145560 asserções;5179 entradas sem alteração ou adição. Auditoria415 conferências/114 hashes aprovada. Recibo `storage/app/phase4b-iss134-development-20261022/iss134-scoped-validation.json`. O aceite122 permanece histórico;esta regressão não encerra4B.

## Trabalho restante

Concluir a seleção/classificaçãoRTC da parcela estrangeira e montar o parDPS
completo; qualificar mapeamento municipal de três dígitos quando exigido;
ampliar câmbioISS próprio, importação, demais perfis/ajustes e fechar a obrigação
documental. O motor de exportação integral existente retorna imunidadeIBS/CBS,
mas não oferece por si uma classificação de emissão da parcela estrangeira:
não é prova suficiente para copiar valores de classificação do documento nacional.
Os80 requisitos originais e0/8 grupos encerrados permanecem a referência de escopo.

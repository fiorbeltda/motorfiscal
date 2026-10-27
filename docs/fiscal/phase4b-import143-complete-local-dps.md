# 143 — montagem local completa da DPS no perfil de importação141/142

Em27/10/2026,o143 integrou cabeçalho,identidade,partes,endereço nacional do
adquirente,serviço,COMEX,valores,tributos documentais declarados e classificação
RTC em um candidatoXML inteiro. **45 testes/195 asserções**,PHPStan global0 e
Pint5 aprovados. OCLI real reproduziu o mesmoXML duas vezes e recusou três
entradas inválidas. Não houve regressão integral143. Evidências noD:,
`D:/ProjetoSaasExports/phase4b-import-dps143-20261027-v1`.

Recibo:
`storage/app/phase4b-import143-development-20261027/import143-integration-scoped-validation.json`.
Matriz80 atual na mesma pasta. Auditoria confere5.958 entradas e141 hashes de
runtime,com as5.952 entradas e138 hashes anteriores preservados. O candidato
de referência é `native-v1/first-candidate.xml`,SHA256
`00524724a4d3b0aab020224ac1e7469849c12c6311e9c82bb812526314600b1b`.

## Fonte e estrutura

O [AnexoVI1.04.01](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/anexovi-leiautesrn_rtc_ibscbs-v1-04-01-nt009.xlsx)
foi recapturado em27/10/2026. SeuSHA256 é
`103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39`,
idêntico aos bytes preservados em135. A consulta foi somente leitura;nenhuma
planilha foi alterada. Metadados,linhas e observações foram preservados noD:.

O contrato versionado em
`resources/fiscal/projections/import-dps-nt009-2026.10.27-143.json`
contém339 linhas ativas da estruturaDPS e quatro linhas tachadas excluídas,
com coordenadas,tipos,ocorrências,limites e textos de origem. O runtime exige
oSHA exato desse contrato e doXLSX original. ID doXML usa a grafiaId do schema;
a referência preserva o identificadorid escrito na planilha. Campos com espaços
na colunaCAMPO mantêm o texto bruto e recebem o nomeXML sem esses espaços.

O validador verifica caminhos ativos,campos/grupos obrigatórios nos pais
presentes,escolhas mutuamente exclusivas e facetas básicas. As datas e relações
temporais são verificadas pelo qualificador documental;dhEmi/verAplic também
passam pelas facetas comuns extraídas doXSD observado. Essa conferência da
tabela não é apresentada como validaçãoXSD ou implementação de todas as regras
de negócio da planilha.

indDest é emitido diretamente eminfDPS (C179),e não no grupoIBSCBS antigo.
vServMoeda (C241) está tachado e não é gerado. nFatura/vFatura ativos (C239:C240)
preservam o identificador numérico original0002026001 e o preçoUSD1000,00.
O fornecedor recebeNIF,endereço exterior e regime documental explícito;
o adquirente recebeCNPJ e endereço nacional vinculados aos fatos. Não se
inventa CNPJ brasileiro para o prestador estrangeiro.

## Valores e execução

`fiscal:import:project-dps-model snapshot invoice evidence comext identity`
exige conhecimento141 exato e replay completo antes da projeção. A identidade
tem contrato,data de observação,emissão,série,número,municípios e evidências
explícitos. A entrada documental inclui endereço do adquirente eCST legado99
pré-qualificado para o perfil deste contrato,sem presumir incidência,benefícios
ou alíquotas dePIS/Cofins-importação.

vServ permanece5199,10,o ajuste cambial−19,60 e os valores declaradosPIS85,46/
Cofins393,64. ComISS259,96 à alíquota sintética5%,a memória independentePython
conferiu diretamente dos elementosXML a baseRTC4440,44. O novo código não
calcula tributos legados nem transforma a fixture em aprovação jurídica.
Identidade,endereço,prestador,facetas,fonte,grupos/choices e dados fiscais
contraditórios interrompem a montagem antes de produzir o candidato.

O relatório inclui oXML candidato,campos,hashes,linhas de origem,conferência
estrutural,memória monetária e resultado doXSD real. As duas execuções doCLI
foram idênticas;outras três recusaram snapshot adulterado,emitente divergente e
ambiente de produção. OsCLI retornam1 porque não há aceitação oficial do
documento. O ambiente e o banco existentes foram preservados;nenhum envio,
assinatura ou novo banco foi realizado.

## Duas limitações da fonte observada

OXSD oficial preservado ainda não contém os campos novosNT009,e a validação do
candidato completo retornaINVALID. O relatório conserva os erros reais. Não
há substituição ou edição do schema para produzir um resultado verde.

Na planilhaRN DPS_NFS-e,D230:I230 prevê rejeição de emissão pelo tomador ou
intermediário nesta versão da aplicação (E9996). D231:I231 exige esse emitente
para fornecedor estrangeiro e prestação noBrasil (E0027). As duas observações
não tachadas ficam no relatório;nenhuma delas é apagada ou convertida em
dispensa da obrigação fiscal. Não foi feita consulta de aceitação remota.

Essas limitações não impedem a projeção headless e não criam exigência de
assinatura/transmissão para o aceite localSandbox. Elas são separadas da
cobertura funcional exigida pelos80 requisitos. O presente aceite comprova
a montagem local completa **deste perfil**;demais perfis e qualificação material
continuam sujeitos à revisão individual. Obrigação fiscalfinal e produção
permanecemfalse. Fase4B segue em andamento,0/8 grupos formalmente encerrados.

## Tentativas preservadas e continuidade

A primeira rodada encontrou a dependência explícita da política130 no fragmento
de prestador estrangeiro;ela foi vinculada sem alterar o código ou os bytes
históricos. Os três apontamentosPHPStan foram corrigidos sem supressões.
Uma rodada intermediária44/192 passou;um probe nativo mostrou que emissão
com frações de segundo não atende a faceta comum observada. Foram incluídos
validação real do cabeçalho,fixture com segundos inteiros e teste negativo;
a rodada final45/195 e osCLI cobrem a versão corrigida. Todas as tentativas
continuam preservadas.

Próximo trabalho:revisar a matriz80 à luz da cobertura funcional atual e priorizar
lacunas dos demais perfis/grupos,conservando as incompatibilidades documentais
observadas e sem confundir aceiteSandbox com liberação de produção.

Encerramento da task e parada solicitada em 2026-10-27T13:14:31.903760-03:00. Ver `phase4b-checkpoint-stop-20261027-import143.md`. A continuidade acima e trabalho futuro apos retomada.

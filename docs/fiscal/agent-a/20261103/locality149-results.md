# A149 — localidades materiais de serviços especiais

Contrato `RTC-SERVICE-MATERIAL-LOCALITY-2026.11.03-A149`.
CLI própria: `fiscal:rtc:qualify-service-locality operation.json`.
Escopo4B.2.3/4B.2.4/4B.2.5/4B.2.7; baseGit87d22e7 e somente arquivosA.

O qualificador vincula registros de escopo/local à operação,fornecimento,partes
e data. Confere o hash de conteúdo dos registros fornecidos, disponibilidade
da informação, papel do local e coerênciaUF/município no snapshotIBGE115.
Integridade do registro não é autenticação de sua origem nem prova cadastral.

Foram comprovadas oito famílias: imóvel em único município; presença pessoal
integral; organização de evento; serviço físico sobre móvel; porto; início do
transporte de passageiros; entrega documental de carga; e terminal fixo.
O transporte de carga exige o documento correspondente ao registro de entrega.
Não se substitui a regra especial pelo endereço de domicílio de outra parte.

Exceções permanecem explícitas: imóvel em vários municípios exige a regra da
maior área; exploração de via exige extensão territorial; prestação pessoal
parcialmente remota requer qualificação residual; contratação centralizada de
comunicação exige art11§4. Nenhum desses casos recebe uma localidade por padrão.

## Provas terminais

-30 testes/144 asserções, incluindo datas,partes,documento trocado,registro
  alterado,recalculado ou futuro,UF incoerente e ausência de fatos materiais.
-Oito famílias executadas nativamente e reproduzidas por replay.
-DuasCLI byte a byte idênticas; documento de carga divergente retorna recusa
  explícita eexit1, sem localidade qualificada.
-Hashes de entrada,registros e relatórios conferidos independentemente emPython.
-FonteLC214 preservada revalidada por hash eHTTP200; leitura do art11 na fonte
  primária, sem promover correlação de catálogo a regra decisória automática.
-PHPStan dos dois arquivos de produção:zero erros; Pint dos quatroPHP:PASS,
  com verificação posterior do teste ampliado.

Recibo `validation-A149.json`; verificador `scripts/agent_a/verify149.py`.
Artefatos em `D:/ProjetoSaasExports/agent-a-A149-service-locality-20261103-v1`.
Esta suíte não é somada às regressões anteriores nem chamada de integral.

## Interface e limitações

SaídaA: artigo,papel do local,UF/município,registros vinculados,referência
geográfica observada e hashes. `classification_catalog_integration=PENDING_AGENT_B`.
Não seleciona cIndOp,cClassTrib,NBS,alíquota,crédito ou schema. B deverá integrar
essa qualificação às suas classificações/versionamento e projeções; nenhum
arquivo ou enum global de B foi escrito. Recebimento/integracão não comprovados.

O snapshot geográfico não prova existência jurídica histórica, estabelecimento
ou domicílio. Nenhum requisito inteiro ou grupoA foi formalmente encerrado.
Próximo incrementoA: áreas de imóvel e extensões de via, com alocação exata e
perguntas para empate/medição incompleta, sem inferir percentual ou tributo futuro.

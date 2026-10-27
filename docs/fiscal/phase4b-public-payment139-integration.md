# 139 — retenções públicas por pagamento

Par139 integrado e aprovado em validação dirigida em26/10/2026:
**85 testes/403 asserções**, quatro arquivos de teste, PHPStan global sem erros
e Pint dos oito arquivosPHP alterados aprovado. O CLI
`fiscal:dps:project-export-pair-withholdings --public-payment-contract=139`
seleciona expressamente o contrato novo; a omissão preserva o caminho137.
O contrato novo está disponível a partir de19:22UTC. Esse aceite não encerra
o escopo original da Fase4B nem autoriza produção.

O coordenador139 compartilha as validações do137, exige capturas139 novas e
vinculadas à política139 e captura o manifesto federal por caminho e SHA256
fixados. O snapshot134 de ISS/RTC é reexecutado pelo caminho anterior e não é
substituído pelo manifesto federal. As perguntas do cálculo federal não final
são propagadas ao resultado do par, que devolve uma lista de XML vazia.

Os cinco perfis públicos foram verificados em dois documentos completos cada,
com XSD e comparação canônica confirmando que somente `tribFed` foi acrescentado
ao par135 correspondente. Doze controles com capturas sintéticas novas recusam
perfil/tipo ausente, categoria desconhecida, fundação não qualificada, convênio
ausente/sem fonte/vencido, regra local ausente/vencida, Siafi com tipo inválido,
arredondamento do percentual agregado e contratos/políticas137 no caminho139.
Também foram verificados indisponibilidade temporal e seletorCLI desconhecido.

O caso de Siafi ausente propaga a pergunta até o coordenador e não devolve XML.
Nas duas alternativas explícitas de um perfil hipotético com somente PIS retido:
Siafi falso resulta em dispensa,líquido641,33;Siafi verdadeiro resulta emPIS4,17,
`tpRetPisCofins=5`,líquido637,16. Em ambos permanecem os débitos próprios
PIS4,05/Cofins18,68,distintos da retenção. As exceções são fixtures hipotéticas,
sem homologação de parâmetros legais ou autenticidade bancária/cadastral.

O CLI real foi executado duas vezes para o município sem convênio:quatroXML
idênticos,dois papéis documentais e validação XSD completa. A declaração inválida
foi recusada sem criar diretório de saída. O resultado completo137 foi reexecutado
com os arquivos históricos preservados e reproduziu seu hash canônico
`d3ec1bd1051c48dcf76926728a0551841fb26bcbaefb8ef62224719a5d05d395`.
Recibos nativos em
`D:/ProjetoSaasExports/phase4b-public-payment139-20261026-v1/pair-native-v1`.

A auditoria independente conferiu os valores próprios,IRRF municipal e líquido
diretamente no relatório/XML; os resultados estão em `independent-amounts-audit.json`.
Todas as5.926 entradas congeladas mantiveram os hashes;132 arquivos de runtime
estão vinculados ao aceite. Não foi repetida uma suíte integral por rotina.

Recibo do par:
`storage/app/phase4b-public139-development-20261026/public139-pair-integration-scoped-validation.json`,
SHA256 `38975a4e969afe345b627c32e17279a282b9687060e870474f2b2d5cdc592097`.
Matriz atual: `requirements80-pair-current-matrix.json` na mesma pasta,80 revisões
próprias e0/8 grupos formalmente encerrados. Permanecem os demais perfis/eventos,
base/preço/câmbio,documentos de importação e pagamentos fora da liquidação integral
separada emBRL,além das qualificações materiais de exclusões e efeitos judiciais.

Primeiro ensaio do par:19 casos passaram;um caso não chegou ao coordenador,
pois a preparação de preço100,00 não conciliava com a parcela RTC do contrato.
A recusa de preço foi preservada. O ensaio do Siafi passa a usar o preço
conciliado641,33 e exceções hipotéticas de retenção por componente, mantendo
os débitos próprios. Isso verifica uma dependência material, sem afirmar
existência ou validade jurídica dessas exceções para um contribuinte real.

Em desenvolvimento em 26/10/2026, após a aprovação auditada da integral137:
9.585 testes,145.896 asserções e5.917 entradas preservadas. O recibo está em
`storage/app/phase4b-retention137-development-20261023/retention137-full-validation.json`.
Isso não encerra nenhum dos oito grupos da Fase4B.

Foram reproduzidas duas falhas no código integrado:

- Retenção federal pública calculada de R$9,45, sem o fato
  `payer.siafi_electronic_collection`: o resultado antigo dispensava a retenção,
  sem pedir o fato. A ausência pode alterar a decisão de dispensa.
- Pagamento municipal sem convênio do art.33: o cálculo retornava IRRF de
  R$30,78 e PCC não aplicável, mas serializava o total PCC como `"0"`.
  O projetor documental exige centavos e rejeitava esse caso válido.

Evidências externas preservadas em
`D:/ProjetoSaasExports/phase4b-public-payment139-20261026-v1`:
`siafi-probe-validation.json`, `legacy-integrated-missing-siafi.json`,
`municipal-bound-failure.json` e `municipal-money-source.json`.
O ensaio dos cinco perfis parou no terceiro: somente os dois perfis federais
tinham passado; não há aceite dos cinco perfis ou dos dez controles negativos.

A correção deve selecionar um contrato novo no manifesto de conhecimento,
preservar os resultados dos manifestos antigos e gerar pergunta para o fato
material ausente. O zero calculado deve ser serializado com centavos no produtor,
sem afrouxar a validação de entradas monetárias externas.

Parâmetros de recolhimento municipal e convênios dos ensaios são sintéticos.
Nenhum parâmetro municipal foi homologado para produção. O catálogo do AnexoI
continua candidato Sandbox; nenhuma alíquota ou aprovação normativa foi criada.

## Aceite dirigido do produtor e das perguntas

O manifesto `CONSUMPTION-PHASE4B-2026.10.26-139-draft1`, disponível a partir de
26/10/2026 às19:15UTC, seleciona `PUBLIC-DOCUMENT-PAYMENT-139-V1`.
Manifestos anteriores mantêm o comportamento arquivado. Contrato desconhecido
é recusado; a ativação exige o catálogo público. A pergunta identifica
recolhimento por Darf eletrônico viaSiafi, sem equiparar isso à simples integração
do órgão ao sistema.

Passaram63 testes dirigidos/253 asserções nos arquivos `PublicPayment139Test`,
`PublicAdministrationRuleSetTest` e `FederalPaymentPair137Test`.
PHPStan global terminou sem erros e Pint aprovou os quatro arquivosPHP alterados.
O primeiro ensaio teve uma expectativa de teste incorreta: tentou ler uma decisão
final num resultado `ACTION_REQUIRED`. A conferência foi corrigida para examinar
a pergunta no resultado raiz e a avaliação federal no rastro.

Os cinco perfis de cálculo integrado foram verificados: administração federal
direta, sociedade federal de economia mista, município sem convênio, município
com convênio sintético e empresa pública estadual seguindo o perfil privado.
No município sem convênio: IRRF30,78,PCC0,00,líquido610,55; o projetor137 emite
somente o tipo de não retenção social e `vRetIRRF=30.78`.
No perfil federal: IRRF30,78,PCC29,82,líquido580,73. A soma dos componentes
arredondados não pode ser substituída por9,45% arredondados de uma só vez.

O ensaio nativo externo reproduziu integralmente dois resultados históricos
capturados antes da correção e confirmou seus hashes. Também preservou a nova
pergunta e a projeção municipal. Artefatos:
`D:/ProjetoSaasExports/phase4b-public-payment139-20261026-v1/integrated-v1`.
O cálculo municipal foi conferido independentemente com aritmética decimal.
As5.915 entradas antigas não alteradas continuam com os hashes137; os dois
arquivos de runtime modificados estão identificados no recibo139.

Recibo compacto:
`storage/app/phase4b-public139-development-20261026/public139-integration-scoped-validation.json`,
SHA256 `f433b34cf08355ca666982a6602f86562f9a8bf11c11a6e803274109ae128ce3`.
A matriz80 atual está em `requirements80-current-matrix.json` na mesma pasta.

Não foi executada outra suíte integral139. A integral137 aprovada pertence ao
estado anterior a esta correção. No aceite inicial do produtor, o coordenador
do par139 ainda estava pendente; a seção de aceite do par acima registra sua
integração posterior. Nenhum grupo4B foi encerrado e produção permanece bloqueada.

## Preservação de armazenamento

Os1.441 arquivos históricos inativos de `/tmp/credit122-historical-v2` foram
preservados integralmente noD:,com conferência de cada hash e arquivo de retorno
no contêiner. A cópia duplicada foi removida somente após essa validação.
Recibo: `D:/ProjetoSaasExports/container-temp-relocation139-20261026-v1/release-validation.json`.
Isso libera espaço reutilizável dentro do Docker; não equivale a reduzir oVHD
noC:. Não houve recriação do aplicativo ou do banco.

Compressão NTFS de históricos fechados preservou caminhos e todos os hashes.
Recibos: `D:/ProjetoSaasExports/ntfs-cold-history-preservation-20261026-v1/validation.json`
e `D:/ProjetoSaasExports/ntfs-cold-history-batch-20261026-v1/validation.json`.
Variação total do espaço livre doC: também inclui outros processos; não é medida
exclusiva do efeito da compressão.

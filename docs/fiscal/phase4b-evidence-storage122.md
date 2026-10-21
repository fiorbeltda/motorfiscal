# Evidências preservadas no D:

Autorização de21/10/2026:priorizarD: para arquivos pesados,preservar o histórico
e manter rastreabilidadeC:↔D:. Regra permanente emAGENTS.md.

## Transferência e auditoria pós-junção concluídas

Duas pastas de`storage/app` foram copiadas e verificadas antes de remover
as cópias redundantes doC:. Os caminhos originais agora são junçõesWindows.

| Pasta histórica | Arquivos | Bytes lógicos |
| --- | ---: | ---: |
| phase4b-private-consolidation48-draft-20261009-28 | 969 | 3.350.553.583 |
| phase4b-realization-consolidated-expansion47-draft-20261009-28 | 829 | 3.257.138.630 |
| Total | 1.798 | 6.607.692.213 |

Destino:`D:/ProjetoSaasExports/preserved-storage-app/`,mesmos nomes.
Recibos e inventários:`D:/ProjetoSaasExports/storage-relocation122-20261021-v1/`.
Índice compacto:`storage/app/evidence-storage-index-20261021.json`.

O coletor1441 terminou antes da transferência,com replays exatos e entradas
intactas. A migração terminou comGit preservado,hashesC:/D: conferidos e todos
os arquivos acessíveis no contêiner. A auditoria independente repetiu todos
os hashes peloC: e pelo contêiner DEPOIS da criação das junções:1.798 arquivos,
6.607.692.213bytes e94 hashes de runtime intactos. Recibo terminal:
`storage122-final-validation.json`,SHA256
`d0cf6e12868c6103ea748ff52ffe4d372675a13ab8212b1834b1acf85a1f570d`.

App eweb reiniciaram com as junções já existentes. Depois do reinício,foram
verificados todos os1.798 caminhos/tamanhos,quatro testemunhosSHA,binds somente
leitura ePostgreSQL saudável:`post-restart-access-validation.json`.
Essa última verificação NÃO é apresentada como outra auditoria integralSHA.

O teste sintético demonstrou que o bind do checkout não atravessa sozinho a
junçãoNTFS paraD:. Por isso,`compose.yaml` usa bindsD: explícitos somente leitura
em app eweb,nos mesmos caminhos lógicos. `FISCAL_EVIDENCE_ROOT` permite alterar
a raiz;pastas ausentes geram erro,sem criar diretórios vazios silenciosamente.
A imagem original foi mantida:`sha256:98a42b7fde1f00bef9a2e2141690bb53bb0c838520dd0f2411d419296a0cee59`.
PostgreSQL e volumes não foram removidos.

## Preservação do contêiner e interrupção documentada

A exportação integral foi interrompida quando oDocker e o executor encerraram.
Não é um backup completo de rootfs. O arquivo parcial e a falha foram mantidos.
A inspeção estrutural não bastou:um snapshot no limite do arquivo tinha bytes
incompletos,e sua verificaçãoSHA256 recusou a primeira recuperação.

A tentativa posterior preservou e verificou todas as11.141 entradas graváveis
inventariadas,por arquivo,com oTAR parcial e dois suplementos. O arquivo
incompleto foi explicitamente rejeitado;uma cópia específica com oSHA original
completou o conjunto. O mapa aponta a origem verificada de cada entrada:

`D:/ProjetoSaasExports/container-preservation122-recovery-20261021-v3/verified-entry-source-map.json`.

Aceite dessa preservação:`container-preservation-validation.json` no mesmo
diretório. Declara`full_rootfs_export_complete=false`:a imagem-base retida e os
binds/volumes permanecem separados. O diff comprovou que as alterações materiais
graváveis estavam nos diretórios inventariados;metadados dos pais foram
registrados. Não sobrescrever falhas,arquivos parciais ou suplementos.

Docker relançado viaWMI,com paiWmiPrvSE.exe,fora da árvore do executor:
`D:/ProjetoSaasExports/docker122-detached-relaunch-20261021-v1/readiness-validation.json`.
Não foi instalada tarefa agendada ou serviço adicional. O smoke pós-recriação
passou nos dois executores:2 testes/6 verificações,somente do testemunho119,
antes e depois do reinício com junções. Recibos em
`D:/ProjetoSaasExports/phase4b-credit122-preparation-20261021/storage122-post-restart-smoke-v2/`.
Isso não substitui regressão integral122.

## Espaço físico e totais

As pastas históricas estavam compactadas noNTFS doC:. Logo,6,6GB lógicos
transferidos não equivalem a6,6GB físicos liberados. O recibo da operação
observouC: de3.496.427.520 para3.836.588.032bytes livres;outros processos
também fazem esse valor oscilar.

Os primeiros recibos serializaram o total de bytes como`null`,por uma limitação
de`Measure-Object` sobre dicionários ordenados. Não foram reescritos. Os tamanhos
individuais eSHA de todos os arquivos estão nos manifestos;os totais da tabela
são sua soma. O código anterior foi preservado noD: e a agregação corrigida.
A auditoria final recalculou os totais e vinculou os recibos originais;os valores
corrigidos e oSHA do aceite estão no índice compactoC:.

Nenhum histórico foi apagado. Só as cópiasC: redundantes foram removidas depois
da verificação;todo o conteúdo permanece noD:. A limpeza anterior removeu
somente o instalador temporárioDocker de339.559.344bytes,após backup eSHA:
`D:/ProjetoSaasExports/cleanup-c-credit122-updater-20261021-v1/cleanup-report.json`.

119 continua como último aceite fiscal integral. A preservação de armazenamento
não conclui122,não encerra aFase4B e não libera produção.

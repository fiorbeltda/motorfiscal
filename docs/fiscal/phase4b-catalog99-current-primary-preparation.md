# Catálogo99 - atualização oficial1.70 preparada,sem instalação

Situação posterior:catálogo99 integrado ao101 aceito localmente,com referência100
e bundle próprio.6.296 testes/94.755 verificações,9.272 entradas intactas,78 hashes,
duas cópias,auditoria e PHPStan/Pint558 finais conferidos.98 preservado.
Ver `phase4b-catalog101-current-integration.md`. O texto abaixo
documenta a preparação histórica99,não o estado instalado atual.

Pesquisa e preparação isoladas realizadas em16/10/2026,enquanto a regressão98
permanece ativa. Nenhuma entrada congelada foi alterada. Auditoria pontual conferiu
7.577 entradas e77 hashes runtime98. Não há novo bundle nem aceitação fiscal99.

Atualização posterior do mesmo dia:98 encerrado e aceito localmente em3.173 testes/
55.383 verificações,com7.577 entradas intactas e77 hashes.99/100 continuam isolados;
é necessário novo bundle para a migração. O relatório99 preserva seu momento de preparação.

## Fontes e revisão

O [índice oficial de documentos diversos da NF-e](https://www.nfe.fazenda.gov.br/portal/listaConteudo.aspx?tipoConteudo=/NJarYc9nus=)
identifica a [tabela cClassTrib publicada em01/10/2026](https://www.nfe.fazenda.gov.br/portal/exibirArquivo.aspx?conteudo=Cd5xq2ZVuEg=).
O [índice de informes técnicos](https://www.nfe.fazenda.gov.br/portal/listaConteudo.aspx?tipoConteudo=hXzemuyNHW4=)
liga o [IT2025.002v1.70](https://www.nfe.fazenda.gov.br/portal/exibirArquivo.aspx?conteudo=h9o7idH%20OcI=)
à mesma publicação. A capa indica setembro,distinto da publicação observada em
outubro. O cronograma da página3 anuncia HML/PROD até16/10/2026;isso não demonstra
implantação remota. Não foi interpretado como ativação automática em NFS-e.

Seis capturas primárias locais preservadas com TLS validado,URL inicial/final,
hash e observação. O arquivo de junho recapturado tem o mesmo SHA do usado pelo
bundle98:`1448cb63a41bdb67ea30b4e11f4dc200d9568542c6b9335b6cff87a4fd664654`.
Novo XLSX:`bee3e106d24c06257d31105658679e97e2544dcc3ac61467fbe0ca4f78c6e850`.
PDF:`cbf61d15fce46101df9c17737a68fc577b058b3b81c0d9b78382f831ed56d83f`.

As13 páginas do informe foram extraídas,renderizadas e revisadas visualmente.
Seções2/3 e7.09 detalham os campos e as mudanças. Página8 continua indicando
legislação pendente para parâmetros futuros;nenhuma alíquota ausente foi inventada.
As planilhas foram lidas sem edição pelo Artifact Tool e confrontadas célula a
célula com openpyxl em modo somente leitura. Datas seriais e identificadores
foram comparados sem perder zeros ou converter ausência em zero.

## Diferenças concretas

164 códigos anteriores,173 na fonte nova. Nove adições,nenhuma exclusão física:
000006,200055,200056,400003,400004,550026,550027,550028,550029.
Somente as três primeiras adições têm indicador de suporte NFS-e habilitado.
Isso não qualifica automaticamente a operação de um autônomo para esses códigos.

Adicionado `tpDoacao`,retirado `ind_gMonoDif`. O primeiro é domínio0/1/2 para
preenchimento do indicador documental de doação,não alíquota. A fonte atualiza
todos os carimbos de atualização;por isso164 linhas diferem na comparação bruta.
Excluindo apenas carimbo e adição/retirada de coluna,há29 códigos com mudanças
em campos compartilhados. Não interpretar164 como164 novas regras legais.

O código410028 passa a não suportar NFS-e. Nenhuma regra decisória ativa98
menciona esse código,mas a futura referência deve conservar a alteração.
Nova aba cClass passou a ser a primeira,antes era a segunda. As novas abas
`tpDoacao` e `tpRBSN` preservam os domínios de referência. A planilha nova contém
172 linhas de dados ocultas por filtros: análise somente das linhas visíveis
omitiria quase todo o catálogo. Todas as173 linhas foram lidas.

## Preparação e comparação nativa

Importador por cabeçalho,não por índices fixos de coluna ou primeira aba.
Mantém datas,CST,referência legal,indicadores,domínios,reduções e localização da
linha fonte. Valida173 classes e18 CST. Duas linhas novas usam CST numérico400;
a normalização restrita0-999 conserva o identificador de três dígitos e verifica
seu prefixo contra cClassTrib. Dimensões estilizadas vazias82/89 colunas não foram
confundidas com43 colunas preenchidas. Diagnósticos e scripts anteriores preservados.

Candidato somente no D::`rtc-codes-v170-candidate.json`,SHA
`9d3002670e5e46d91d256b36f598a4061b471c324355d99446bff108dfbe5788`.
Não altera correlações,seleciona nova classificação ou modifica regras decisórias.

61 casos contrafactuais no PHP nativo:30 de98,30 de antecipações91 e um SaaS
doméstico. Mesmos fatos e instante de conhecimento em ambos os cálculos,
posterior à observação da fonte. Resultado60ACTION_REQUIRED/1VALID em cada
candidato,iguais após normalizar exclusivamente a versão explícita do catálogo.
Não são testes Pest novos,replays de aceitação nem validação pelos dois runners.
Não comprovam o tratamento dos nove códigos novos,projeção de doação ou integrações
não cobertas. Os códigos de autônomos continuam exigindo contexto e regra próprios.

Evidências:
`D:/ProjetoSaasExports/phase4b-catalog99-primary-research-20261016/catalog99-preparation-validation.json`,
`catalog99-comparison.json`,`native-v1.stdout`,`capture-v1/primary-source-observation.json`.
Fontes originais,extrações por ambas as bibliotecas,previews e13 imagens PDF no
mesmo diretório. Nada volumoso foi colocado no C:.

## Próximo trabalho

1. Preservar98 já encerrado e aceito,sem reiniciar a suíte ou reescrever suas evidências.
2. Versionar fonte,catálogo e bundle seguintes,com observação temporal comprovada,
  173 classes,18 CST e domínios;preservar integralmente os anteriores.
3. Incluir testes permanentes de cabeçalhos,abas,tipos,ocultações,vigências e
  indicadores;examinar regras de autônomos e doação sem decidir só por existência de código.
4. Rodar corpus dos dois runners,replays históricos,regressão e auditoria final.
5. Retomar conversão/base e identidade fiscal por documento,par XML e auditoria
  dos80 requisitos.4B.6 e os demais grupos continuam parciais. Produção bloqueada.

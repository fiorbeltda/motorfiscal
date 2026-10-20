# Temporalidade81: contrato de pesquisa, ainda não ativado

## Evidência primária e alcance

Pesquisa em `D:/ProjetoSaasExports/phase4b-temporal81-primary-research-20261015/`.
As capturas HTTP200 da Câmara em 15/10/2026 estão vinculadas por URL, horário
e hash em `primary-source-observation.json`. O artigo10 da LC214 e o artigo11
do Decreto12955 foram extraídos integralmente. O PDF oficial IBS preservado
na geração67 teve hash conferido; páginas físicas11/12 foram renderizadas
e inspecionadas visualmente, incluindo o caput e todos os nove parágrafos.
Textos e imagens estão preservados, sem modificar o PDF oficial.

A versão atual capturada do regulamento CBS difere da captura67. O diff
semântico aponta alterações nos artigos472/476/477, mas não no artigo11.
Isso não autoriza substituir a fonte histórica nem declarar o decreto inteiro
inalterado. O PDF IBS é a observação de13/10; sua integridade não prova sozinho
a inexistência de ato modificador posterior. Reconfirmar vigência antes de ativar.

## Separação necessária

O motor atual recebe `rtc.operation_date` e resolve a transição antes das demais
qualificações (`ConsumptionReformEngine.php`, início de `evaluate`). Há guarda
específica para data de pagamento de compras públicas, além de calendários
próprios de câmbio, crédito e apuração. Portanto não se trata de ausência de
toda temporalidade. Ainda falta provar a derivação geral dessa data a partir
dos eventos de fornecimento, execução continuada e antecipações.

O novo contrato não pode resolver tudo com um único campo de data:

| Evento | Momento relevante | Informação adicional necessária |
| --- | --- | --- |
| Serviço comum | Término do fornecimento | Prova vinculada ao serviço/documento |
| Execução continuada/fracionada | Primeira exigibilidade ou pagamento de cada parcela | Identidade, contraprestação e prova por parcela |
| Antecipação | Pagamento de cada parcela | Base paga; seleção de alíquota na primeira data entre documento correspondente e pagamento |
| Fornecimento após antecipações | Fornecimento | Base total incluindo antecipações; alíquota desse momento e reconciliação de valores já antecipados |

IBS/CBS, regulamentos art11 §4: documento que constitui direito de recebimento
define a exigibilidade na emissão. Não confundir qualquer documento fiscal
com prova de constituição desse direito. §§8/9 também exigem tratar a previsão
de entrega/disponibilização e a presunção documental de fornecimento, com prova
em contrário. Não substituir essas regras por `min` indiscriminado de datas.

A LC214 art10 §7 prevê regulamentação para levar antecipações ao período do
fornecimento em hipóteses com intervalo máximo de cinco dias. Isso não é
autorização genérica para mudar a data do fato gerador ou a alíquota. Nenhuma
exceção de cinco dias foi ativada nesta pesquisa.

## Próxima implementação e prova exigida

1. Reconfirmar atos vigentes e contexto de aplicação IBS/CBS antes do bundle.
2. Implementar qualificador de eventos com identidade, prova de origem,
   cronologia civil, disponibilidade da informação e vínculo ao fornecimento.
3. Separar data do evento, data da alíquota e período de débito; distinguir
   antecipação e cálculo definitivo, evitando tributar novamente o valor inteiro
   sem reconciliação. Não inventar alíquotas de outro período.
4. Cobrir pagamentos múltiplos, empate de datas, emissão anterior/posterior,
   documento sem vínculo, cancelamento, presunção e prova em contrário,
   virada2026/2027 e limites de data. Preservar chamadas históricas/replays.
5. Só depois integrar política versionada, fatos/perguntas, decisões nativas
   nos dois runners, corpus de reconciliação e regressão integral.

Este documento é pesquisa e especificação de trabalho, não aceitação do
requisito4B.1, nem autorização documental, de apropriação ou produção. Runtime,
catálogo ativo e inventário original de80 requisitos não foram alterados por81.

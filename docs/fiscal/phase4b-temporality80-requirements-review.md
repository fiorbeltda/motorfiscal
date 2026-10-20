# Revisão 80 — oito requisitos originais de temporalidade

Revisão de evidência em 15/10/2026, sem mudar o runtime da suíte78. Não substitui
a matriz integral nem marca a Fase4B como concluída. O inventário original de
80 itens permanece intacto. Foram confrontados os oito enunciados de4B.1 com
código, catálogo, testes e os limites das execuções já registradas.

Relatório exclusivo:
`D:/ProjetoSaasExports/phase4b-temporality80-requirements-review-20261015/requirements-temporality-review.json`.
O relatório identifica os arquivos/hashes examinados, quais continuam idênticos
à base76 aceita, e a regressão dirigida atual100/550. A suíte completa78 ainda
está em andamento; o relatório não converte sua expectativa em aceitação.

## Leitura de origem

Pesquisa36 foi lida integralmente, incluindo as revisões finais, e preservada
por hash no diretório do relatório: SHA-256
`a5a6094584ce78663719c916770a368c89219c338a195db897d19f912d576530`.
É pesquisa fornecida pelo projeto, data-base14/09/2026, não uma fonte normativa
atual capturada nesta revisão. Afirmações legais novas nela encontradas não
foram ativadas sem confronto posterior com fonte primária e temporalidade.
Partes sobre UI, boleto, tributos próprios e outros regimes não ampliam
automaticamente o escopo autorizado de4B.

## Situação por requisito, sem aceitação integral

| Requisito original | Evidência delimitada | O que ainda não prova |
| --- | --- | --- |
| 4B.1.1 Fases2026–2033 | 2.922 dias do catálogo têm uma única fase ativa; há caminhos históricos e pós-ISS distintos | Cálculo e documentação completos em todos os períodos |
| 4B.1.2 Alíquotas de teste | Corpus/testes separam IBS/CBS nominal e dispensa de caixa em2026 | Aceitação de regime inteiro, obrigação documental ou produção |
| 4B.1.3 Redução/extinção gradual | Fórmula ISS referencia31/12/2028 e aplica fatores anuais | Ato municipal real de referência ou cálculo efetivo futuro autorizado |
| 4B.1.4 Convivência | Exclusões de base, metadados legados e ponte4A/pós-ISS | ICMS completo a partir de um fator ou conciliação integral entre anos |
| 4B.1.5 Data de cada decisão | Data RTC pode diferir da competência; existem caminhos especiais | Derivação geral por evento, continuidade e reconciliação adiantamento/final |
| 4B.1.6 Vigência/publicação/revogação/supersessão | Seletores explícitos, mais observação da fonte na eleição78 | Cobertura uniforme de toda camada material, documental e técnica |
| 4B.1.7 Bloqueio futuro/vencido | Recusas por taxa ausente/conflitante e filtros temporais | Corpus nativo de fronteiras para todas as regras ativas e exceções |
| 4B.1.8 Não selecionar só por ano | Datas completas, conhecimento timestamp e testes intradiários | Evento controlador qualificado em todos os ramos |

A contagem de2.922 dias é conferência de metadados executada pelo revisor;
não é execução fiscal nativa nem teste de cada dia no engine. Cada requisito
do relatório permanece `requirement_achieved=false` por exigir prova mais ampla
que o fragmento já observado. Isso não significa ausência de implementação.

## Duas distinções que mudam a próxima ação

Primeiro: `rtc.operation_date` é um fato explícito aceito pelo caminho geral.
A comparação com competência e a seleção de versões existem. Porém isso,
isoladamente, não demonstra a derivação da data a partir do término do
fornecimento, exigibilidade/pagamento de assinatura ou antecipação/acerto final.
É necessária prova específica desses eventos, não apenas mais exemplos com
uma data final já escolhida pelo cenário. A busca textual é só apoio; o achado
está limitado ao fluxo e ao registro de fatos inspecionados, não afirma ausência
universal de todos os mecanismos temporais no repositório.

Segundo: testes antigos de2033 usam um bundle anterior que recusa o fluxo
pós-ISS ainda não materializado naquele contrato. Outros testes posteriores
demonstram roteamento pós-ISS e integração4A com gates. Portanto, a recusa do
teste antigo não prova que o runtime atual inteiro carece da rota. Da mesma
forma, o teste posterior com taxas/leiaute sintéticos não prova disponibilidade
real desses parâmetros futuros.

O ensaio que rotula uma referência municipal como `OFFICIAL_ACT` ou
`OFFICIAL_FILE` constrói dados sintéticos. Ele prova seleção/formulação diante
do tipo de entrada, não autenticação de um ato de2028. A regra de não inventar
CBS futura, IBS jurisdicional ou referência ISS deve permanecer.

Antes de novas alterações, fechar a regressão78 e manter seus inputs intactos.
Depois, priorizar a qualificação dos eventos temporais e o corpus correspondente
sem retirar a frente documental de exportação ou as demais obrigações do plano.

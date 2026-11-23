# A162 — modelo legal simplificado do split payment

Frente 1, 23/11/2026. Continuidade A159–A161 para 4B.3.7, com interfaces de temporalidade,
responsabilidade, arredondamento e memória. A fase técnica padrão A158 permanece separada.
O requisito inteiro e a frente de 44 itens ainda não estão encerrados.

## Fonte e critério legal

Texto vigente consultado: [LC 214, art. 33, com alterações da LC 227/2026](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html).
O modelo aplica o procedimento aos dois tributos e distingue opção explícita, omissão da
identificação dos valores nominais e obrigatoriedade por ato conjunto qualificado. Não impõe
restrição geral do simplificado a adquirentes fora do regime regular. O percentual não é
limitado ao débito nominal da operação. Na apuração, débitos de adquirentes não regulares
do período têm prioridade; outros débitos entram após o encerramento. Segregação não gera
crédito ao adquirente regular nem é convertida automaticamente em extinção.

HTML recapturado em 23/11/2026, 3.095.827 bytes, SHA256
`dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`, igual ao preservado
no A157. Fonte integral, extração e recibo em
`D:/ProjetoSaasExports/front1-a162-20261123-source-v1`.

## Cálculo e qualificação

`SimplifiedSplitCalculation162`, contrato `RTC-SIMPLIFIED-SPLIT-CALCULATION-A162-V1`, exige:

- Origem, partes, operação/documento/pagamento, datas de originação/liquidação/conhecimento,
  valor da operação e base alocada com evidência. A base não pode superar o valor da operação.
- Um parâmetro identificado e versionado, ambiente Sandbox, vigência/publicação, revogação e
  supersessão, escopo por fornecedor/setor, papéis CGIBS/RFB e os dois percentuais.
- Regra de arredondamento explicitamente qualificada. A regra do manual da fase padrão não
  é adotada como regra automática do simplificado. Não há redistribuição arbitrária de centavos.
- Base temporal explícita do parâmetro: data da operação, originação ou liquidação financeira.
  Não se reutiliza um percentual apenas porque o ano da operação coincide com o de uma versão.
- Evidência da opção explícita ou ato conjunto. Obrigatoriedade exige adquirente não regular
  e ato qualificado; ausência de identificação de ambos os tributos é uma opção distinta.

Parâmetros documentais são aceitos apenas do catálogo vinculado ao bundle. Um campo de entrada
não pode autodeclarar um parâmetro oficial. Parâmetros sintéticos exigem origem de teste e
ficam identificados no relatório; percentuais zero e ausentes são estados diferentes.

## Livro de apuração

`SimplifiedSplitPeriod162`, contrato `RTC-SIMPLIFIED-SPLIT-PERIOD-A162-V1`, recebe um livro
mensal completo: débitos/aberturas e extinções anteriores, ordem documental comprovada,
recebimentos por tributo e eventos fornecidos da autoridade. As datas respeitam o corte de
conhecimento; não é usado dinheiro recebido depois de uma aplicação.

As propostas seguem a prioridade e a cronologia documental. A ordem fornecida tem evidência
própria e é conferida por data; o motor não escolhe um desempate por código do documento.
Propostas não alteram saldos de débito. Aplicações efetivamente fornecidas devem respeitar a
cabeça elegível, o saldo e os fundos disponíveis em cada instante.

Determinação de excesso e transferência são eventos distintos. Excesso não pode ser
reservado enquanto ainda há débito elegível em aberto; fundos reservados não voltam a financiar
aplicações. Transferência exige a determinação correspondente, valor disponível e beneficiário
fornecedor. Recibos e IDs repetidos, mistura de tributos e reuso do histórico são recusados.
Eventos com mesmo instante respeitam a sequência expressamente fornecida pelo livro.

O relatório separa recebido, aplicado por registros fornecidos, excesso determinado, transferido,
pendente, disponível e proposto. Nenhum evento é autenticado, transmitido ou lançado pelo motor.
O prazo legal está identificado; sua data ainda depende do pacote de calendário qualificado.

## Ativação e execução

Bundle próprio `consumption-phase4b-2026.11.23-a162-draft1.json`, derivado do A161, com gate
`simplified_split_a162`, cinco fatos/perguntas e controles versionados. Os três catálogos de
percentual, obrigatoriedade e implantação oficiais estão vazios. A pesquisa em fontes oficiais
não qualificou ato de percentual nos resultados consultados; isso não prova inexistência de
publicação. Nenhum percentual foi inventado como oficial.

Uma implantação padrão/fase 1 não habilita o simplificado. O motor exige implantação própria
da modalidade e vincula cálculo, regime, valor, documento e pagamento aos fatos. Quando há
livro, confere fornecedor, período e débito nominal do documento atual. O cálculo nominal IBS/CBS
não é reescrito pelo valor aproximado segregado. Dados sintéticos retêm a decisão final.

```text
python scripts/fork_phase4b_a162_simplified_bundle.py --php C:/tools/php85/php.exe
php artisan fiscal:rtc:assess-simplified-split input.json --mode=calculation
php artisan fiscal:rtc:assess-simplified-split book.json --mode=period
python scripts/agent_a/validate_split160.py --increment A162 --php C:/tools/php85/php.exe --output D:/ProjetoSaasExports/front1-a162-new-run
```

## Validação e continuidade

Regressão dirigida cumulativa: **449 testes/1.419 asserções**, zero falhas. Inclui 90 testes
próprios/167 asserções e compatibilidade A158–A161/A157/B34/B38/B39. Não somar contagens
sobrepostas. PHPStan global nível 8 em processo único sem erros; Pint aprovado.

Sete modos nativos com igualdade de bytes e replay, sete recusas por adulteração e 20
comparações independentes Python Decimal. Fonte, logs e entradas no D:, recibo compacto em
`agent-a/20261123/validation-A162.json`. Validação PHP 8.5 local; Docker indisponível.
Não substitui a regressão integral final da versão.

Segue o pacote de calendários/prazos; depois recolhimento pelo adquirente, combinados,
perfis restantes, temporalidade e base/memória. Os 44 IDs continuam preservados na auditoria
`agent-a/20261123/requirements44-current.json`. Nenhum grupo formalmente encerrado e nenhuma
liberação de produção por este incremento.

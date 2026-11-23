# A161 — múltiplos pagamentos do mesmo boleto

Frente 1, 23/11/2026. Continuidade A159/A160 para 4B.3.7, com interfaces de temporalidade,
arredondamento e memória. Este incremento não encerra o split nem a frente de 44 requisitos.

## Resultado funcional

Um boleto ocupa uma parcela no cronograma, mesmo tendo vários pagamentos. Cada pagamento
tem ID e recibo próprios, snapshot qualificado A158/A160 e instante próprio de congelamento
e liquidação. A classificação de pagamento integral é fornecida com evidência de encerramento;
o motor não a deduz da soma dos valores. Pagamentos posteriores ao integral são recusados.

A segregação é calculada e truncada separadamente em cada pagamento e em cada tributo.
Exemplo sintético: pagamentos de 33,33 e 66,67 sobre original 100,00, com IBS 10,00 e CBS
20,00, segregam IBS 9,99 e CBS 19,99 no total. Os dois centavos não são redistribuídos.
O motor não subtrai uma segregação anterior do valor em aberto recebido da plataforma nem
equipara segregação à extinção do débito.

Os snapshots do mesmo boleto devem preservar continuidade fiscal. Uma mudança nominal entre
pagamentos exige histórico A160 compatível com o snapshot anterior; revisões retroativas ou
reescrita de versões já fornecidas são recusadas. Uma revisão posterior não modifica a memória
do pagamento já congelado. Identidade de operação/documento/partes/PSP/parcela permanece vinculada.

O snapshot corrente é não financeiro e serve à qualificação do estado atual; não cria outro
pagamento. A aplicação ao débito ainda depende de registros próprios da autoridade. Cancelamento
posterior a pagamentos parciais não apaga os cálculos anteriores. Troca do documento entre
snapshots financeiros exige conciliação documental própria; documentos divergentes não são
aceitos silenciosamente como a mesma operação.

## Livro, informes e ativação

- Envelope local `RTC-SPLIT-BOLETO-PAYMENTS-A161-V1`, com snapshot corrente e histórico completo.
- Livro `RTC-SPLIT-LEDGER-A161-V1`: cronograma com parcelas futuras A159, transações A158/A160
  e boletos A161. Principal e débito da parcela são alocados uma única vez.
- Informes de boletos A161 usam `payment_references` com `transaction_id` e `payment_id`.
  O identificador do boleto isolado é ambíguo. Formatos misturados e pagamentos desconhecidos,
  repetidos ou ainda não liquidados são recusados. Transações antigas mantêm `transaction_ids`.
- A conciliação usa o valor e a data daquele pagamento; uma referência não pode constar em
  dois informes. Repasse, aplicações, excesso e saldo continuam separados por IBS/CBS.
- Política exata A161 e bundle derivado do A160:
  `bundles/consumption-phase4b-2026.11.23-a161-draft1.json`.
- Definição ARRAY reutilizada; pergunta, catálogo derivado e controles próprios versionados.
  O catálogo de implantação não muda. Contratos e políticas históricos continuam disponíveis.

```text
php artisan fiscal:rtc:assess-split-payment input.json --mode=boleto
php artisan fiscal:rtc:assess-split-payment ledger.json --mode=payment-ledger
python scripts/fork_phase4b_a161_boleto_bundle.py
python scripts/agent_a/validate_split160.py --increment A161 --php C:/tools/php85/php.exe --output D:/ProjetoSaasExports/front1-a161-new-run
```

## Fontes e validação

[Manual de Integração 1.1.1](https://cgibs.gov.br/upload/arquivos/202609/24145324-split-payment-manual-de-integracao-v1-1-1.zip),
página 10, campo `indPgtoIntegral`: recapturado em 23/11/2026, ZIP SHA256
`a9a53f90431d9054d0d1ffec75fbd8b17c1686ad37501dbca663e4db5260ef67`, igual ao preservado.
O PDF também coincide com o hash anterior; página 10 renderizada e inspecionada.
Operações 1.0.1, §§5.1–5.3, foi recapturado no A160. São contratos locais de qualificação;
não se apresentam IDs locais ou hashes como autenticação da plataforma pública.

Testes cobrem componente, continuidade fiscal, duplicidades, encerramento, referências de
informes, repasses/aplicações e duas rotas do motor com bundle instalado. A regressão cumulativa
inclui A158/A159/A160/A161/A157/B34/B38/B39. Cinco modos nativos repetem bytes e replay;
há cinco recusas por adulteração e oráculos Decimal para segregação e saldo.

Registros completos no D:, com recibo compacto em `agent-a/20261123/validation-A161.json`.
PHPStan global em processo único, Pint e console são verificados. Validação em PHP 8.5 local;
Docker indisponível. Esta regressão é dirigida, não a regressão integral da Fase 4B.

Pendências seguintes: modelo legal simplificado com parâmetros explícitos, calendários e
prazos qualificados; depois os demais pacotes da frente 1. Implantação oficial, autenticação
remota e liberação de produção não são presumidas. Os 44 requisitos seguem em auditoria;
nenhum grupo formalmente encerrado.

Resultado final dirigido: **359 testes/1252 asserções**, zero falhas; PHPStan global
sem erros, Pint aprovado, cinco modos CLI com igualdade de bytes/replay, cinco recusas de
adulteração e 12 comparações Decimal aprovadas. Evidências em
`D:/ProjetoSaasExports/front1-a161-20261123-final-v1`.

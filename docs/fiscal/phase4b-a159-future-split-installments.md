# A159 — split payment com parcelas futuras não iniciadas

Frente 1, 23/11/2026. Base `f8f22d8`, bundle predecessor B38. Incremento do requisito
4B.3.7, sem encerrar o requisito ou qualquer grupo da Fase 4B.

O cronograma pode agora fechar o principal e os débitos da operação com transações já
iniciadas e parcelas futuras ainda não iniciadas. A parcela futura é uma alocação fornecida:
não precisa de uma iniciação ou liquidação fictícia para completar o livro.

## Contratos e ativação

- `RTC-SPLIT-LEDGER-A158-V1` e as rotas anteriores continuam disponíveis, com replay próprio.
- `RTC-SPLIT-LEDGER-A159-V1`, selecionado explicitamente por `reconcileSchedule`, mantém
  as guardas de identidade, conhecimento, cronograma, valores, informes, repasses e aplicações.
- Uma entrada futura usa `RTC-SPLIT-PLANNED-INSTALLMENT-A159-V1`. Exige a mesma operação,
  documento e partes, identificador reservado localmente, valor positivo, IBS/CBS, número e
  quantidade de parcelas, prova de alocação, origem/hash e data de iniciação planejada posterior
  ao conhecimento. O identificador reservado não comprova existência na plataforma pública.
- O contrato futuro tem campos fechados: não aceita iniciação, liquidação, antecipação ou
  retornos da plataforma. Sua avaliação é `NOT_INITIATED`, sem segregação ou extinção.
- O livro continua exigindo todos os números de parcelas, sem duplicidade, e fechamento
  integral do principal e dos dois débitos. Não escolhe centavos residuais.
- Informes e repasses só podem referenciar transações com liquidação qualificada. Parcelas
  futuras não podem ser a transação de pagamento âncora dos fatos do motor.

O bundle `consumption-phase4b-2026.11.23-a159-draft1.json` deriva do B38. Atualiza apenas
o catálogo derivado de códigos, a pergunta do livro e os controles de produção. Reutiliza as
definições tipadas de fatos: nenhum fato adicional é necessário para o mesmo envelope ARRAY.
A política A159 seleciona o contrato novo; uma política B34 recusa esse envelope. O catálogo
de transição e a ausência de implantação oficial permanecem preservados. Nos testes de cálculo,
a implantação sintética existe apenas em memória, e a decisão final fica retida como sintética.

O relatório conserva o status de conciliação local e acrescenta contagens separadas de parcelas
planejadas e transações iniciadas. O status do cronograma descreve fechamento/alocação fornecida;
não declara que todas as parcelas foram pagas. Uma nova captura pode substituir a parcela
planejada por uma transação A158, mantendo os snapshots anteriores reproduzíveis.

## Execução reproduzível

```text
php artisan fiscal:rtc:assess-split-payment input.json --mode=schedule
python scripts/fork_phase4b_a159_schedule_bundle.py
python scripts/agent_a/validate_split159.py --php C:/tools/php85/php.exe --output D:/ProjetoSaasExports/front1-a159-new-run
```

A validação usa diretório novo no D:, armazenamento Laravel temporário independente, testes
dirigidos de A158/A159/B34/A157/B38/B39, PHPStan global, Pint, verificador do console, duas
execuções nativas da CLI com igualdade de bytes e replay, recusa por adulteração e seis
comparações independentes com Python Decimal. Evidências históricas não são regravadas.

## Fontes e limites

Em 23/11/2026 foi consultado o [índice oficial do CGIBS](https://cgibs.gov.br/split-payment):
ele lista Operações 1.0.1 e Tempos 1.0.1, além de Integração/OpenAPI 1.1.1. Essa observação
do índice não é uma nova conferência byte a byte dos manuais. Os hashes técnicos fixados no
A158 permanecem preservados; nenhuma interpretação nova de prazos, percentuais ou API é ativada.
A separação local entre alocação futura e evento financeiro evita inferir recolhimento antes
da liquidação, sem atribuir ao contrato local autoridade externa.

Continuam pendentes: revisões fiscais/reset de retornos, múltiplos pagamentos do mesmo boleto,
modelo legal simplificado, calendário qualificado e implantação oficial. O Docker está
indisponível neste ambiente; a validação deste corte usa PHP 8.5 local. A regressão dirigida
não substitui a regressão integral final nem o aceite individual dos 80 requisitos.

Resultado final: **276 testes/1.066 asserções**, zero falhas; PHPStan global sem erros;
Pint nos oito arquivos PHP aprovado; duas CLI idênticas com replay e recusa por adulteração;
seis comparações Decimal aprovadas. Console: 28 verificações e endpoint HTTP 200 em servidor
PHP 8.5 local, com fases 3/4A encerradas, 4B em andamento e 5–7 planejadas. Recibo compacto:
`docs/fiscal/agent-a/20261123/validation-A159.json`; logs e entradas no
`D:/ProjetoSaasExports/front1-a159-20261123-final-v1`. A primeira tentativa de regressão
falhou ao gravar a matriz histórica B39 no C: (errno 28); a matriz foi restaurada byte a byte
da base limpa e as execuções posteriores usaram armazenamento independente no D:.

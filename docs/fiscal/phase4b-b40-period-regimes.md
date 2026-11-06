# B40: apuração periódica dos regimes de planos de saúde e de prognósticos (06/11/2026)

Item 4B.4.10 (regimes específicos). Nesses regimes, a NFS-e de cada operação é **informativa**: CST 820, com o tributo
declarado no regime específico. O IBS e a CBS são apurados por período, pelo regime de caixa.

## Fonte

LC 214/2025, arts. 234–247, no texto compilado do Planalto observado em 03/11/2026 (SHA256 `0a0d190b…4673c`).

| Regime | Base | Alíquota | Documento por operação |
|---|---|---|---|
| Planos de saúde (`HEALTH_PLAN`) | (+) prêmios e contraprestações recebidos e receitas financeiras das reservas efetivamente liquidadas (art. 235, I); (−) indenizações pagas, cancelamentos e restituições, intermediação, taxa de administração (art. 235, II) e corresponsabilidade cedida (§2º) | Referência de cada esfera reduzida em 60% (art. 237) | cClassTrib 820001 |
| Planos de saúde de animais (`PET_HEALTH_PLAN`) | Igual à anterior (art. 243) | Referência reduzida em 30% (art. 243) | cClassTrib 820003 |
| Concursos de prognósticos (`BETTING`) | Arrecadação, menos prêmios pagos e destinações legais (art. 245) | Soma das alíquotas de referência (art. 246) | cClassTrib 820004 |

## Regras aplicadas

- **Regime de caixa:** só contam valores recebidos ou pagos dentro do período (`cash_date` no mês).
- **Receita financeira das reservas:** só entra quando cumpre três condições:
  - o ativo foi liquidado ou resgatado;
  - a provisão técnica foi reduzida (art. 235, §4º);
  - a receita está vinculada aos prêmios (art. 235, §6º).
- **Lançamentos por regime:** cada regime só aceita os tipos de lançamento previstos na lei. Por exemplo, prêmio de
  aposta lançado em plano de saúde é recusado.
- **Crédito:** adquirentes e apostadores não têm crédito (arts. 238, 243 e 247).
- **Alíquotas de referência:** entram como dado de entrada, com fonte e hash; nada é estimado. Nos testes, as alíquotas
  de teste de 2026 aparecem rotuladas como fixture.
- **Base negativa no período:** os arts. 234–247 não dão tratamento a ela, então é recusada.

## Implementação

`PeriodRegimeAssessmentB40.php` (contrato `RTC-PERIOD-SPECIFIC-REGIME-B40-V1`):
- é um componente independente, com replay por hash;
- não muda o motor de cálculo por documento.

## Fora desta etapa

- **Planos de assistência funerária (art. 236):** seguem as regras dos planos de saúde, mas não têm cClassTrib de
  documento próprio no catálogo.
- **Ligação com a declaração periódica oficial:** o leiaute oficial ainda não foi publicado.

## Testes

`tests/Fiscal/Unit/PeriodRegimeAssessmentB40Test.php` tem 12 testes:
- plano de saúde: base de 3.000,00, com CBS 10,80 e IBS 1,20;
- corresponsabilidade cedida;
- plano de animais e prognósticos;
- oito recusas;
- replay adulterado.

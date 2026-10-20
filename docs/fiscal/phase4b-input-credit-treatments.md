# Crédito do adquirente por tratamento da aquisição

Corte: `CONSUMPTION-PHASE4B-2026.10.02-1`, `SANDBOX_CANDIDATE`. Qualificação separada de IBS/CBS nos arts. 47, 49 e 50 da [LC 214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), conferida em 02/10. O HTML preservado em 01/10 mantém SHA-256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.

## Contrato

Para `rtc.credit_context=INPUT` e adquirente regular, exige `rtc.input_tax_treatments` e `rtc.presumed_credit_requested`. O array tem uma linha de IBS e uma de CBS, com `tax`, `treatment`, `document_id` e `evidence_id`. O documento precisa ser o da aquisição. A entrada é classificada documentalmente, independente do tratamento da saída; o qualificador não homologa a imunidade ou o benefício de uma saída.

| Tratamento da entrada | Crédito ordinário do adquirente |
|---|---|
| Padrão ou redução de 30%/60% | Segue documento, extinção, regime e ajustes próprios |
| Imunidade, isenção, alíquota zero ou diferimento | Negado; pedido positivo contraditório invalidado |
| Suspensão sem exigência posterior | Negado |
| Suspensão exigida sem principal extinto | Condicional, valor zero |
| Suspensão exigida com principal extinto | Limitado ao principal extinto; acréscimos excluídos |

O tratamento pode diferir entre os tributos. O exemplo assimétrico mantém IBS de entrada de R$2,00 e nega CBS de entrada sob alíquota zero. O débito da saída continua IBS R$0,40/CBS R$3,60. Não há compensação entre tributos ou inferência de crédito pelo débito de saída.

Na suspensão exigida, registra `suspension_demanded`, `demanded_principal`, `extinguished_principal`, `legal_additions` e prova da exigência. Principal extinto positivo exige prova e data da extinção conciliadas com a data avaliada e o conhecimento. Não pode superar o principal exigido; o pedido não pode superar o extinto. CBS exigida de R$27,00, extinta em R$18,00 e acréscimos de R$2,25 permitem no máximo R$18,00, ainda sujeitos aos ajustes comprovados.

Valores negativos, precisão incompatível, duplicidade, documento divergente, prova vazia ou extinção futura impedem qualificação. Pedido presumido retorna `RTC_PRESUMED_CREDIT_RULE_NOT_PROVEN` até existir regra específica. A regra verifica disponibilidade, vigência, revogação, supersessão e fonte. Bundles históricos não recebem o contrato novo.

## Integração e evidências

`InputCreditTreatmentQualifier` participa da avaliação RTC e da rota de crédito da importação; `CreditDecisionEngine` mantém os ajustes documentais. Os novos integrados usam ISS sintético de R$50,00 e 4A `NOT_REQUESTED`; não homologam fonte municipal ou retenções de saúde. A projeção preserva NBS `123012100`, sem transformar crédito da entrada em imposto da saída.

No PHP 8.5 do Docker:

```powershell
python scripts/fork_phase4b_input_credit_bundle.py
python scripts/verify_phase4b_input_credit_cut.py
docker compose exec -T app php artisan fiscal:rtc:production-assess --target=local-validation
```

Os scripts recusam sobrescrever artefatos diferentes. Os goldens são registrados depois de conferir resultados e status previamente especificados. Evidências: `storage/app/phase4b-input-credit-*-20261002-1-*` e `phase4b-production-assessment-20261002-1.json`.

| Integrado | Hash da decisão |
|---|---|
| Assimétrico | `ffe5346880939aef13496ecd4c36070e49e6c556000badc0524b7fa60c6d4b20` |
| Suspensão/principal extinto | `28b4911daa4271abd1a64f9a79c2dba3b3e3311850e6d666bad32e16382c72e0` |

Validação final: **305 testes / 1.303 asserções**, PHPStan sem erros, Pint em oito arquivos; corpus **18/18 RTC e 2/2 integrados**. Os dois snapshots novos e os dez predecessores reproduzem os hashes. Regressão dos anexos: 16/16 RTC, 3/3 integrados; corpus histórico: 53/53. O teste de importação confirma crédito negado sem exigir extinção de débito inexistente; crédito tributado mantém essa prova obrigatória.

Limites: crédito presumido específico, anulação proporcional das saídas imunes/isentas e manutenção do crédito do fornecedor nos arts. 51/52 continuam pendentes. Negar crédito ao adquirente não implementa a apuração periódica do fornecedor nem o cálculo integral dos regimes de saída. Gate: 18/18 fontes íntegras, elegível local, `ready=false`, 113 bloqueios. Nenhum controle aprovado ou bundle publicado.

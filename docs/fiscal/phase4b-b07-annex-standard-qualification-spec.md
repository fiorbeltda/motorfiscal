# B07 — Especificação da Q-B06-1 (alternativa B aprovada) — Agente B, 03/11/2026

Decisão do usuário (03/11/2026): alternativa B, com critérios adicionais. **O Agente A implementa** a seleção de alíquota
em `ConsumptionReformEngine.php` (escritor único). O Agente B especifica, prepara os testes e valida a integração.
Esta especificação não aprova as 40 regras e não autoriza publicação. Base `87d22e7` + B01–B06 locais, sem commit.

## 1. Fundamento jurídico (fontes preservadas com hash)

- LC 214/2025, **art. 129**: "Ficam reduzidas em 60% (sessenta por cento) as alíquotas do IBS e da CBS incidentes sobre o
  fornecimento dos serviços de educação relacionados no Anexo II desta Lei Complementar, com a especificação das respectivas
  classificações da [NBS]." **Art. 130**: idem para os serviços de saúde do Anexo III (texto do Planalto obtido em
  2026-11-03T12:44:32Z, `resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm`, SHA-256 `0a0d190b…673c`).
- Os conjuntos de NBS dos Anexos II (7 códigos) e III (21 códigos) do Planalto são idênticos aos do catálogo
  (`annex_service_reduction`, fonte `LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001`, `dc87a43e…2bde`).
- cClassTrib (IT 2025.002 v1.70): `200028` (Art. 129, Anexo II), `200029` (Art. 130, Anexo III), `000001` (tributação integral).
- Consequência: a lei não traz opção pela tributação integral para o serviço enquadrado; o enquadramento depende da
  **descrição do item** e da **NBS**. Declarar "integral" não afasta a redução.

## 2. Comportamento histórico observado (caracterização, bundle 141)

Para as 40 regras, `rtc.tax_treatment=STANDARD` com NBS de anexo resolve `000001`, **mesmo com
`rtc.annex_service_description_confirmed=true`** (contradição aceita). Teste:
`tests/Fiscal/Integration/AnnexStandardQualificationB07Test.php`, conjunto "characterizes the historical gap" (40/40).
Esse comportamento deve permanecer **inalterado nos bundles sem o gate** (replays e hashes aceitos).

## 3. Contrato

- Gate: `App\Modules\Fiscal\ConsumptionReform\AnnexStandardQualificationPolicyB07::required($this->codes)`; chave do
  catálogo `annex_standard_qualification_b07` com o bloco exato `POLICY`. Nenhum catálogo publicado o carrega.
- Condição de aplicação: gate ativo **e** `rtc.tax_treatment=STANDARD` **e** a família do serviço é de anexo (regra com
  `annex_service_category`) ou `rtc.service_nbs_code` está listado num item dos Anexos II/III.
- Fatos novos (definições e perguntas a incluir na nova versão de bundle, preparada por B após o patch de A):

| Fato | Tipo | Pergunta (resumo) |
|---|---|---|
| `rtc.annex_noncorrespondence_justification` | STRING | Por que o serviço concreto não corresponde à descrição do item do anexo? |
| `rtc.annex_noncorrespondence_evidence_id` | STRING | Evidência material da não correspondência |
| `rtc.annex_nbs_classification_confirmed` | BOOLEAN | A NBS informada foi conferida e está correta para o serviço? |

Fatos existentes reutilizados: `rtc.annex_service_description_confirmed`, `rtc.service_nbs_code`, `rtc.service_nbs_evidence_id`.

## 4. Tabela de decisão (ordem de avaliação)

| # | Condição (com gate ativo e STANDARD em família/NBS de anexo) | Resultado | Motivo |
|---:|---|---|---|
| 1 | `rtc.service_nbs_code` ausente | ACTION_REQUIRED (pergunta) | `RTC_ANNEX_SERVICE_NBS_REQUIRED` |
| 2 | NBS informada **fora** dos anexos | gate não se aplica; comportamento histórico | — |
| 3 | `annex_service_description_confirmed=true` | INVALID | `RTC_ANNEX_STANDARD_CONTRADICTS_CONFIRMED_DESCRIPTION` |
| 4 | descrição não informada, ou justificativa/evidência/conferência da NBS ausentes | ACTION_REQUIRED, com `missing_facts` | `RTC_ANNEX_NONCORRESPONDENCE_PROOF_MISSING` |
| 5 | justificativa vazia ou só espaços | ACTION_REQUIRED | `RTC_ANNEX_NONCORRESPONDENCE_JUSTIFICATION_INSUFFICIENT` |
| 6 | `annex_nbs_classification_confirmed=false` | ACTION_REQUIRED (corrigir a NBS antes de decidir) | `RTC_ANNEX_NBS_RECLASSIFICATION_REQUIRED` |
| 7 | descrição=false + justificativa + evidência + NBS conferida | RESOLVED STANDARD (`000001`), com trace `ANNEX_NONCORRESPONDENCE_QUALIFIED` | — |

- `REDUCED_60` continua exigindo a prova atual do `AnnexServiceReductionQualifier` (sem mudança).
- **Nunca**: selecionar STANDARD por padrão, nem presumir REDUCED_60 sem a prova do anexo.

## 5. Exemplos (do construtor de testes)

- Positivo reduzido: 04.01, Anexo III item 7, NBS `1.2301.21.00`, presença física → `040101.PERSON_PRESENCE.REDUCED_60-1`, cClassTrib `200029`.
- Positivo integral qualificado: mesmo serviço, STANDARD + descrição=false + justificativa "Serviço contratado não corresponde à
  descrição do item do anexo: ver laudo." + evidência + NBS conferida → `000001`.
- Negativo: STANDARD + descrição=true → INVALID (contradição). STANDARD sem justificativa → pergunta.

## 6. Testes preparados (lado B)

- `tests/Fiscal/Unit/AnnexStandardQualificationPolicyB07Test.php`: gate desligado em todos os catálogos; bloco exato; oráculo
  alinhado ao contrato.
- `tests/Fiscal/Integration/AnnexStandardQualificationB07Test.php`: 40 caracterizações (rodam agora) + **320 casos**
  (40 regras × 8 casos: enquadramento correto, sem justificativa, contradição, justificado, justificativa insuficiente,
  reclassificação de NBS, NBS ausente, serviço fora do anexo). Os 320 ficam pendentes (skip) até existir bundle com o gate.
  No caso "fora do anexo", o resultado deve ser idêntico ao histórico.
- Suporte: `tests/Fiscal/Support/b07/AnnexStandardSpecB07.php` (construtor de cenários e oráculo).

## 7. Impactos e passos de integração

1. A aplica o patch em `ConsumptionReformEngine.php` (seleção de tratamento) usando o gate, sem alterar o comportamento sem gate.
2. B cria a nova versão de catálogo/bundle com o bloco `annex_standard_qualification_b07`, os 3 fatos e as perguntas.
3. B executa os 320 casos e as suítes de regressão dirigidas; só então as 40 regras podem ser consideradas tecnicamente resolvidas.
4. A decisão tributária muda o hash de decisões novas (versão nova de bundle). Replays e hashes antigos permanecem.
5. Casos de interpretação ainda controversa (por exemplo, serviço com componentes mistos) seguem sinalizados para revisão especializada.

## 8. As 40 regras e o tratamento esperado

| # | Regra STANDARD (hoje) | Item LC116 | Anexo / item | NBS (prefixo) | Destino | Regra reduzida gêmea | Tratamento esperado com B07 |
|---:|---|---|---|---|---|---|---|
| 1 | `040101.ACQUIRER_DOMICILE.STANDARD-1` | 04.01 (040101) | Anexo III itens 7,8 | 1.2301.21.00, 1.2301.22.00 | ACQUIRER_DOMICILE | `040101.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 2 | `040101.PERSON_PRESENCE.STANDARD-1` | 04.01 (040101) | Anexo III itens 7,8 | 1.2301.21.00, 1.2301.22.00 | PERSON_PRESENCE | `040101.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 3 | `040301.ACQUIRER_DOMICILE.STANDARD-1` | 04.03 (040301) | Anexo III itens 1,2,3,4,5,6 | 1.2301.1 | ACQUIRER_DOMICILE | `040301.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 4 | `040301.PERSON_PRESENCE.STANDARD-1` | 04.03 (040301) | Anexo III itens 1,2,3,4,5,6 | 1.2301.1 | PERSON_PRESENCE | `040301.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 5 | `040302.ACQUIRER_DOMICILE.STANDARD-1` | 04.03 (040302) | Anexo III itens 12 | 1.2301.93.00 | ACQUIRER_DOMICILE | `040302.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 6 | `040302.PERSON_PRESENCE.STANDARD-1` | 04.03 (040302) | Anexo III itens 12 | 1.2301.93.00 | PERSON_PRESENCE | `040302.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 7 | `040401.ACQUIRER_DOMICILE.STANDARD-1` | 04.04 (040401) | Anexo III itens 24 | 1.2301.99.00 | ACQUIRER_DOMICILE | `040401.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 8 | `040401.PERSON_PRESENCE.STANDARD-1` | 04.04 (040401) | Anexo III itens 24 | 1.2301.99.00 | PERSON_PRESENCE | `040401.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 9 | `040601.ACQUIRER_DOMICILE.STANDARD-1` | 04.06 (040601) | Anexo III itens 10 | 1.2301.91.00 | ACQUIRER_DOMICILE | `040601.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 10 | `040601.PERSON_PRESENCE.STANDARD-1` | 04.06 (040601) | Anexo III itens 10 | 1.2301.91.00 | PERSON_PRESENCE | `040601.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 11 | `040701.ACQUIRER_DOMICILE.STANDARD-1` | 04.07 (040701) | Anexo III itens 26 | 1.2301.99.00 | ACQUIRER_DOMICILE | `040701.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 12 | `040701.PERSON_PRESENCE.STANDARD-1` | 04.07 (040701) | Anexo III itens 26 | 1.2301.99.00 | PERSON_PRESENCE | `040701.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 13 | `040802.ACQUIRER_DOMICILE.STANDARD-1` | 04.08 (040802) | Anexo III itens 11 | 1.2301.92.00 | ACQUIRER_DOMICILE | `040802.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 14 | `040802.PERSON_PRESENCE.STANDARD-1` | 04.08 (040802) | Anexo III itens 11 | 1.2301.92.00 | PERSON_PRESENCE | `040802.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 15 | `040803.ACQUIRER_DOMICILE.STANDARD-1` | 04.08 (040803) | Anexo III itens 21 | 1.2301.99.00 | ACQUIRER_DOMICILE | `040803.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 16 | `040803.PERSON_PRESENCE.STANDARD-1` | 04.08 (040803) | Anexo III itens 21 | 1.2301.99.00 | PERSON_PRESENCE | `040803.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 17 | `041001.ACQUIRER_DOMICILE.STANDARD-1` | 04.10 (041001) | Anexo III itens 22 | 1.2301.99.00 | ACQUIRER_DOMICILE | `041001.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 18 | `041001.PERSON_PRESENCE.STANDARD-1` | 04.10 (041001) | Anexo III itens 22 | 1.2301.99.00 | PERSON_PRESENCE | `041001.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 19 | `041201.ACQUIRER_DOMICILE.STANDARD-1` | 04.12 (041201) | Anexo III itens 9 | 1.2301.23.00 | ACQUIRER_DOMICILE | `041201.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 20 | `041201.PERSON_PRESENCE.STANDARD-1` | 04.12 (041201) | Anexo III itens 9 | 1.2301.23.00 | PERSON_PRESENCE | `041201.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 21 | `041601.ACQUIRER_DOMICILE.STANDARD-1` | 04.16 (041601) | Anexo III itens 17 | 1.2301.98.00 | ACQUIRER_DOMICILE | `041601.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 22 | `041601.PERSON_PRESENCE.STANDARD-1` | 04.16 (041601) | Anexo III itens 17 | 1.2301.98.00 | PERSON_PRESENCE | `041601.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 23 | `041701.ACQUIRER_DOMICILE.STANDARD-1` | 04.17 (041701) | Anexo III itens 27 | 1.2302 | ACQUIRER_DOMICILE | `041701.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 24 | `041701.PERSON_PRESENCE.STANDARD-1` | 04.17 (041701) | Anexo III itens 27 | 1.2302 | PERSON_PRESENCE | `041701.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 25 | `041704.ACQUIRER_DOMICILE.STANDARD-1` | 04.17 (041704) | Anexo III itens 27 | 1.2302 | ACQUIRER_DOMICILE | `041704.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 26 | `041704.PERSON_PRESENCE.STANDARD-1` | 04.17 (041704) | Anexo III itens 27 | 1.2302 | PERSON_PRESENCE | `041704.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 27 | `041901.ACQUIRER_DOMICILE.STANDARD-1` | 04.19 (041901) | Anexo III itens 14 | 1.2301.95.00 | ACQUIRER_DOMICILE | `041901.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 28 | `041901.PERSON_PRESENCE.STANDARD-1` | 04.19 (041901) | Anexo III itens 14 | 1.2301.95.00 | PERSON_PRESENCE | `041901.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 29 | `042101.ACQUIRER_DOMICILE.STANDARD-1` | 04.21 (042101) | Anexo III itens 15 | 1.2301.96.00 | ACQUIRER_DOMICILE | `042101.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 30 | `042101.PERSON_PRESENCE.STANDARD-1` | 04.21 (042101) | Anexo III itens 15 | 1.2301.96.00 | PERSON_PRESENCE | `042101.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 31 | `080101.ACQUIRER_DOMICILE.STANDARD-1` | 08.01 (080101) | Anexo II itens 1,2,3 | 1.2201 | ACQUIRER_DOMICILE | `080101.ACQUIRER_DOMICILE.REDUCED_60-1` (200028) | Redução 60% (200028) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 32 | `080101.PERSON_PRESENCE.STANDARD-1` | 08.01 (080101) | Anexo II itens 1,2,3 | 1.2201 | PERSON_PRESENCE | `080101.PERSON_PRESENCE.REDUCED_60-1` (200028) | Redução 60% (200028) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 33 | `080102.ACQUIRER_DOMICILE.STANDARD-1` | 08.01 (080102) | Anexo II itens 6 | 1.2204 | ACQUIRER_DOMICILE | `080102.ACQUIRER_DOMICILE.REDUCED_60-1` (200028) | Redução 60% (200028) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 34 | `080102.PERSON_PRESENCE.STANDARD-1` | 08.01 (080102) | Anexo II itens 6 | 1.2204 | PERSON_PRESENCE | `080102.PERSON_PRESENCE.REDUCED_60-1` (200028) | Redução 60% (200028) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 35 | `080201.ACQUIRER_DOMICILE.STANDARD-1` | 08.02 (080201) | Anexo II itens 4,5,7,8 | 1.2202, 1.2203, 1.2205.13.00 | ACQUIRER_DOMICILE | `080201.ACQUIRER_DOMICILE.REDUCED_60-1` (200028) | Redução 60% (200028) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 36 | `080201.PERSON_PRESENCE.STANDARD-1` | 08.02 (080201) | Anexo II itens 4,5,7,8 | 1.2202, 1.2203, 1.2205.13.00 | PERSON_PRESENCE | `080201.PERSON_PRESENCE.REDUCED_60-1` (200028) | Redução 60% (200028) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 37 | `250101.ACQUIRER_DOMICILE.STANDARD-1` | 25.01 (250101) | Anexo III itens 30 | 1.2603.00.00 | ACQUIRER_DOMICILE | `250101.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 38 | `250101.PERSON_PRESENCE.STANDARD-1` | 25.01 (250101) | Anexo III itens 30 | 1.2603.00.00 | PERSON_PRESENCE | `250101.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 39 | `250202.ACQUIRER_DOMICILE.STANDARD-1` | 25.02 (250202) | Anexo III itens 30 | 1.2603.00.00 | ACQUIRER_DOMICILE | `250202.ACQUIRER_DOMICILE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |
| 40 | `250202.PERSON_PRESENCE.STANDARD-1` | 25.02 (250202) | Anexo III itens 30 | 1.2603.00.00 | PERSON_PRESENCE | `250202.PERSON_PRESENCE.REDUCED_60-1` (200029) | Redução 60% (200029) se enquadrado e provado; integral (000001) só com não correspondência qualificada; senão pergunta/bloqueio |

# Imunidade institucional integrada — ISS, RTC e 4A

Bundle candidato: `CONSUMPTION-PHASE4B-2026.10.02-6`, ainda `SANDBOX_CANDIDATE`. Acrescenta qualificação independente de ISS à imunidade RTC do predecessor `-4`.

## Qualificação e cálculo

`iss.evaluate_institutional_immunity=true` seleciona o fluxo. Além da elegibilidade institucional RTC do fornecedor/documento/período, exige `iss.institutional_immunity_proof`: vínculo às finalidades essenciais e evidências desse vínculo e do estado do benefício. Ausência de prova retorna `ACTION_REQUIRED`; titular/documento divergentes ou serviço sem vínculo essencial retornam `INVALID`. A imunidade RTC isolada não ativa a imunidade de ISS.

A política vincula CF150 VI b/c e §4 e CTN9/14. O município continua qualificado pela estratégia do serviço. Dispensa consulta à alíquota municipal: alíquota `null`, base e ISS `0.00`, retenção inaplicável. A invariante dispensa alíquota somente para incidência exatamente `IMMUNE`; ISS tributável sem alíquota continua inválido. Retenção de ISS efetuada é incompatível com o fluxo.

O recorte positivo é educacional080101, com quatro mapeamentos RTC candidatos e classes410006/410007. Outros serviços, imunidades públicas/por objeto e regimes restantes continuam na auditoria integral. O contrato conservador de observação das provas RTC do predecessor permanece.

## Documento e 4A

Contrato `DPS-LOCAL-2026.10.02-1`, XSD oficial v1.01: `tribISSQN=2`, `tpImunidade=2` para entidade religiosa ou3 para instituição elegível, `tpRetISSQN=1`, sem `pAliq`. Esses códigos não são intercambiáveis com v1.00. Preserva NBS e identidade comprovados; rejeita projeção adulterada com ISS positivo.

Dois casos mantêm 4A `NOT_REQUESTED`. O terceiro executa a4A para pagamento por pessoa física responsável pela criança destinatária: IRRF/PCC/INSS `NOT_APPLICABLE` pelas regras do pagador, total retido0, obrigação `NO_WITHHOLDING_EVENT`. Não infere imunidade federal pelo ISS. A classificação própria PIS/Cofins99 é fixture documental manual com evidência explícita, separada da retenção.

## Evidências

Constituição preservada: `cf1988-camara-observed-20261002.html`, SHA256 `654bbf1797b5624339021f53d5a8f612c917135f0c23a232c9bc39ce86828744`. CTN e LC214 mantêm os hashes do contrato RTC. Gate20/20 fontes, elegível local, `ready=false`,117 bloqueios e12 controles parciais/abertos.

Verificador: `scripts/verify_phase4b_integrated_immunity_cut.py`. Corpus11/11; três replays idênticos; três XMLs candidatos com somente o conflito oficial de série (`ACTION_REQUIRED`). Suíte completa363 testes/1.499 asserções; PHPStan sem erros.

| Caso | Hash da decisão |
|---|---|
| Educação sem fins lucrativos | `d3ff09b5fb508fe7c789a8d5e8483ab609cb7276ea69ae684bed6bf5aabb5fdf` |
| Entidade religiosa | `add9742b31ee6b88033417743ff2ee545349d17eacd8083d714b51e227e54035` |
| Pagador familiar com4A | `40c540d2f28ca03608993b97ec0f7ff5e67171fc21366df3e12c7ac20379d3f5` |

Artefatos: `storage/app/phase4b-integrated-institutional-immunity-*-20261002-6-*`, corpus `phase4b-integrated-institutional-immunity-corpus-20261002-6.json`, gate `phase4b-production-assessment-20261002-6.json`. Nenhum documento assinado/transmitido, recolhimento executado ou aprovação fabricada.

O rascunho imutável `-5` foi preservado com um enum INSS não registrado no cenário familiar. O `-6` corrige esse fato para `CONTRACT_FOR_RESULT`, sem reescrever o rascunho ou classificá-lo como corpus aprovado.

# Cooperativas: alíquota zero em serviços não financeiros

Bundle `CONSUMPTION-PHASE4B-2026.10.02-9`, `SANDBOX_CANDIDATE`. Contrato `LC214_ART271_COOPERATIVE_SERVICE_ZERO`, qualificador `CooperativeZeroRateQualifier`. Rotas: associado fornece serviço à cooperativa e cooperativa fornece serviço a associado sujeito ao regime regular. Prova array singleton `rtc.cooperative_zero_proof` vinculada às partes e ao documento.

## Qualificação

Exige cadastro da cooperativa, vínculo de associação comprovado, opção registrada/eficaz e evidência da validação regulamentar. Exige datas de exercício, efeitos e observação, início/fim da prova, natureza não financeira do serviço e contribuinte fornecedor regular. Opção ordinária deve ter sido exercida no ano anterior ao início de efeitos; o recorte de início de operações admite opção na mesma data de início, com prova regulamentar. Esse recorte não estabelece um prazo geral de um dia para todas as opções de início de atividade.

Ausência de prova ou condição desconhecida retorna `ACTION_REQUIRED`; partes, documento ou períodos conflitantes retornam `INVALID`. A saída da cooperativa para membro exige adquirente regular; não se concede zero por existir simplesmente uma cooperativa no cadastro.

## Cálculo e integração

Incidência permanece `TAXABLE`, mecanismo `ZERO_RATE`, regime `COOPERATIVE_ART271`. Para base1000, IBS estadual/municipal e CBS têm alíquotas efetivas0 e nominal0. A base permanece1000, distinta da imunidade. Alíquotas padrão, redução100 e efetivas ficam na memória/enriquecimento nacional. A causa é `RTC_COOPERATIVE_ZERO_RATE`.

Classificação: CST200/classe200020 do catálogo oficial preservado, com duas regras educacionais080101 próprias do motor para presencial/remoto. São `ENGINE_DECISION_RULE`, candidatas à revisão; não correlações oficiais. Não classificam automaticamente todos os serviços cooperativos.

O contrato explicita preservação dos créditos anteriores do fornecedor sujeita a prova, vedação do crédito ordinário do adquirente e ausência de transferência automática pelo art272. A memória não apropria ou transfere créditos. A qualificação de ISS e4A é independente. Cenários usam ISS municipal fixture50; três mantêm4A `NOT_REQUESTED`, um executa a4A para pagador associado pessoa física e retorna retenção0 pelas regras do pagador.

O contrato documental existente `DPS-LOCAL-2026.10.02-1` projeta CST/classe e NBS, preserva os valores de ISS decididos e mantém o enriquecimento RTC fora do emissor. A identidade fixture declara regime municipal0; o tratamento RTC não impõe automaticamente `regEspTrib` de ISS. O recorte documental não qualifica atos cooperados de ISS nem regimes municipais especiais.

## Fonte e evidências

Fonte: [LC214 consolidada, art271 I/II e §3](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`; classes do IT2025.002v1.60 preservado. Arts49/52 conservam efeitos distintos de crédito; não se inferiu isenção ou imunidade pelo zero.

Materializadores: `fork_phase4b_cooperative_zero_bundle.py` e `fork_phase4b_cooperative_payer_contract.py`. Verificador: `verify_phase4b_cooperative_zero_cut.py`. Corpus16/16, testes dirigidos16/60, PHPStan sem erros e Pint três arquivos. Regressão dirigida anterior65/216; corpus mensal20/20 e três hashes preservados. Última suíte completa385/1554 pertence ao predecessor `-7`; não foi apresentada como medição de `-9`.

| Snapshot | Hash da decisão |
|---|---|
| Cooperativa para membro | `0c544bea3e494450e41a2780882994c13a503ab69ff15e1475aec4fb67c17af3` |
| Membro para cooperativa | `99b9237d7e693d629921d0d523a4033f81415b573d841dd06a6a71fee2f4f07d` |
| Membro pessoa física com4A | `b292b5726fbc74e9785d784b13fc2be231228394dfeeeb18d2ecc2df98e53550` |

Três replays idênticos e três candidatos XML com somente conflito oficial de série, `ACTION_REQUIRED`. Artefatos `storage/app/phase4b-cooperative-zero-*-20261002-9-*`; corpus `phase4b-cooperative-zero-corpus-20261002-9.json`; gate `phase4b-production-assessment-20261002-9.json`:20/20 fontes, elegível local, `ready=false`,119 bloqueios. Os dois bloqueios adicionais são as duas novas regras sem revisão.

O rascunho8 foi preservado, incluindo dois snapshots positivos: seu caso individual omitia `payer.person_type` e foi corretamente bloqueado pela4A. O9 explicita o pagador. Financeiro, intercooperativas/bancos participantes, agropecuário, transferência272, opção fora do recorte materializado e demais requisitos da fase continuam pendentes. Nenhuma emissão, assinatura, transmissão, pagamento ou aprovação formal.

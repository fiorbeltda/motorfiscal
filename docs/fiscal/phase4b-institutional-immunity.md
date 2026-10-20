# Imunidade institucional nas saídas

Candidato `CONSUMPTION-PHASE4B-2026.10.02-4`, sujeito à revisão formal. O desenvolvimento integral da Fase 4B continua aberto.

## Qualificação

A prova `rtc.institutional_immunity_proof` é uma lista de exatamente um registro vinculado ao **fornecedor**, documento e período. Exige natureza jurídica privada sem fins lucrativos, categoria elegível, estatuto e provas da operação. O art.9 §4 da LC214 impede estender a imunidade institucional às aquisições da entidade; a condição do cliente não substitui a do fornecedor.

Entidade religiosa exige finalidade de professar a fé e praticar religião. Organização assistencial religiosa exige vínculo e manutenção pela entidade religiosa, assistência social e atendimento sem discriminação nem exigências aos assistidos. Partidos, seus institutos/fundações, sindicatos de trabalhadores e instituições de educação/assistência social exigem os requisitos cumulativos do CTN art.14: ausência de distribuição de patrimônio/renda, aplicação dos recursos no País e escrituração regular/exata. Os serviços precisam de relação direta aos objetivos estatutários; a aplicação do benefício não pode estar suspensa. Institutos/fundações partidários exigem prova do vínculo. Sindicato patronal não é categoria elegível.

A prova tem intervalo válido e data de observação, sem aceitação de observação futura. Falta de dados materiais exige ação; titular/documento divergentes, condições expressamente descumpridas e categoria incompatível invalidam a qualificação. Referências documentais são fatos auditáveis, sem declaração de aprovação jurídica automática.

## Cálculo e documento

O submotor calcula nominal e caixa de IBS/CBS em zero, com estado `IMMUNE`, base tributável zero e alíquotas públicas `null` por inaplicabilidade. Não depende de alíquota tributável futura. Não trata imunidade como redução de 100%. Preserva o valor de referência da operação no trace e os campos de classificação CST410/classes410006 e410007, conferidos no catálogo oficial e no XSD dos campos do emissor. O enriquecimento de cálculo tributável fica inaplicável.

Existem **quatro regras interpretativas educacionais** para serviço080101, NBS compatível e destinos remoto/presencial; permanecem sem aprovação formal. A rota positiva materializada é doméstica, onerosa e com fornecedor regular. Outras classificações, fornecimentos não onerosos, imunidade pública e imunidades por objeto continuam pendentes. A qualificação de crédito anterior exige livro mensal separado; não se presume crédito por esta decisão. Este corpus é do RTC isolado: **não homologa a imunidade de ISS nem gera DPS integrada para entidade imune ao ISS**.

## Evidências

21 testes dirigidos/58 asserções; corpus18/18 e dois snapshots reproduzíveis. Suíte completa349/1.442, PHPStan sem erros, Pint nos sete arquivos PHP deste corte. Gate19/19 fontes íntegras, elegível local, `ready=false`,117 bloqueios. O aumento de quatro bloqueios corresponde às novas regras interpretativas sem revisão.

Resultados/snapshots: `storage/app/phase4b-institutional-immunity-{nonprofit-education,religious-entity}-20261002-4-*`. Hashes: educação `cddb76778632bfc8bb5c52a8c92f1f07e6e7989a0bca81439c16f3b8c25a1372`; religiosa `1ad636b5c79486f6fbb41a861793da78bd680221e3fa8124c8ae54726bb82722`. Verificador: `scripts/verify_phase4b_institutional_immunity_cut.py`.

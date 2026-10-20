# Educação, saúde, destino presencial e NBS

Candidato: `CONSUMPTION-PHASE4B-2026.10.01-6`. Estado: `SANDBOX_CANDIDATE`; Fase 4B ainda sem conclusão integral. O predecessor `-5` materializa os qualificadores e os cenários; o `-6` acrescenta NBS à projeção documental. Todos os hashes históricos são preservados.

## Fonte e qualificação

Os arts. 129/130 da [LC 214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html) e os anexos II/III fundamentam a redução de 60%. O HTML integral está em `resources/fiscal/sources/official/lc214-camara-observed-20261001.html`, SHA-256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`. O materializador preserva os 9 itens de educação e 30 de saúde, com descrições e códigos exatamente como publicados, incluindo prefixos e NBS repetidos para serviços distintos.

A operação exige categoria, item de anexo, NBS completo de serviço e evidência, confirmação da descrição, contraprestação restrita ao serviço beneficiado e prova do tratamento. Educação especial exige público legal e prova específica. A linha normativa sem NBS explícito é preservada; a classificação operacional continua dependente de NBS conhecido e compatível. Contraprestações de operações combinadas retornam `RTC_MIXED_SERVICES_REQUIRE_SEPARATE_TREATMENT`.

`AnnexServiceReductionQualifier` verifica publicação, vigência, disponibilidade, revogação e supersessão. O código CST/classe não comprova elegibilidade. Existem 20 mapeamentos interpretativos de cTribNac/NBS e 80 regras de classificação padrão/reduzida e presencial/remota. Classes reduzidas: CST 200, educação 200028, saúde 200029. Todas as regras são `SANDBOX_REVIEW_REQUIRED`; não há decisão automática para todas as combinações dos anexos.

## Destino

O caminho do art. 11, III, exige prestação física sobre pessoa, fruição presencial, local, UF e evidência. Município/UF declarados para IBS/CBS precisam coincidir com o local da prestação. A prestação no estabelecimento do fornecedor exige também conciliação com seus dados. Indicadores: 030101 no estabelecimento, 030102 em outro local, 100301 no caminho remoto por domicílio do adquirente. Os exemplos remotos não carregam fatos de prestação presencial.

## Cálculo e glosa

As taxas padrão, redução e taxas efetivas são preservadas separadamente. Aplica-se o fator exato 0,40 antes do arredondamento dos valores em `HALF_UP` a duas casas. Percentuais que não caibam nos campos documentais retornam falta de prova de precisão, sem arredondamento silencioso. As alíquotas futuras não são presumidas.

| Exemplo de 2026 | Base RTC | IBS nominal | CBS nominal |
|---|---:|---:|---:|
| Educação/saúde, valor de R$1.000 | 1.000,00 | 0,40 | 3,60 |
| Saúde, glosa auditada e não paga de R$100 | 900,00 | 0,36 | 3,24 |
| Saúde, glosa paga de R$100 | 1.000,00 | 0,40 | 3,60 |

A saúde exige declaração explícita do valor da glosa, inclusive zero. A exclusão positiva exige auditoria e prova de não pagamento; o valor precisa caber no bruto e na base restante após outros ajustes. A exclusão conserva artigo, hash e referências de prova no trace. O crédito de entrada permanece independente do débito reduzido: exige documento e extinção próprios; uso pessoal não gera crédito.

## Integração e documento

Os três cenários integrados usam ISS de R$50,00 proveniente de fixture municipal sintética identificada. As retenções da 4A ficam `NOT_REQUESTED`; esses casos não homologam retenções escolares ou hospitalares. A integração profissional com 4A do corte `-4` permanece reproduzível.

O contrato `DPS-LOCAL-2026.10.01-2` projeta `serv.cServ.cNBS` com nove dígitos, após `xDescServ`. A decisão conserva a NBS pontuada e sua prova; o projetor concilia código e local. Os candidatos de educação/saúde têm somente o conflito conhecido de série numérica no XSD oficial. Não são XMLs operacionais validados. Na glosa não paga, a base RTC é R$900 e a base ISS continua R$1.000: o grupo documental do ajuste ainda falta e o projetor retorna `RTC_DPS_ADJUSTMENT_OR_SPECIAL_GROUP_REQUIRED`, sem produzir XML que descarte a glosa.

## Execução reproduzível

No PHP 8.5 do Docker:

```powershell
python scripts/verify_phase4b_annex_corpus.py
python scripts/audit_phase4b_annex_cut.py
docker compose exec -T app php artisan fiscal:rtc:production-assess --bundle-manifest=bundles/consumption-phase4b-2026.10.01-6.json --controls=resources/fiscal/production/phase4b-controls-2026.10.01-6.json --target=local-validation
```

Os scripts recusam sobrescrever evidências diferentes. Os goldens são registrados após verificar valores, status, classificações e provas esperados independentemente dos hashes calculados.

Validação: **284 testes / 1.223 asserções**, PHPStan sem erros, Pint em 10 arquivos. Corpus RTC **16/16**, integrado **3/3**, histórico **53/53**. Dez snapshots reproduziram seus hashes, incluindo o predecessor `-5`, o profissional `-4` e os cinco snapshots históricos principais.

Hashes integrados:

| Cenário | SHA-256 da decisão |
|---|---|
| Educação presencial | `e1950eb2faa0d17be3e9381f23f30532b93ff5bf8d703453eb719a418a50c674` |
| Saúde presencial | `86b398dceded38073d970cfb7db08a9e15ddf879da085310abef1aea8f0ab2c4` |
| Saúde/glosa não paga | `43c0540c460f7dac24849698ab51cd15cc246d2b6d391319d7034644d81fcabe` |

Evidências em `storage/app/phase4b-integrated-*-20261001-6-*` e relatórios `phase4b-annex-*-corpus-20261001-6.json`. Gate atual: 18/18 fontes íntegras, elegibilidade local verdadeira, `ready=false`, 113 bloqueios. A matriz continua com 12 controles parciais/abertos. Restam mapeamentos, operações combinadas, ajustes documentais, demais tratamentos, taxas/atos municipais aplicáveis, casos reais e revisão formal. A auditoria integral está em `phase4b-completion-audit-2026-10-01.md`.

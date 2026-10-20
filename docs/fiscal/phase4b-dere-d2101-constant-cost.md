# D2101: Selic diária e base por título com custo constante — candidato24

Objetivo local concluído em06/10/2026. Bundle `CONSUMPTION-PHASE4B-2026.10.06-24` preserva23 e vincula47 artefatos oficiais: acrescenta resposta BCB SGS11 de setembro2026, metadados de unidade, documentação ISIN ANNA e DV CNPJ RFB. Os21 registros diários são reais e foram capturados integralmente por período, com SHA256 `43cffec8577702c1afd462ea51c0d27fd60ed77e3864cc543082ad377761be6a`. As posições positivas continuam sintéticas.

O calculador exige posição qualificada mantida durante todo o mês, quantidade e custo médio de aquisição constantes, sem entrada ou saída de principal. Confere quantidade×custo unitário contra a base de custo informada e exige prova independente datada da posição. O contrato usa exclusivamente setembro2026 observado, sem interpolar taxas, preencher feriados ou extrapolar para outros meses. A série11 é percentual ao dia, não a meta anual Selic.

A memória multiplica os fatores `(1 + taxa_diária/100)` com precisão decimal; o teto é o custo×(fator acumulado−1). O resultado monetário usa arredondamento local HALF_UP a centavos; esse critério de implementação ainda depende de homologação, sem afirmar aprovação governamental. Calcula `max(0, min(vSelic, vVarMensal) − vPisCofins)` por título. PIS/Cofins omitido equivale a zero. Variação negativa não gera base negativa nem compensa outro título.

Exemplo conferido por oráculo Python Decimal de100 dígitos: custoR$10.000, tetoR$108,17, variaçãoR$150, PIS/CofinsR$10, baseR$98,17. A memória retém taxas, fatores, hash dos dados oficiais, regra, prova e entrada.

CLI: `php artisan fiscal:dere:calculate-debt-constant-cost <position.json> --bundle-manifest=bundles/consumption-phase4b-2026.10.06-24.json --snapshot=<novo-snapshot.json>`. Replay: mesmo comando com `--replay=<snapshot.json>` e sem posição. Os snapshots preservam entrada, conhecimento e resultado completos; alteração de entrada, bundle distinto ou conhecimento anterior à disponibilidade é rejeitada. Recusa sobrescrever evidência existente.

Aceitação:959 testes/3.108 assertivas; PHPStan sem erros; Pint366 arquivos;22 casos(7 positivos/15 negativos), sete replays e três guardas de comando. Regressões24:15/15 RTC e3/3 integrado;37 casos e42 corpos XML do23 reproduzidos.2.448 artefatos históricos sem alteração. Evidência `storage/app/phase4b-constant-cost-20261006-24/verification-report.json`. Reproduzir com `python scripts/verify_phase4b_dere_constant_cost_cut.py` e `python scripts/audit_phase4b_dere_constant_cost_cut.py`.

Este é um componente aritmético de4B.4/4B.8. Ainda não qualifica oferta pública, fundo, PGCC ou saldo contábil, não integra rendimentos à base financeira e não ativa XML mensal com rendimentos. Movimentações com mudança de custo/estoque continuam pendentes. O mensal herdado do23 continua aceitando somente principal sem rendimentos. Gate47/47 fontes, `ready=false`,119 bloqueios. Fase4B parcial; nenhuma assinatura, transmissão, contabilização ou totalização tributária final.

Próximo escopo: enquadramento art.195 e reconciliação dos rendimentos ao lançamento/PGCC, usando este cálculo no motor de base e no D2101, com exclusão explícita da parcela acima da Selic e prevenção de dupla contagem.

Fontes: [MOD oficial CGIBS](https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere), páginas85–87 impressas; [metadados oficiais BCB SGS11](https://dadosabertos.bcb.gov.br/pt_BR/dataset/11-taxa-de-juros---selic). Capturas preservadas no bundle e em `storage/app/phase4b-d2101-research-20261006/`.

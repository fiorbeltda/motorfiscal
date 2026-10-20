# Consolidação privada48 — desenvolvimento e limites de aceitação

O candidato `2026.10.09-28-draft48` acrescenta os dois conjuntos de fontes privadas aos12 perfis históricos de47. As políticas originais de4 e8 permanecem distintas: o reconhecimento negativo em subconta exige a política explícita de8. O bundle, catálogo e controles são próprios; o builder verifica hashes dos30 insumos originais e do relatório precedente antes de preparar o sucessor.

Os15 casos privados preservam integralmente os ledgers financeiros, as contas e os valores do livro, os lançamentos brutos e a memória diária. Os vínculos de qualificação e PGCC são recapturados para o conhecimento48. A classificação dos componentes segue o catálogo atual, sem converter perda pública não compensável em dedução privada.

Evidência inicial:30 testes positivos/372 asserções;15 testes de seleção e rejeição/45 asserções;15 snapshots com replays coincidentes;75 XMLs locais sob XSD1.3.0; IBS e CBS conferidos separadamente. Os XMLs continuam sem assinatura. Essa evidência cobre o corpus privado capturado, sem afirmar aceitação integral do escopo28.

A primeira tentativa de reutilizar os87 ledgers públicos de47 sem recapturar a declaração independente de venda produziu85 rejeições e2 sucessos: a prova contém o hash da política de47. O log `regression87.log` e os arquivos anteriores do helper/teste estão preservados. A recaptura atualiza explicitamente esse hash e o selo de qualificação do ledger, mantendo os dados econômicos e comparando a análise com o conhecimento47 original.

A segunda tentativa preservou as bases e análises dos87 casos, mas85 projeções rejeitaram a prova mensal D2101 antiga (`RTC_DERE_D2101_YIELD_COMPLETE_SCOPE_EVIDENCE_UNPROVEN`). O log `regression87-rebound.log` também está preservado. A prova mensal agora referencia a operação, o livro e o PGCC recapturados; o teste dirigido dos seis casos públicos de15 passou com60 asserções. A captura completa dos87 casos passou com972 asserções. Os87 novos insumos financeiros foram comprimidos após fechamento dos arquivos, conservando os SHAs.

O relatório `regression-positive-validation-report.json` congela essa regressão dirigida e os hashes da implementação final usada nas verificações seguintes. Há22 casos negativos/106 asserções, incluindo os sete novos bloqueios do livro privado. O corpus nativo dos87 casos terminou com87 replays e520 XMLs; a suíte completa passou com1836 testes/44012 asserções. A auditoria final conferiu os102 cenários/102 replays/595 XMLs sob48, perfis, origens, IBS/CBS, qualidade e preservação. `consolidation-progress-report.json` aceita o corpus comum localmente e mantém escopo28 integral/Fase4B e produção como não concluídos.

A auditoria inicial própria está em `private-initial-validation-report.json`: mantém a aceitação do corpus comum e da qualidade completa como falsas. A preservação verificou6610 artefatos,87 snapshots precedentes e30 insumos privados;51 fontes oficiais válidas. Os verificadores dos candidatos27,26 e25 também passaram novamente. A regressão/captura comum foi posteriormente aceita pela auditoria final; o relatório inicial permanece preservado com seu estado parcial original.

Ainda são necessários: fechar a matriz de negativos por requisito do escopo; resolver a ponte documental do rendimento novo produzido na alienação de títulos de dívida corporativa com oferta pública; e provar os critérios integrais do escopo28 e da Fase4B.8. Produção permanece bloqueada. O candidato integral aceito localmente continua sendo27.

Artefatos atuais: `storage/app/phase4b-private-consolidation48-draft-20261009-28/`. Comandos de reprodução:

```text
python scripts/build_phase4b_private_consolidation48_draft.py
docker compose exec -T app php -d memory_limit=512M vendor/bin/pest tests/Fiscal/Unit/DereSecuritiesPrivateConsolidation48BaseTest.php tests/Fiscal/Unit/DereSecuritiesPrivateConsolidation48MonthlyTest.php --compact
python scripts/verify_phase4b_private48_corpus.py
python scripts/audit_phase4b_private48_preservation.py
```

Os verificadores usam criação exclusiva ou comparação com o artefato existente. Nenhuma evidência anterior deve ser sobrescrita para transformar uma falha em aceitação.

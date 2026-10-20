# D1001 financeiro: ativação local no candidato18

Data de corte:05/10/2026. Bundle `CONSUMPTION-PHASE4B-2026.10.05-18`, `SANDBOX_CANDIDATE`, predecessor17. Este recorte atende parte documental de4B.7 e acrescenta corpus de4B.8 do planejamento inicial. Contribui para5.4 e6.5 sem representar fechamento dessas fases ou da Fase4B.

## Entrega e limites

O candidato18 ativa D1001 financeiro de inclusão original no comando mensal. Exige cadastro externo qualificado `ACTIVE`, regime principal1 sem regimes secundários e provas independentes de cadastro, regime, natureza tributária, atividades e início da obrigação. Cada prova identifica fornecedor, valor, hash, observação e intervalo de validade. Ausência, identidade divergente, vencimento ou observação futura geram `ACTION_REQUIRED` específico. O contrato não verifica a veracidade externa de uma prova marcada como qualificada.

A atividade deve existir na Tabela21 e concordar com a operação: crédito01A, câmbio02A, títulos03A, securitização04A e factoring05A. O cadastro deve abranger o início e o fim do mês reconstruído. O catálogo recebido deve corresponder ao hash canônico vinculado à política capturada.

O comando exige snapshot reproduzível da etapa financeira documental pendente; não a transforma em decisão tributária integrada final. Reconstrói D1001/D1101/D1121/D1199 e confere cada corpo no XSD preservado antes da gravação. A única ausência estrutural admitida é a assinatura. Mantém `production_ready=false`, `registration_performed=false`, `period_closed=false` e ausência de transmissão. PGCC/D1011 continuam sem ativação neste bundle.

Padrões dos comandos preservados: gerais14, D1101=15, D1121=16 e fechamento17. Seleção explícita do novo candidato: `--bundle-manifest=bundles/consumption-phase4b-2026.10.05-18.json`. Bundles e evidências anteriores permanecem reproduzíveis.

## Catálogo oficial e temporalidade

`resources/fiscal/catalogs/dere-financial-activities-2026.10.05-18.json` materializa26 atividades da Tabela21, página105 do PDF, página impressa104. Código, descrição, regime e referência da fonte são preservados. O conteúdo também é capturado na política mensal, com hash canônico. Fonte AnexoI1.3.0 SHA-256 `7296b225e15fb4b395a41a9413ada1190cc2229d64334bf78d7b69f0fb8019f9`.

A edição do documento é02/10/2026, a publicação do pacote03/10/2026 e a observação local05/10/2026. A seleção técnica requer publicação e observação conhecidas na data do snapshot. A Tabela21 não contém datas fiscais individuais dos códigos: `fiscal_validity=NOT_SPECIFIED_BY_TABLE`. Não se atribuiu artificialmente01/01/2026 nem se converteu publicação técnica em início da obrigação.

O catálogo qualifica domínio técnico na versão conhecida. A validade da atividade do contribuinte e o parâmetro `dataInicioDeRE` exigem provas externas independentes e datadas. Nos positivos essas provas são explicitamente **sintéticas**. O cenário de atividade vencida testa a prova do contribuinte, não uma vigência normativa inexistente na tabela.

Fontes: [AnexoI1.3.0](https://www.cgibs.gov.br/upload/arquivos/202610/03141315-03-leiautes-da-dere-anexo-i-tabelas-v-1-3-0.pdf), [publicação oficial](https://www.cgibs.gov.br/dere-cgibs-e-receita-federal-publicam-versao-1-3-0-da-documentacao-tecnica-e-disponibilizam-manual-do-usuario), [índice SPED](https://www.gov.br/sped/pt-br/assuntos/documentos-fiscais/dere/manuais-e-documentos-tecnicos/versao-atual-1). PDFs, páginas e hashes foram preservados sem substituir fontes anteriores. A tabela foi conferida também em renderização.

## Compatibilidade1.3.0

O comunicado anuncia documentação1.3.0. O índice SPED observado ainda denomina seu ZIP completo `v.1.2.0 PRODUÇÃO`; o download atual produz o mesmo hash `c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818` do pacote preservado. O CGIBS oferece um ZIP para pilotos com nove esquemas, sem D1121/D1199.

O XSD D1001 do piloto é idêntico em bytes ao preservado. D1101 e D1011 são iguais após normalizar quebras de linha; os hashes brutos permanecem separados. Os campos extraídos de D1001 nas versões1.2.0/1.3.0 são iguais após retirar cabeçalhos/paginação. Isso qualifica compatibilidade local do D1001; não demonstra migração ou homologação mensal integral1.3.0.

`resources/fiscal/sources/official/dere-compatibility-2026.10.05-18.json` registra comparação por evento. D1101/D1121/D1199 mantêm contratos e XSDs1.2.0. Reabertura, bases negativas e demais mudanças do pacote novo ficam fora deste recorte. As regras técnicas1.3.0 consultadas para D1001 incluem `INI_VALID`, distinção de regimes, exigência/exclusividade de grupos e atividades por tabela.

## Corpus, testes e reprodução

O corpus documental tem oito cenários: positivo; atividade fora do domínio; prova de atividade vencida; identidade divergente; observação futura; cadastro ausente; calendário de início ausente; atividade incompatível com a operação. Cada projeção usa snapshot financeiro reproduzido e política capturada. O relatório positivo preserva hashes do snapshot, decisão, entrada mensal e quatro XMLs. D1101/D1121/D1199 mantêm os hashes do candidato17.

Suíte completa no Docker PHP8.5.10: **800 testes/2.658 asserções**, sem falhas. PHPStan completo sem erros; Pint354 arquivos aprovado. Corpus novo8/8, corpus RTC15/15 e integrado3/3 dos candidatos18 e17, oito replays herdados por candidato e replay mensal17 passaram. Quatro corpos XML conferidos com somente assinatura ausente. **1.660 artefatos históricos sem mudança de hash**. Gate18:41/41 fontes íntegras, elegível local, `ready=false`,119 bloqueios.

Materialização: `python scripts/fork_phase4b_dere_contributor_bundle.py`. Verificação: `python scripts/verify_phase4b_dere_contributor_cut.py`. Os scripts recusam substituir recursos ou evidências diferentes dos existentes. Downloads observados devem estar presentes antes da materialização; não são refeitos automaticamente.

Evidências e logs: `storage/app/phase4b-contributor-20261005-18/`. O verificador executa corpus documental, replays, corpus RTC/integrado18 e17, checks XML e hashes históricos. A matriz geral permanece parcial: aprovação deste recorte local não equivale ao gate final da Fase7.

## Aceitação do recorte e continuidade

| Critério do plano | Entrega | Limite mantido |
|---|---|---|
| Bundle/catalogação versionados | Candidato18, Tabela21 oficial, políticas capturadas | Aprovação de produção pendente |
| Temporalidade | Publicação/observação técnica e provas independentes datadas | Sem datas fiscais individuais na tabela; início obrigatório não inferido |
| Projeção documental | D1001 financeiro e quatro corpos XML conferidos | Sem assinatura/recepção; mensal1.3.0 integral não demonstrado |
| Corpus/trace/hash/replay | Oito casos e regressão dos candidatos | Sem cobertura integral de famílias/etapas temporais de4B.8 |
| Perfis5.4 e obrigações6.5 | Componente financeiro documental | Sem orquestrador financeiro completo ou governança integral |

Continuam pendentes cadastro completo e alterações/exclusões; referências/códigos oficiais do PGCC; D1106/D2101; bases negativas/reabertura; financeiro integrado com ISS/4A; demais famílias de4B; perfis completos5.1–5.8; governança completa6 e corpus/gates finais7. O próximo avanço documental deve qualificar PGCC e auxiliares antes de ativar D1011, mantendo os critérios originais de aceitação.

# Prompt de continuidade — Motor Fiscal SaaS / Fase 4B

Assuma a continuidade a partir do estado real descrito abaixo. Leia as evidências antes de alterar arquivos. O agente anterior parou a pedido do usuário em 14/10/2026. Não há suíte de testes em execução nesse checkpoint; Docker/PHP-FPM permanecem ligados. Não reinicie trabalho já aceito.

## Objetivo e limites

Avançar sequencialmente ao máximo na Fase 4B conforme o plano original: pesquisar fontes oficiais, concluir e validar cada escopo viável, preservar evidências históricas e manter produção bloqueada até aceitação efetiva. A prioridade inicial D2101 com movimento/migração documental DeRE 1.3.0 já teve avanços aceitos; continue a partir das pendências atuais, sem voltar ao início.

O objetivo é cobertura funcional IBS/CBS integrada ao ISS e à Fase 4A, com temporalidade, incidência/local, sujeitos, base/cálculo, créditos, catálogos, projeção documental e aceitação por requisito. Frontend, autenticação, billing, multitenancy, assinatura e transmissão governamental ao vivo não integram esse escopo. Regimes verticais da Fase 5 não devem substituir os requisitos mínimos da 4B. Homologação técnica local e aprovação de produção são gates distintos.

Não declare a Fase 4B completa porque uma suíte ou corpus passou. O percentual aproximado anteriormente informado ao usuário foi 70%; não é métrica de aceitação. Confira todos os itens do plano e as lacunas reais.

## Ambiente e diretórios

- Repositório REAL: `C:\xampp\htdocs\projeto-saas-motor`. Execute comandos nesse diretório. O diretório `C:\Users\gabriel.oliveira\Documents\ChatGPT\Projeto - Saas` informado na interface não é o checkout usado neste trabalho.
- Branch atual: `codex/fiscal-engine-bootstrap`. Há muitas alterações e arquivos não rastreados anteriores. Não faça reset, clean, descarte, commit ou push como parte automática dessa continuidade.
- Shell: PowerShell. PHP do contêiner: 8.5; PostgreSQL: 18. PHP local do Windows 8.2 não é o runtime válido para os checks.
- Docker: `D:\Docker\DockerDesktop\resources\bin\docker.exe`. Acrescente esse diretório ao PATH. Use `docker compose exec -T app php -d memory_limit=512M ...`.
- Python: 3.14, disponível como `python`; configure `$env:PYTHONIOENCODING='utf-8'`. Há `pypdf` disponível.
- Código RTC: `C:\xampp\htdocs\projeto-saas-motor\app\Modules\Fiscal\ConsumptionReform`.
- DPS/DeRE: `C:\xampp\htdocs\projeto-saas-motor\app\Modules\Fiscal\NFSe`.
- Comandos CLI: `C:\xampp\htdocs\projeto-saas-motor\app\Console\Commands`.
- Infraestrutura/orquestração: `C:\xampp\htdocs\projeto-saas-motor\app\Modules\Fiscal\Infrastructure`.
- Recursos: `C:\xampp\htdocs\projeto-saas-motor\resources\fiscal`, subdiretórios `bundles`, `catalogs`, `facts`, `questions`, `production`, `schemas`, `scenarios`, `invariants` e `rules`.
- Testes: `C:\xampp\htdocs\projeto-saas-motor\tests\Fiscal\Unit` e `C:\xampp\htdocs\projeto-saas-motor\tests\Fiscal\Integration`.
- Scripts: `C:\xampp\htdocs\projeto-saas-motor\scripts`.
- Documentação: `C:\xampp\htdocs\projeto-saas-motor\docs\fiscal`.
- Evidências imutáveis: `C:\xampp\htdocs\projeto-saas-motor\storage\app`. Não as apague ou sobrescreva.
- Pesquisas originais: `K:\GERAL\TI\Projeto - SAAS\Motor Fiscal\Pesquisas Definitivas`.

O disco C tinha aproximadamente 780 MB livres no checkpoint e o espaço tem oscilado. Verifique antes de capturas longas. Não apague histórico para liberar espaço. Tentativas interrompidas por falta de espaço devem ser preservadas; use novos nomes/diretórios para novas tentativas.

## Fontes de escopo fornecidas pelo usuário

Leia estes arquivos completos, todos chamados `Texto colado.txt`:

1. Plano original: `C:\Users\gabriel.oliveira\.codex\attachments\e85edde7-ac3a-4a4a-b380-9cf54627bd84\Texto colado.txt`; seção FASE 4B, itens 4B.1–4B.8 (aproximadamente linhas 416–556).
2. Implementação funcional obrigatória: `C:\Users\gabriel.oliveira\.codex\attachments\38472c6b-c887-4922-9b01-c44d96618036\Texto colado.txt`.
3. Continuidade original: `C:\Users\gabriel.oliveira\.codex\attachments\f2fc3be3-3301-4cb9-a367-19ffd08531ae\Texto colado.txt`.
4. Aceitação 4B.8 e fases seguintes: `C:\Users\gabriel.oliveira\.codex\attachments\e0404e58-c85b-4a11-a1f1-de5fd3da4f13\Texto colado.txt`.

O inventário de 80 itens preserva também instruções em prosa (especialmente 4B.6/4B.7); não reduza o plano apenas aos bullets.

## Onde o trabalho parou exatamente

**Geração 71 aceita tecnicamente; geração 73 instalada e verificada de forma direcionada, aguardando regressão completa e auditoria de desenvolvimento.** Nenhuma suíte completa 73 foi iniciada. Nenhum processo Pest permanecia em execução no `docker compose top app` do checkpoint.

Geração 71:

- Conversão com token decimal exato da fonte JSON, sem usar float na aritmética monetária; validação de tipo JSON usa o valor decodificado apenas para distinguir número de string.
- Guarda de origem material da cotação e limites de offset civil até ±14:00.
- Bundle `2026.10.14-70-draft1`: origem material qualificada apenas para USD; EUR/AUD continuam como dados numéricos históricos, mas o bundle novo recusa origem não qualificada.
- Suíte completa terminal: **2.233 testes / 45.597 assertions**, exit 0.
- Aceitação: `C:\xampp\htdocs\projeto-saas-motor\storage\app\phase4b-fx71-exact-quote-preparation-20261014\combined-integration-development-validation.json`.
- Os hashes dessa aceitação representam o runtime anterior à instalação 73. O engine anterior está arquivado no diretório 73. Não compare o engine atual com 71 para concluir adulteração nem reescreva a aceitação anterior.

Geração 73:

- Arquivos instalados: `ConsumptionReformEngine.php`, `PartialExportFxQuoteConverter.php`, `PartialExportFxTransactionQualifier.php` no diretório RTC indicado acima.
- Converte o bruto em moeda estrangeira ANTES da alocação estrangeira/brasileira da exportação parcial digital.
- Exige origem material qualificada, fornecedor brasileiro, adquirente e destinatário estrangeiros, destinatário brasileiro da parcela doméstica, documentos, evidência de transação, valor original, valor BRL reconciliado, cotação/hash, percentual e prova da alocação.
- Não usa o contexto de importação para exportação. Provas locais sintéticas não significam autenticação remota ou autorização de crédito/lançamento.
- Bundle próprio: `C:\xampp\htdocs\projeto-saas-motor\resources\fiscal\bundles\consumption-phase4b-2026.10.14-73-draft1.json`.
- Catálogo, facts e controles: mesmo sufixo `2026.10.14-73-draft1.json` nos respectivos diretórios. Perguntas: `questions\consumption-integrated-2026.10.14-73-draft1.json`.
- Cinco fatos/perguntas novos: `rtc.acquirer_foreign_nif`, `rtc.export_foreign_document_id`, `rtc.export_transaction_evidence_id`, `rtc.export_fx_source_id`, `rtc.export_fx_proof`.
- Cenários: `C:\xampp\htdocs\projeto-saas-motor\resources\fiscal\scenarios\export-fx-2026.10.14-73-draft1`.
- **12 decisões nativas + replays exatos**, com USD dia útil/fim de semana, parcelas 40/25/99/1%, BRL e seis recusas. **18 replays históricos de importação/câmbio/crédito preservados**.
- **50 testes direcionados / 213 assertions**, PHPStan geral aprovado, Pint geral aprovado em **501 arquivos**. Os testes/PHPStan foram capturados antes da última formatação; o Pint geral depois. O checkpoint declara isso expressamente.
- Testes novos: `tests\Fiscal\Unit\PartialExportFxTransaction73Test.php` e `tests\Fiscal\Integration\PartialExportFxEngine73Test.php`.
- Caso USD 1000.00 × 5.1795: bruto 5179.50, parcela estrangeira 2071.80, brasileira 3107.70, IBS estadual 3.11, municipal 0.00, CBS 27.97.
- Resultado nominal continua **ACTION_REQUIRED / RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN**. A obrigação e a projeção documental da exportação parcial ainda não foram concluídas.
- Produção continua bloqueada. Não declare o escopo 73 inteiro ou a Fase 4B concluídos.

Evidência exata do ponto de parada:

`C:\xampp\htdocs\projeto-saas-motor\storage\app\phase4b-partial-export-fx73-preparation-20261014\handoff-checkpoint-validation.json`

Esse arquivo registra hashes ATUAIS de código, testes e recursos. `export-fx-installation.json` registra hashes anteriores ao Pint; mantenha ambos como registros de momentos distintos.

## Diretórios de evidência para ler primeiro

Sob `C:\xampp\htdocs\projeto-saas-motor\storage\app\`:

- `phase4b-partial-export-fx73-preparation-20261014`: checkpoint, fontes IBS extraídas (páginas físicas 15/16/46), protótipos, arquivos anteriores, instalação, bundle, manifest, 12 snapshots e corpus. Principais relatórios: `partial-export-fx-preparation-validation.json`, `export-integrated-corpus-validation.json`, `export-integrated-case-manifest.json`, `handoff-checkpoint-validation.json`.
- `phase4b-fx71-exact-quote-preparation-20261014`: aceitação plena 71; **também contém os logs/JSON dos checks 73**, prefixo `scope73-`, porque o wrapper existente foi reaproveitado. `combined71-full-suite.json/.log` são da geração 71, não da 73. Replays 71 em `replay-v2`.
- `phase4b-fx70-origin-preparation-20261014`: investigação de origem BCB/Refinitiv, guarda material, offset, nove decisões do bundle 70 e arquivos anteriores.
- `phase4b-import-fx69-temporal-preparation-20261014`: aceitação das datas estritas, predecessor completo **2.213 / 45.531**.
- `phase4b-import-fx68-integration-20261013`: fontes/input fixtures, integração direta de câmbio, crédito, 14 snapshots. A primeira suíte 68 foi interrompida com exit 143 e NÃO é aceitação; o registro foi preservado.
- `phase4b-fx67-preparation-20261013`: capturas oficiais CBS/LC214/IBS/BCB, observações com URLs/hashes, dados brutos USD/EUR/AUD, contrato de pesquisa e prova de arredondamento exato.
- `phase4b-original-requirements72-audit-v3-20261014`: `original-requirements-inventory.json` (80 itens e 179 fontes de teste), texto original, reprodução BRL/USD da lacuna, `requirements-export-preparation-validation.json`. Os diretórios 72 sem sufixo/v2 são tentativas sem aceitação; foram preservados.
- `phase4b-document74-primary-research-20261014`: nova pesquisa oficial de documentação, descrita abaixo. Nenhum desses schemas foi ativado.
- `phase4b-dere9121-63-period-integration-20261013`: aceitação anterior **2.157 / 45.338**, períodos abertura/fechamento/reabertura e vínculo econômico de eventos ativos.
- `phase4b-dere9121-65-version-corpus-20261013`: `version-corpus-development-validation.json`, 40 fechamentos com movimento, 240 XML e 100 replays, versões ativas 01/03.
- `phase4b-dere9121-66-no-movement-versions-20261013`: `no-movement-version-development-validation.json`, seis fechamentos sem movimento, 36 XML e 13 replays.
- `phase4b-dere9121-64-process-preparation-20261013`: fonte e lacuna D1021; exclusão dependente de processo continua recusada sem schema/prova de origem. Não enfraqueça a guarda.

## Próxima ação concreta: fechar a geração 73

1. Confira os hashes do checkpoint e o estado atual. Descubra/releia AGENTS.md se houver novas instruções; nenhum foi encontrado anteriormente.
2. Não execute novamente instaladores/construtores exclusivos que já produziram arquivos. Scripts principais já executados: `activate_phase4b_export_fx73.py`, `build_phase4b_export_fx73_bundle.py`, `prepare_phase4b_export_fx73_permanent_tests.py`, `prepare_phase4b_export_fx73_scenarios.py`, `capture_phase4b_export_fx73_corpus.py`.
3. Se precisar confirmar a geração final após formatação, use NOVOS labels com o wrapper abaixo. O resultado direcionado esperado é 50 testes / 213 assertions.
4. Rode a suíte Pest completa da geração ATUAL. Contagem esperada: **2.266 testes** (2.233 anteriores + 33 novos). Confira a saída real; não fabrique assertions nem aceite somente o exit code do PowerShell.
5. Produza novo auditor/relatório de desenvolvimento 73 com hashes finais, suite terminal, corpus 12, replays 18, fontes legais, bundle/facts/questions, type/style e bloqueio de produção. Existe auditor de PREPARAÇÃO `audit_phase4b_export_fx73_preparation.py`; NÃO o rerode depois da instalação, pois suas assertivas corretamente exigem runtime anterior. Ainda não existe aceitação integral de desenvolvimento 73.
6. Preserve 71 e todos os históricos. Em verificadores que precisem do runtime anterior, use os arquivos arquivados e hashes correspondentes.

Exemplo PowerShell, a partir do checkout real:

```powershell
$env:PATH='D:\Docker\DockerDesktop\resources\bin;'+$env:PATH
$env:PYTHONIOENCODING='utf-8'
python scripts/capture_phase4b_fx71_check.py scope73-final-directed docker compose exec -T app php -d memory_limit=512M vendor/bin/pest tests/Fiscal/Unit/PartialExportFxTransaction73Test.php tests/Fiscal/Integration/PartialExportFxEngine73Test.php tests/Fiscal/Unit/PartialExportDomesticLegTest.php tests/Fiscal/Integration/ImportFxEngine68Test.php
python scripts/capture_phase4b_fx71_check.py scope73-full-suite docker compose exec -T app php -d memory_limit=512M vendor/bin/pest
```

O wrapper cria log e JSON exclusivos no diretório 71; não reaproveite labels existentes. Ele captura stdout em memória e só imprime ao término: silêncio não significa travamento. As suítes recentes levaram cerca de 26 minutos. Observe o handle retornado e/ou `docker compose top app`; timeout de observação não autoriza reinício. PID exibido por compose não deve ser tratado como PID interno do contêiner. Não interrompa processos arbitrariamente.

## Pesquisa documental 74: descoberta importante para a sequência

Foram capturados em 14/10/2026, com SHA e inventário, portal RTC, portais de produção/homologação, NT009 v1.01, Ato Técnico Conjunto nº7, imagem de cronograma e ZIPs XSD atuais. Leia:

- `primary-source-observation.json`;
- `document-research-validation.json`;
- `nt009-external-commerce-clauses.txt` (páginas físicas 15/16/17);
- `technical-act7-full-text.txt`;
- `schedule-nt009.jpeg`, que foi inspecionado visualmente.

Fontes primárias:

- https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc
- https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual
- https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita
- https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf
- https://www.cgibs.gov.br/upload/arquivos/202610/01120218-nfse-e-nfsevia-atc-rfb-sufis-cgibs-diretoria-executiva-n-7-de-28-de-setembro-de-2026.pdf
- https://www.gov.br/nfse/pt-br/noticias/publicado-o-ato-tecnico-conjunto-rfb-cgibs-no-7-aprovando-a-versao-1-01-da-nota-tecnica-009-nfs-e-e-a-nota-tecnica-010-da-nfs-e-via

Constatações ainda NÃO ativadas:

- NT009 v1.01 explicita consumo misto: importação informa em vServ a parcela brasileira; exportação informa a parcela exportada e outra NFS-e deve cobrir a parcela brasileira. Isso dá fonte concreta para a pendência documental 73, mas exige qualificação temporal/ambiental e contrato próprio.
- Novos campos de comExt incluem fatura, valor e contrato de câmbio; há ajuste de base IBS/CBS de comércio exterior. Leia a NT completa e os anexos; não deduza campos por memória.
- Cronograma anunciado na imagem oficial: pacote 1 em homologação **01/10/2026**; pacote 2 (comExt, ajuste de câmbio, nota do tomador e outros) em homologação **15/10/2026**; produção dos pacotes 1/2 **03/11/2026**. Data atual do checkpoint: 14/10/2026. Cronograma anunciado não prova remotamente implantação.
- O ZIP de homologação observado tem TSSerieDPS com pattern **`[0-9]{1,4}|[0-8][0-9]{4}`**, sem âncoras literais. O ZIP de produção contém duas declarações observadas: uma sem pattern e outra com **`^0{0,4}\d{1,5}$`**. Consulte membros/hashes exatos no inventário. NÃO trate esses arquivos como um único schema nem normalize o oficial.
- O conflito antigo `RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT` decorre do XSD preservado usado pelo libxml; o novo schema de homologação pode permitir resolver localmente uma parte dessa lacuna em nova versão. Ainda falta provar seleção do membro, fechamento de imports/includes, identidade de versão, restrições, semântica libxml, datas e corpus. Não remova o bloqueio histórico/produção por suposição.

A próxima frente documental viável é migrar de maneira versionada a projeção local para os artefatos oficiais observados adequados ao ambiente/data, validar séries numéricas e estruturar os documentos separados da exportação parcial. Reavalie também importação/nota do tomador com as novas fontes. Não assine/transmita.

## Outras pendências que o objetivo integral mantém

- Auditoria requisito por requisito dos 80 itens; o inventário 72 começa UNPROVEN e não é uma matriz final de aceitação.
- Demais moedas: taxas EUR/AUD do BCB/Refinitiv não bastam para a origem material exigida pelo regulamento; conversão cruzada de moeda sem cotação precisa de prova específica. Ausência na lista de dez moedas da API não prova ausência de cotação no País.
- Obrigações/projeções de importação, exportação parcial, doações/não onerosas e outras rotas ainda devem ser confrontadas com fonte, política e corpus. Não classifique todas as guardas NOT_MATERIALIZED como lacunas sem analisar o ramo ativo.
- Responsabilidade/plataformas, compras públicas, Simples, ajustes/benefícios, crédito, regimes específicos e integração ISS/4A: conferir cobertura real no plano, sem inventar parâmetros ou mover tudo para Fase 5.
- CBS de 2027 em diante, IBS jurisdicional de 2029 em diante e taxa municipal de ISS congelada em 31/12/2028: só ativar com artefato efetivo comprovado; distinguir fórmula implementável de parâmetro indisponível.
- Catálogos cIndOp/cClassTrib/NBS/correlação: preservar OFFICIAL_CORRELATION versus ENGINE_DECISION_RULE, versões e datas. AnexoVIII é apresentado no portal como trabalho inicial sem regras de negócio nos ambientes; não converter em decisão oficial automática.
- DeRE: ponte de realização/alienação e outras famílias permanecem condicionadas a fontes/contratos; exclusão dependente de D1021 continua sem origem qualificada. Os corpora sintéticos aceitos não autenticam recepção governamental nem dados reais de contribuinte.
- A aprovação formal de produção permanece separada. O assessor do bundle 70 retornou ready=false, eligible_for_local_validation=true; o resultado está em `combined71-production-assess.log/.json` no diretório 71.

## Documentos e scripts de apoio

Em `docs\fiscal`: `phase4b-export-fx73-preparation-validation.md`, `phase4b-original-requirements72-partial-export73.md`, `phase4b-fx71-combined-integration-progress.md`, `phase4b-next-scope-original-requirements67.md`, `phase4b-local-dps-projection.md`, `phase4b-status.md` (cronologia extensa, nem todas as entradas refletem o último checkpoint).

Scripts de pesquisa 74: `capture_phase4b_document74_primary_sources.py`, `inspect_phase4b_document74_sources.py`. Já executados e exclusivos; não os repita com os mesmos destinos.

Scripts de checkpoint/auditoria: `capture_phase4b_handoff73_checkpoint.py` (já executado), `audit_phase4b_fx71_combined_integration.py --full` (já executado), `audit_phase4b_requirements72_export73_preparation.py` (histórico de preparação), `audit_phase4b_export_fx73_preparation.py` (histórico de preparação). Leia condições antes de rodar; não enfraqueça verificadores para fazer hashes históricos passarem.

## Regras de continuidade

Crie evidências novas sem sobrescrever as anteriores. Arquive código antes de alterações materiais. Não altere fontes oficiais/XSD para obter validação. Mantenha classificações de documento validado, candidato local, autenticação, elegibilidade de crédito e apropriação separadas. Ao corrigir uma falha, preserve sua primeira tentativa e use uma geração/label nova.

Pesquise fontes primárias e registre URL, arquivo, SHA, observação, versão, vigência e ambiente. Não invente alíquotas futuras ou aprovação legal. Comunique resultados reais e limites. Prossiga autonomamente pelas frentes viáveis, sem pedir confirmação de ações reversíveis já autorizadas. Marque conclusão apenas quando a auditoria integral demonstrar o objetivo; caso contrário, mantenha explícitas as pendências.

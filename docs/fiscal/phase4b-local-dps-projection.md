# Projeção local de DPS a partir da decisão integrada

Contrato atual: `DPS-LOCAL-2026.10.02-1`, no candidato `CONSUMPTION-PHASE4B-2026.10.02-7`. Acrescenta ISS institucional imune qualificado por prova independente: `tribISSQN=2`, `tpImunidade=2/3`, sem `pAliq`, conforme XSD v1.01. Preserva NBS e município da prestação. O cálculo mensal do `-7` fica na memória e não altera os valores da operação da DPS. Os contratos anteriores continuam aceitos para bundles históricos. Escopo: fornecedor/tomador domésticos, ISS padrão ou institucional imune, serviço sem ajustes e classificação RTC materializada. Demais perfis continuam pendentes. Contrato e evidências: `phase4b-integrated-institutional-immunity.md` e `phase4b-monthly-assessment.md`.

## Execução

O candidato padrão corrente é `CONSUMPTION-PHASE4B-2026.10.02-14`, com o mesmo contrato documental. Software não financeiro de fornecedor183, art184 §3, mantém DPS geral comISS/4A/RTC; três candidatos locais apresentam somente o conflito de série. Débitos e elegibilidade de crédito financeiros específicos são calculados na memória, mas não produzem documento final. Ver `phase4b-financial-borrower-credit.md`. A projeção DeRE financeira é separada da DPS: `phase4b-dere-financial-projection.md`; não comprova integração financeira final. Contratos: `phase4b-financial-gross-debits.md` e `phase4b-financial-scope-integration.md`. Rotas cooperativas do10 e transferência272 continuam preservadas. Os comandos históricos abaixo exigem seus manifestos explícitos.

No contêiner PHP 8.5:

```powershell
docker compose exec -T app php artisan fiscal:dps:project-xml storage/app/phase4b-integrated-education-presential-20261001-6-snapshot.json resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json --bundle-manifest=bundles/consumption-phase4b-2026.10.01-6.json --candidate-output=storage/app/review-dps-candidate.xml --report=storage/app/review-dps-report.json
```

Os destinos precisam ser novos. O comando não sobrescreve artefatos. `--candidate-output` grava explicitamente uma projeção ainda não validada. `--output` só grava XML quando o schema retorna `VALID`; atualmente a série numérica encontra o conflito oficial descrito abaixo. `--report` registra hashes da decisão, snapshot, identidade, XML, bundle e XSD. Nenhum documento é assinado ou transmitido.

Snapshots antigos exigem seleção explícita do manifesto correspondente por `--bundle-manifest`; não se substitui seu conhecimento pelo candidato atual.

O comando exige decisão final integrada, reconstrói seu resultado com o conhecimento capturado e compara o conteúdo canônico. Um snapshot adulterado, identidade com competência diferente ou contrato incompatível é rejeitado. A aprovação de produção não é simulada: todos os relatórios indicam `production_ready=false`.

## Identidade documental

O JSON exige ambiente, data/hora de emissão com fuso, versão da aplicação, série e número numéricos, competência, município emissor, CNPJ do fornecedor, opção do Simples, regime especial, indicador de tributos totais, município da prestação e nome do tomador. Exige referências de prova para identidade, local da prestação, regime e indicador total. Fornecedor, município emissor e opção do Simples são conciliados com os fatos usados pelo motor. Destinatário diferente exige nome adicional.

A retenção federal requer `pis_cofins_cst` e `pis_cofins_evidence_id` para a classificação própria. `tpRetPisCofins` representa a combinação efetivamente retida; `vRetIRRF`, `vRetCP` e `vRetCSLL` são valores retidos agregados. `vPis` e `vCofins` da apuração própria não são inferidos das retenções. As somas são conciliadas com o total decidido.

## Evidência e limites

O cenário integrado verificado produziu ISS 20,00; IRRF 15,00; PCC 46,50 (PIS 6,50, Cofins 30,00 e CSLL 10,00); IBS 1,00; CBS 9,00. O XML contém a combinação de retenções 3 e CSLL 10,00. Os valores previstos de enriquecimento nacional ficam na decisão, fora do XML do emissor.

O XSD oficial preservado por SHA-256 contém âncoras `^` e `$` na faceta de `serie`, interpretadas literalmente pelo libxml. A série operacional `1` retorna um erro de padrão. O relatório traduz esse conflito para `ACTION_REQUIRED / RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT`. Não se altera a fonte nem se usa a série sintética `^1$` operacionalmente. Essa substituição só aparece em um teste estrutural.

Validação de schema não substitui regras de negócio da API e parâmetros municipais. Importação, exportação, operações não onerosas, regimes especiais, ajustes de base e grupos documentais adicionais ainda precisam de contratos próprios. Dedução de ISS, ajuste de base RTC ou tratamento especial fora da imunidade qualificada retorna `RTC_DPS_ADJUSTMENT_OR_SPECIAL_GROUP_REQUIRED`; o projetor não descarta seu efeito. Os parâmetros municipais dos cenários tributáveis são fixtures; o fluxo imune dispensa alíquota por incidência inaplicável. O comando não qualifica esses exemplos para produção.

# DeRE — cadastro local D1001

## Ativação financeira versionada: candidato18

O candidato `CONSUMPTION-PHASE4B-2026.10.05-18` ativa D1001 financeiro com26 atividades oficiais da Tabela21 da documentação1.3.0. Exige cinco provas externas independentes e datadas, compatibilidade da atividade com a operação mensal e hash do catálogo vinculado à política. Cadastro/calendário dos positivos são sintéticos; a tabela não fornece vigências fiscais individuais. XSD D1001 compatível; eventos mensais mantidos no pacote1.2.0 observado, sem demonstrar homologação1.3.0 integral. Suíte800/2.658, corpus8/8 e regressões18/17 passaram; PHPStan/Pint aprovados;1.660 históricos preservados. Gate41/41 fontes, `ready=false`,119 bloqueios. Detalhes e critérios: [ativação D1001](phase4b-dere-contributor-activation.md). O corte inicial abaixo é histórico; PGCC e demais famílias continuam pendentes.

## Corte de05/10/2026

Implementados `DereContributorQualifier` e `DereContributorXmlProjector`, contrato `DERE-CONTRIBUTOR-LOCAL-2026.10.05-1`. Reconstroem inclusão original unsigned em ambiente restrito, namespace `evtInfoContrib/v1_0_1`, com início/fim de validade, regime principal, até três regimes secundários, natureza tributária e grupos financeiro, saúde e prognósticos. Atividades e UFs de credenciamento são conferidas contra catálogos separados, previamente qualificados e datados; não há código de atividade fiscal padrão.

O perfil precisa declarar completude e provas de identidade cadastral, regime, natureza tributária e início da obrigação, com hashes e observação temporal. A situação cadastral suportada neste corte é `ACTIVE` previamente qualificada. Outras situações retornam `ACTION_REQUIRED` e exigem implementação específica. Exige validade cadastral abrangendo início da declaração até fim da competência, sem consulta externa de veracidade. O início da DeRE não é deduzido da existência do XSD.

Rejeita regime secundário igual ao principal, duplicidades, grupos ausentes/extras, atividades inexistentes ou sem cobertura temporal, cadastro/calendário não comprovados, observação futura e geração anterior ao período ou à observação do perfil. Credenciamento declarado exige prova própria e UF presente no catálogo. `registration_performed=false`, `signature_created=false`, `transmission_performed=false` e `production_ready=false` são explícitos.

## Integração mensal opcional

A política `contributor_projection_required=true` do fechamento mensal exige `month.contributor`, `month.contributor_identity` e `closing.contributor_catalogs`, além da regra `contributor_projection`. Fornecedor, período e hash do perfil devem coincidir com os pré-requisitos e o livro. O cadastro deve incluir o regime financeiro. Havendo D1011, sua identidade cadastral, vigência e prova de início da DeRE devem concordar com o D1001.

`ProjectDereMonthlyClosingXmlCommand` recebe a regra `dere_contributor_projection` do catálogo capturado, valida D1001 no mesmo pacote XSD oficial e grava `caminho.xml.D1001.xml` quando presente. Todos os destinos são conferidos antes da escrita; arquivo existente é preservado. O relatório mantém resultado da qualificação e validação por evento, removendo os corpos XML das projeções aninhadas. As limitações de atomicidade do comando mensal permanecem.

O teste do comando usa manifesto/catálogo isolados e explicitamente sintéticos, cria snapshot compatível, verifica hash do D1001 gravado e recusa de sobrescrita antes de criar os demais destinos. Esses recursos temporários são removidos pelo próprio teste; nenhum bundle histórico é alterado. O candidato17 permanece com os contratos anteriores e não ativa D1001 ou PGCC novos.

## Fontes e validação

Fundamento técnico preservado: DeRE Eventos1.2.0 SHA-256 `7adbc82a55f0d60dfc2a63771a4c65c67d479e6a50a7821079e73f38b21873c7`; AnexoII SHA-256 `b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb`; pacoteXSD SHA-256 `c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818`; assinaturaXSD SHA-256 `d102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d`. Usados os campos do D1001 e as regras `INI_VALID`, `FIM_VALID`, `REG_SEC_DIFERENTE_REG_PRINC`, `EXIGIR_GRUPO_REGIME`, `REJEITAR_GRUPO_REGIME` e membership das tabelas21/31/41/13. A regra cadastral completa possui situações adicionais ainda não implementadas.

O [comunicado oficial SPED](https://www.gov.br/sped/pt-br/assuntos/comunicados/dere/dere-receita-federal-e-cgibs-publicam-versao-1-2-0-da-documentacao-tecnica-da-dere), consultado em05/10/2026, registra publicação do pacote1.2.0 em05/09/2026 e evolução incremental dos eventos transacionais. A publicação técnica não prova calendário obrigatório por contribuinte ou homologação do motor.

Cinco perfis D1001 positivos e o conjunto mensal D1001/D1011/D1101/D1121/D1199 foram conferidos nos XSDs oficiais preservados: somente assinatura ausente. As atividades, credenciamentos e provas cadastrais desses testes são fixtures explícitas; não são catálogos oficiais homologados. Validação final dirigida: **116 testes/236 asserções** nas frentes cadastro, PGCC, fechamento e comando. PHPStan completo sem erros; Pint353 arquivos aprovado, mais conferência do último teste do comando. Suíte ampla anterior ao último teste do comando: **768 testes/2.514 asserções**.

Evidências novas: `storage/app/phase4b-resumption-20261005-1/`. Verificador17 reexecutado e hashes históricos preservados; nenhuma mudança em1.642 artefatos de recursos/evidências comparados. Corpus17:15/15 RTC e3/3 geral; oito replays. Gate17:32/32 fontes, `eligible_for_local_validation=true`, `ready=false`,119 bloqueios.

## Pendências reais

Faltam materialização/qualificação oficial dos catálogos de atividades por vigência/atributos, todas as situações cadastrais, alterações/exclusões de cadastro, aprovação do início de obrigação por perfil, ativação versionada em bundle/corpus e integração documental integral. PGCC ainda precisa de referências/códigos oficiais e tabela de auxiliares qualificados. D1106/D2101, bases negativas, reabertura, financeiro integrado ISS/4A e demais famílias continuam em desenvolvimento. Este corte não conclui a Fase4B nem comprova recepção governamental.

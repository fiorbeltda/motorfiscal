# Estado individual dos 36 requisitos do Agente B (B22, 03/11/2026)

Fonte: `storage/app/phase4b-b22-development-20261103/requirements80-current-matrix.json` (SHA-256 `3496eeba…0556`). Itens do A: `PENDENTE_DE_INTEGRACAO_A` (44).

| ID | Requisito | Estado (B22) | Evidência | Dependência residual |
|---|---|---|---|---|
| 4B.5.1 | direito a crédito | PARCIAL_DEPENDENTE_DO_A | B13 vedacoes; B17 todas as hipoteses de credito presumido da LC214 (44/141) | Integracao ao fluxo (A); atos/formulas externas dos arts.168,169,171,312,380,381 |
| 4B.5.2 | apropriação | PARCIAL_DEPENDENTE_DO_A | B11 ciclo, B14 utilizacao, B18 ciclo completo com conservacao | Ligacao a apuracao no fluxo do A; Selic oficial para acrescimos |
| 4B.5.3 | condicionantes | PARCIAL_DEPENDENTE_DO_A | B02 causas distintas (opcional, gate desligado) | Patch B02 em ConsumptionReformEngine (A) |
| 4B.5.4 | estorno | PARCIAL_DEPENDENTE_DO_A | B09 invariantes, B11 ciclo de estorno com causas legais, B18 | Integracao (A); Selic/regulamento (externo); questao do registro tardio |
| 4B.5.5 | glosa | PARCIAL_DEPENDENTE_DO_A | B09, B11 anulacao por apropriacao indevida | Integracao (A); interpretacao 'glosa' documentada; Selic |
| 4B.5.6 | crédito em importação | PARCIAL_DEPENDENTE_DO_A | Historico 119/122 (import ALLOWED/DENIED/CONDITIONAL) | Documentos definitivos de importacao e prova de pagamento no fluxo de importacao (A, 4B.2.8) |
| 4B.5.7 | operações sem crédito | PARCIAL_DEPENDENTE_DO_A | B13 14 vedacoes com trechos vigentes | Ligacao ao fluxo (A) |
| 4B.5.8 | benefícios que preservam ou anulam crédito | PARCIAL_DEPENDENTE_DO_A | Politica arts.49-52 existente, B15 revalidada | Livros/vinculo documental no fluxo (A) |
| 4B.5.9 | ligação entre documento fiscal e crédito | PARCIAL_DEPENDENTE_DO_A | B09/B11/B18 vinculo documento-credito | Integracao de documentos no fluxo (A) |
| 4B.5.10 | nunca tratar todo resultado zero como equivalente | PARCIAL_DEPENDENTE_DO_A | B02 (5.832 combinacoes) | Patch B02 (A) |
| 4B.6.1 | Materializar e versionar: | DEPENDENTE_DE_PUBLICACAO_OFICIAL | Catalogos versionados (B01, B12, B20) | Vigencia/selecao por ambiente oficial nao publicada |
| 4B.6.2 | cIndOp | DEPENDENTE_DE_PUBLICACAO_OFICIAL | cIndOp 1.03.00 revalidado (B03) | Vigencia por ambiente oficial nao publicada |
| 4B.6.3 | cClassTrib | FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA_DELIMITADA | B20: 173 cClassTrib v1.70 identicos a planilha oficial preservada | Sandbox aceito; validacao externa pendente: revalidar versao vigente no portal NF-e |
| 4B.6.4 | NBS | FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA_DELIMITADA | B20: 1.208 NBS identicas a planilha oficial | Sandbox aceito; validacao externa pendente: revalidar versao vigente |
| 4B.6.5 | correlação oficial quando existir | FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA_DELIMITADA | B01 1.01.00/1.02.00 por oraculo independente; B12 sem autoridade | Sandbox aceito; validacao externa pendente: portal declara Anexo VIII sem regras de negocio/vigencia |
| 4B.6.6 | regra decisória do motor quando não existir correlação ofici | PARCIAL_DEPENDENTE_DO_A | B04/B06: 37+6 regras com fundamento; Q-B06-1 especificada (B07) | Implementacao Q-B06-1 pelo A e 320 casos B07 |
| 4B.6.7 | Distinguir obrigatoriamente: | INTEGRALMENTE_IMPLEMENTADO_E_VALIDADO_NO_ESCOPO | B12 | — |
| 4B.6.8 | OFFICIAL_CORRELATION | INTEGRALMENTE_IMPLEMENTADO_E_VALIDADO_NO_ESCOPO | B12 | — |
| 4B.6.9 | ENGINE_DECISION_RULE | PARCIAL_DEPENDENTE_DO_A | B04/B06/B07 | Q-B06-1 (A) |
| 4B.6.10 | A Pesquisa 34 não contém resposta extensa. Obtenha o artefat | FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA_DELIMITADA | Artefato v1.70 preservado com hash/versao/vigencia por linha; B20 confirma conteudo | Sandbox aceito; validacao externa pendente: recaptura periodica do indice oficial |
| 4B.7.1 | Expandir a projeção da DPS/NFS-e com os campos IBS/CBS, sem  | DEPENDENTE_DE_PUBLICACAO_OFICIAL | 143 local; B03/B05 XSD revalidado | XSD oficial com campos NT009 nao publicado; demais perfis dependem dos fatos de A |
| 4B.7.2 | Usar a documentação técnica oficial e o manual localizado em | FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA_DELIMITADA | B03/B05 fontes oficiais revalidadas byte a byte | Sandbox aceito; validacao externa pendente: XSD NT009 nao publicado |
| 4B.7.3 | K:\GERAL\TI\Projeto - SAAS\Motor Fiscal\Pesquisas Definitiva | INTEGRALMENTE_IMPLEMENTADO_E_VALIDADO_NO_ESCOPO | B12 | — |
| 4B.7.4 | Toda mudança de schema deve ser versionada | FUNCIONAL_SANDBOX_COM_DEPENDENCIA_EXTERNA_DELIMITADA | Versionamento de schemas com hash; pacote PRODREST 20261020 revalidado (B05) | Sandbox aceito; validacao externa pendente: XSD NT009 nao publicado |
| 4B.8.1 | A Fase 4B deve produzir: | DEPENDENTE_DE_EVIDENCIA_OU_AMBIENTE | Matriz de aceite B08/B10/B21 | Integracao A+B e regressao integral (34 pastas ausentes) |
| 4B.8.2 | bundle próprio | INTEGRALMENTE_IMPLEMENTADO_E_VALIDADO_NO_ESCOPO | B12 | — |
| 4B.8.3 | facts e perguntas próprios | PARCIAL_DEPENDENTE_DO_A | Facts/perguntas proprios; fatos B07/B13 especificados | Nova versao de bundle apos patches do A |
| 4B.8.4 | catálogos versionados | INTEGRALMENTE_IMPLEMENTADO_E_VALIDADO_NO_ESCOPO | B12 | — |
| 4B.8.5 | regras temporais | PARCIAL_DEPENDENTE_DO_A | B16 matriz temporal | Correcao do parametro sintetico (A; teste B19 pendente) |
| 4B.8.6 | cálculo separado de IBS e CBS | PARCIAL_DEPENDENTE_DO_A | Historico (separacao IBS/CBS) | Evidencias de calculo do A |
| 4B.8.7 | convivência explícita com tributos antigos | PARCIAL_DEPENDENTE_DO_A | Historico 2026 | Evidencias de convivencia por familia (A) |
| 4B.8.8 | trace | DEPENDENTE_DE_EVIDENCIA_OU_AMBIENTE | Historico (traces/replays) | Regressao integral por familia (pastas ausentes) |
| 4B.8.9 | decisão | DEPENDENTE_DE_EVIDENCIA_OU_AMBIENTE | Historico (decisao/hash/replay) | Regressao integral (pastas ausentes) |
| 4B.8.10 | projeção documental | DEPENDENTE_DE_PUBLICACAO_OFICIAL | 143 local; B05 | XSD NT009 nao publicado; perfis restantes (A) |
| 4B.8.11 | invariantes | PARCIAL_DEPENDENTE_DO_A | B09/B18 invariantes de credito; historico required-path | Invariantes das familias do A |
| 4B.8.12 | cenários de 2026, 2027, transição e regime final | PARCIAL_DEPENDENTE_DO_A | B16 periodos 2026-2033; B17 | Familias do A e parametros oficiais futuros |

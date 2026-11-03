# B08 — Matriz de aceite individual da frente B (36 itens) — Agente B, 03/11/2026

Base `87d22e7` + B01–B07 locais, sem commit. Derivada da matriz B06 (`01b8f515…c8c1`). **Nenhum item aprovado.**
Artefato: `storage/app/phase4b-b08-development-20261103/b08-acceptance-matrix-b.json`.

Situação dos cortes: B01–B06 validados localmente e consolidados. B07 (especificação Q-B06-1, gate e 360 testes
preparados) teve a validação completa interrompida pelo sistema por falta de memória (337 aprovados, 0 falhas, sem
resultado terminal). Por isso **não está consolidada** até nova execução autorizada.

Os 6 itens com dependência `IMPLEMENTACAO_B` (4B.5.4, 4B.5.5, 4B.5.9, 4B.6.3, 4B.6.4, 4B.8.11) são os candidatos aos
próximos cortes independentes. Exigem execução de testes PHP, que aguarda memória disponível.

| ID | Critério de aceite | Classificação | Cortes B | Dependência | Próxima ação |
|---|---|---|---|---|---|
| 4B.5.1 | Direito a credito decidido por perfil (regular, Simples, uso pessoal, importacao, presumidos/especificos) com documento vinculado e fundamento legal por perfil; positivos e negativos por familia. | PARCIAL | — | FONTE_E_IMPLEMENTACAO_B | Pesquisar fonte oficial por perfil e implementar em cortes pequenos. |
| 4B.5.2 | Apropriacao ponta a ponta: elegibilidade -> registro -> consumo do credito no periodo, com proveniencia e condicoes do art.47/48 dependentes de implantacao separadas. | PARCIAL | — | FONTE_OFICIAL_IMPLANTACAO | Separar condicoes dependentes de implantacao oficial; nao presumir. |
| 4B.5.3 | Condicionantes (documento valido, extincao do debito anterior, tratamento do insumo) com causa explicita por resultado e perguntas de correcao. | PARCIAL | B02 | INTEGRACAO_A_B02 | Aguardar patch do A (B02) e validar. |
| 4B.5.4 | Estorno com evento causal/documental, limite ao disponivel e ciclo (registro, reversao) por contexto exigido. | PARCIAL | — | IMPLEMENTACAO_B | Candidato a proximo corte B independente. |
| 4B.5.5 | Glosa com evidencia, ciclo material e integracao a decisao/memoria; distinta de estorno. | PARCIAL | — | IMPLEMENTACAO_B | Candidato a proximo corte B independente. |
| 4B.5.6 | Credito em importacao com documento definitivo, prova do pagamento/extincao e vinculo DI/DUIMP-documento fiscal. | PARCIAL | — | DADO_E_FONTE_EXTERNA | Definir com o usuario as provas de importacao aceitas (DI/DUIMP, pagamento). |
| 4B.5.7 | Operacoes sem credito: todas as exclusoes legais com fonte e testes; NOT_ASSESSED distinto de DENIED. | PARCIAL | — | FONTE_E_IMPLEMENTACAO_B | Pesquisar fonte oficial por perfil e implementar em cortes pequenos. |
| 4B.5.8 | Beneficios que preservam ou anulam credito (imunidade, isencao, aliquota zero, diferimento, suspensao, reducoes) com fonte e livros. | PARCIAL | — | FONTE_E_IMPLEMENTACAO_B | Pesquisar fonte oficial por perfil e implementar em cortes pequenos. |
| 4B.5.9 | Vinculo documento fiscal <-> credito em todos os perfis exigidos, com recusa de documento em branco/divergente. | PARCIAL | — | IMPLEMENTACAO_B | Candidato a proximo corte B independente. |
| 4B.5.10 | Todo resultado zero tem causa e semantica distintas em todas as rotas e periodos ativos. | PARCIAL | B02, B05 | INTEGRACAO_A_B02 | Aguardar patch do A (B02) e validar. |
| 4B.6.1 | Catalogos materializados e versionados com fonte/hash e vigencia por ambiente oficial qualificada. | PARCIAL | B01 | FONTE_OFICIAL_VIGENCIA | Qualificar vigencia/ambiente por fonte oficial antes de ativar. |
| 4B.6.2 | cIndOp com edicoes versionadas, selecao por ambiente e conciliacao com Anexo C. | PARCIAL | B03 | FONTE_OFICIAL_VIGENCIA | Qualificar vigencia/ambiente por fonte oficial antes de ativar. |
| 4B.6.3 | cClassTrib oficial versionado, com selecao aplicavel por perfil sem autoridade automatica. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | IMPLEMENTACAO_B | Candidato a proximo corte B independente. |
| 4B.6.4 | NBS oficial materializada com aplicabilidade temporal e classificacao de negocio por familia. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | IMPLEMENTACAO_B | Candidato a proximo corte B independente. |
| 4B.6.5 | Correlacao oficial preservada por edicao (1.01.00, 1.02.00) sem autoridade decisoria. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | B01 | NENHUMA_ALEM_DE_VIGENCIA | Manter edicoes; vigencia oficial do Anexo VIII nao declarada pelo portal. |
| 4B.6.6 | Toda ENGINE_DECISION_RULE com fundamento verificavel; controversias decididas e implementadas. | PARCIAL | B04, B06 | INTEGRACAO_A_B07 | Aguardar implementacao do A (Q-B06-1) e executar os 320 casos B07. |
| 4B.6.7 | Separacao estrutural OFFICIAL_CORRELATION x ENGINE_DECISION_RULE mantida em todas as versoes e mutacoes. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | B04, B06 | NENHUMA | Manter invariante nos proximos cortes. |
| 4B.6.8 | Toda correlacao com kind OFFICIAL_CORRELATION e decision_authority=false, em todas as edicoes. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | B01 | NENHUMA | Manter invariante nos proximos cortes. |
| 4B.6.9 | Toda regra do motor com kind ENGINE_DECISION_RULE e validacao por perfil. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | B04, B06 | INTEGRACAO_A_B07 | Aguardar implementacao do A (Q-B06-1) e executar os 320 casos B07. |
| 4B.6.10 | Artefato oficial atual do cClassTrib preservado com hash, versao e vigencia; revalidacao periodica. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | REVALIDACAO_PERIODICA | Revalidar bytes no portal a cada corte. |
| 4B.7.1 | Projecao DPS/NFS-e com campos IBS/CBS para todos os perfis exigidos, valida no XSD oficial vigente, sem assinar/transmitir. | PARCIAL | — | XSD_OFICIAL_NT009_NAO_PUBLICADO | Monitorar publicacao do XSD oficial com campos NT009; nao adaptar schema. |
| 4B.7.2 | Uso da documentacao tecnica oficial vigente, com revalidacao de bytes das fontes. | PARCIAL | B03 | REVALIDACAO_PERIODICA | Revalidar bytes no portal a cada corte. |
| 4B.7.3 | Manual municipal consultado e preservado (SHA-256). | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | NENHUMA | Manter invariante nos proximos cortes. |
| 4B.7.4 | Toda mudanca de schema versionada com hash e fonte; schema oficial nunca adulterado. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | B03, B05 | XSD_OFICIAL_NT009_NAO_PUBLICADO | Monitorar publicacao do XSD oficial com campos NT009; nao adaptar schema. |
| 4B.8.1 | Todos os requisitos funcionais da Fase 4B com aceite individual comprovado. | PARCIAL | B05 | INTEGRACAO_A_B_E_REGRESSAO | Integracao A+B autorizada + regressao integral final. |
| 4B.8.2 | Bundle proprio versionado carregado nativamente. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | NENHUMA | Manter invariante nos proximos cortes. |
| 4B.8.3 | Facts e perguntas proprios cobrindo os perfis exigidos, incluindo os fatos B07. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | INTEGRACAO_A_B07 | Aguardar implementacao do A (Q-B06-1) e executar os 320 casos B07. |
| 4B.8.4 | Catalogos versionados com hash de conteudo vinculado ao manifesto. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | B01 | NENHUMA | Manter invariante nos proximos cortes. |
| 4B.8.5 | Regras temporais versionadas; parametros futuros indisponiveis recusados explicitamente. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | PARAMETROS_FUTUROS_OFICIAIS | Aguardar parametros oficiais futuros; manter recusas explicitas. |
| 4B.8.6 | Calculo separado IBS estadual, IBS municipal e CBS em todos os perfis. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | EVIDENCIA_A | Receber relatorio de integracao do A. |
| 4B.8.7 | Convivencia com tributos antigos por familia e periodo de transicao. | PARCIAL | — | EVIDENCIA_A | Receber relatorio de integracao do A. |
| 4B.8.8 | Trace nao vazio e replay deterministico por familia. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | REGRESSAO_INTEGRAL | Recuperar as 34 pastas da maquina antiga e rodar a regressao integral apos integracao A+B. |
| 4B.8.9 | Decisao deterministica com hash e replay; recusas futuras explicitas. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | REGRESSAO_INTEGRAL | Recuperar as 34 pastas da maquina antiga e rodar a regressao integral apos integracao A+B. |
| 4B.8.10 | Projecao documental exigida para todos os perfis. | PARCIAL | — | XSD_OFICIAL_NT009_NAO_PUBLICADO | Monitorar publicacao do XSD oficial com campos NT009; nao adaptar schema. |
| 4B.8.11 | Invariantes por familia/periodo com mutacoes positivas e negativas. | IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL | — | IMPLEMENTACAO_B | Candidato a proximo corte B independente. |
| 4B.8.12 | Cenarios 2026, 2027, transicao e regime final por familia, com dados ausentes, conflitos e versoes fora de vigencia. | PARCIAL | — | PARAMETROS_FUTUROS_OFICIAIS | Aguardar parametros oficiais futuros; manter recusas explicitas. |

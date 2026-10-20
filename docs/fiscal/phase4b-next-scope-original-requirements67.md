# Cobertura funcional dos requisitos originais4B.1–4B.8 — 67

Objetivo: após a aceitação dos perfis sem movimento66, confrontar o módulo integrado IBS/CBS + ISS + Fase4A com cada requisito original, identificar desenvolvimento ainda viável e executar a próxima frente comprovadamente incompleta. A aceitação de corpora DeRE não será usada como prova de conclusão das demais rotas.

Fontes de requisitos: plano original anexado em `e85edde7-ac3a-4a4a-b380-9cf54627bd84`, itens4B.1–4B.8; instrução de implementação funcional anexada em `38472c6b-c887-4922-9b01-c44d96618036`, seção “Escopo obrigatório”; continuidade anexada em `f2fc3be3-3301-4cb9-a367-19ffd08531ae`; critérios4B.8 anexados em `e0404e58-c85b-4a11-a1f1-de5fd3da4f13`. A homologação requerida é técnica local. Frontend, autenticação, billing, multitenancy, assinatura e transmissão governamental ao vivo estão fora desse pedido. Produção permanece bloqueada até aprovação formal.

Critérios da auditoria e continuidade:

1. Temporalidade: mapear2026–2033, datas relevantes, vigência, publicação, revogação, supersessão, convivência e recusa de parâmetros futuros. Distinguir fórmula implementada de parâmetro oficial ainda indisponível.
2. Incidência/local: mapear operações onerosas/não onerosas, destino, B2B/B2C, presencial/remoto/digital, importação/exportação, estabelecimentos/domicílios e intermediários. Conferir código, política selecionada, fatos necessários e corpus específico.
3. Papéis/recolhimento: conferir fornecedor, adquirente, destinatário, importador, plataforma, contribuinte e responsável; split, recolhimento pelo adquirente e regimes públicos/especiais. Uma guarda operacional não demonstra que todos os cálculos e documentos estejam materializados.
4. Base/cálculo: conferir exclusões, descontos, acréscimos, reembolsos, operações combinadas, alíquotas/reduções/regimes, arredondamento e componentes estaduais/municipais/CBS independentes, com memória reproduzível.
5. Créditos: conferir direito, apropriação, condicionantes, estorno, glosa, importação, ausência de crédito e efeitos dos benefícios, vinculados à operação/documento. Preservar distinções entre resultados monetários zero.
6. Catálogos: conferir artefatos oficiais, hashes, versões, vigência e diferença entre `OFFICIAL_CORRELATION` e `ENGINE_DECISION_RULE`, sem converter inferência do motor em correlação oficial.
7. Documentos: conferir DPS/NFS-e RTC local e os caminhos específicos aplicáveis, incluindo campos do emissor versus enriquecimento nacional, schemas e migrações documentais. A ausência de assinatura/transmissão não é uma lacuna desse escopo local.
8. Aceitação: mapear bundle, facts/perguntas, catálogos, temporalidade, IBS/CBS, tributos antigos, trace, decisão, documento, invariantes e cenários dos anos/fases exigidos. Produzir matriz por requisito com evidência suficiente, contradição, desenvolvimento pendente ou dependência externa explicitamente identificada.

A leitura preliminar do `ConsumptionReformEngine.php` encontrou guardas `NOT_MATERIALIZED` para algumas combinações de doação/operação não onerosa, exportação, importação/câmbio, plataforma, compra pública e Simples. Esses nomes isolados não provam uma lacuna na política atualmente selecionada: o ramo pode ser condicionado a dados ausentes, catálogo sem aquela capacidade ou rota fora do recorte local. Cada candidato deve ser confrontado com a política e os cenários antes de definir sua prioridade.

O próximo desenvolvimento deve atacar uma lacuna funcional confirmada, com pesquisa primária, fonte temporalmente qualificada, política/bundle quando necessário, integração e execução completa do fluxo. Não alterar guardas para aceitar parâmetros ou fontes não comprovados. Preservar implementações/evidências aceitas e emitir novos resultados. A Fase4B só será concluída após auditoria requisito por requisito, mantendo o gate de produção separado.

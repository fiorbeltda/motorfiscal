# D1021 e exclusão por processo — pesquisa64

Objetivo: qualificar o processo administrativo/judicial referido em exclusão pública por motivo1 antes de permitir essa exceção na cadeia D2101/D9121.

O leiaute1.3.0 do D2101, página física36, exige `nrProc` quando `motExcl=1` e declara que o número deve ser válido e existente no D1021. O AnexoII, página física7, registra a mesma exigência em `EXIGIR_NUMERO_PROCESSO`. Esses trechos não definem, por si, o leiaute de origem nem a validade temporal do cadastro D1021. Um número com formato aceito pelo XSD do D2101 não comprova a existência do processo.

Em13/10/2026, o [portal oficial CGIBS](https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere) ligava três pacotes distintos: produção, piloto e documentação1.3.0. Os três foram baixados em arquivos novos, preservando os anteriores. Os inventários contêm, respectivamente,5,9 e32 arquivos. O pacote documental tem SHA256 `3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd`, idêntico à fonte preservada usada no motor. Os arquivos examinados não oferecem um XSD de origem D1021. O retorno genérico de tabela não permite reconstruir um evento de origem ausente nem qualificar sua semântica.

O aviso de governança dentro do pacote1.3.0 o identifica como documentação para desenvolvimento antecipado, com implementação progressiva nos ambientes. Isso reforça a distinção entre validação documental local e compatibilidade com recepção operacional. A pesquisa não altera a seleção de fontes ou os controles de produção do motor.

Uma execução própria exercitou quatro cadeias com o seletor permanente: exclusão ordinária sem processo, motivo1 com número sintético, motivo1 sem número e outro motivo acompanhado de número. A primeira conserva a exclusão e deixa o evento ativo ausente; as três dependentes de processo retornam `EXCLUSION_PROCEEDING_QUALIFICATION_REQUIRED`. O resultado foi reproduzido exatamente. O número usado é apenas fixture, sem alegação de processo real.

Fatos comprovados: obrigatoriedade de referência a D1021 para motivo1; inventários e hashes dos pacotes efetivamente examinados; preservação da guarda atual. Questões de implementação ainda sem prova suficiente: namespace e XSD oficial de origem D1021, identificação da decisão/cadastro vigente, correlação do retorno com essa origem, validade para contribuinte e competência, efeitos de alteração/exclusão do cadastro e prova de recepção do ambiente correspondente.

O escopo64 fica com pesquisa e guardas verificadas, enquanto a qualificação e ativação do processo permanecem pendentes de fonte suficiente. Essa pendência específica não impede os escopos financeiros que utilizam exclusão ordinária ou nenhuma exclusão. Próximo avanço viável: ampliar as cadeias públicas economicamente reconstruídas para os demais cenários do corpus56, conservando histórico contextual, stock BCN por origem e regressões. Produção continua bloqueada.

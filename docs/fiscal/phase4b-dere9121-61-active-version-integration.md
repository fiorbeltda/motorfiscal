# Integração da versão pública ativa — 61

Objetivo: emitir no comando mensal o D2101 efetivamente ativo de uma cadeia sintética qualificada, depois de conferir seu conteúdo econômico contra o livro, PGCC e memória financeira reconstruídos. O bundle próprio `2026.10.13-28-draft61` habilita explicitamente essa capacidade; os bundles anteriores conservam suas políticas.

A seleção permanente acompanha inclusão00, alteração01, exclusão02 e reinclusão03. Alteração substitui a versão anterior; exclusão remove o ativo; reinclusão continua a sequência. Recibos, hashes, contribuinte, competência, cronologia e campos por operação são conferidos. A integração exige igualdade dos grupos econômicos completos com o D2101 nativo, em vez de aceitar somente igualdade do agregado. D9121 entra uma única vez na composição.

O comando mantém o hash da projeção nativa como referência independente para a solicitação D1199 e emite os bytes da origem ativa no arquivo D2101. Os cinco demais XMLs preservam o fechamento previamente capturado. Os cenários próprios01 e03 mantêm IBS0,74 e CBS7,02; o cálculo bruto original continua separado. Os três cenários sem movimento do bundle58 e os vinte cenários públicos do corpus56 devem reproduzir integralmente seus relatórios e XMLs históricos.

As fontes alteradas foram arquivadas antes da integração, incluindo as duas implementações aceitas58 e o comando aceito55. Os relatórios anteriores continuam descrevendo suas gerações; a validação61 confere os arquivos arquivados, sem alterar seus mapas de hashes.

Aceitação local requer testes dos componentes e comando, PHPStan, Pint geral, captura e replay dos cenários01/03, regressões56/58/25/26/27, replay financeiro, reprodução do bundle e suíte completa terminal. `integration-progress-validation.json` registra a evidência anterior à conclusão da suíte; somente `version-development-validation.json`, caso emitido pelo auditor após verificar a suíte, comprova essa etapa final.

O alcance permanece sintético: estado aberto é declarado no contexto mensal, e recibo PGCC não é autenticado externamente. Alterações de competência efetivamente encerrada/reaberta precisam de prova contextual adicional. Exclusão por motivo1 e qualquer processo indicado continuam recusados até qualificação do D1021. Essa integração não conclui o escopo59 completo, o escopo54 amplo, o escopo28 ou a Fase4B. Não há assinatura, transmissão, contabilização, autenticação governamental ou liberação de produção.

Próximo trabalho: qualificar o estado da competência e a cadeia de fechamento/reabertura, depois o D1021 e os demais perfis econômicos, conforme os critérios do plano59. A sequência do D2101 nunca substitui a sequência do fechamento na formação do codBCN.

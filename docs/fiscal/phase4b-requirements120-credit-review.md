# Créditos120 — auditoria parcial dos dez requisitos4B.5

74 cenários foram observados no runtime119:148 chamadas nativas,142 decisões
concluídas e seis exceções de domínio em três casos. Não foram alterados código,
catálogos ou fixtures durante a investigação.507 conferências independentes,
incluindo180 comparaçõesDecimal;91 hashes119 preservados no corte observado.

## Matriz por requisito

|ID|Cobertura observada|Pendência para conclusão|
|---|---|---|
|4B.5.1 Direito|ALLOWED,DENIED eCONDITIONAL por perfil/tratamento/documento.|Elegibilidade e provas materiais completas,créditos presumidos/especiais e demais perfis.|
|4B.5.2 Apropriação|Crédito da operação separado do livro mensal previamente qualificado.|Ciclo e proveniência da apropriação,condições doart.48 e ligação integral entre registros.|
|4B.5.3 Condicionantes|Ausência de prova e conflitos impedem qualificação;documento inválido/débito não extinto condicionam.|Tipos e identificadores aninhados significativos;demais exceções e provas de extinção.|
|4B.5.4 Estorno|REVERSAL e valores negativos/excessivos tratados.|Evento material,documento,identidade de linha e demais hipóteses de estorno.|
|4B.5.5 Glosa|DISALLOWANCE altera valores de forma segregada.|Evidências vazias ainda aceitas;contratos materiais e ciclo completo de glosa.|
|4B.5.6 Importação|Contrato sintético atualizado alcança crédito permitido,negado econdicionado e guardas próprias.|Documento definitivo,papéis/provas materiais e cobertura completa da importação.|
|4B.5.7 Sem crédito|Uso pessoal/não regular negados;saída não avaliada não vira crédito negado.|Demais exclusões e exceções com prova qualificada.|
|4B.5.8 Benefícios|Entrada e saída diferenciadas;anulação proporcional e preservação em zero/reduzidas/exportação observadas.|Demais benefícios/regimes e livros materiais completos.|
|4B.5.9 Documento|Vínculos de tratamento e importação conferidos.|Documento em branco alinhado às linhas ainda passa;identidade/proveniência e ciclo documental completos.|
|4B.5.10 Semântica zero|DENIED,CONDITIONAL eNOT_ASSESSED_ON_OUTPUT preservados;IBS/CBS assimétricos.|Demonstrar essas invariantes em todas as rotas/períodos restantes.|

Todos permanecem parciais. Agora54 IDs originais foram examinados,26 ainda não
revistos;nenhum requisito/grupo foi formalmente encerrado.

## Falhas concretas preservadas

Em `rtc.credit_adjustments`, valor booleano verdadeiro vira1.00; número
binário1.1 é admitido e reduz IBS de2.00 para0.90. Esse exemplo demonstra
ausência de contrato de tipo exato,não erro isolado de arredondamento1.1.
Booleano falso,string vazia e string em branco lançamInvalidArgumentException
nos dois executores,seis exceções em vez de recusas controladas.

Evidência vazia/em branco aceita glosa de0.25. Duas linhas idênticas com a
mesma evidência estornam0.50 em vez do0.25 do controle;falta desambiguação de
linha/contexto,sem concluir que toda referência documental repetida é ilegal.
`rtc.input_document_id` em branco e linhas de tratamento igualmente em branco
mantêm créditoALLOWED. A correção deve ser versionada e preservar os testemunhos
anteriores,sem inferir autenticação real ou apagar o contexto de entrada.

Inteiro1 foi observado separadamente e sua exatidão não deve ser confundida
com booleano ou número binário. Guardas existentes de imposto desconhecido,
valor negativo/excessivo,tratar crédito suspenso somente pelo principal e
limites de tratamento permanecem evidenciadas.

## Importação e origem das provas

As oito fixtures antigas de importação foram observadas sem completar campos
novos implicitamente:na versão119, várias param antes por falta de tratamentos
da entrada. Elas não foram apresentadas como cobertura de uma guarda posterior.
Em mais nove casos,novos registros de tratamento foram declarados explicitamente
como sintéticos,com vínculo ao documento da fixture. Esses casos alcançam
crédito1.00/9.00,reversão0.75/8.00,negação,condicionamento e recusas próprias de
documento/extinção. A obrigação documental final permaneceACTION_REQUIRED.

Reutilizada,sem nova observação ou ativação,a captura117 da
[LC214 compilada](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm),
arts.47–57 e64. Anulação do fornecedor,crédito do adquirente e compensação mensal
são contratos distintos;um resultado aritmético não comprova pagamento,cadastro
autenticado,apropriação efetivada ou transmissão governamental.

Evidência: `D:/ProjetoSaasExports/phase4b-requirements120-credit-audit-20261020-v1`.
`native-probe-v1` mantém65 casos;`native-probe-v2` contém74,com a ponte explícita
de tratamentos de importação. O leitor v1 falhou ao tratar uma decisão recusada
como objeto; código/falha preservados. O leitor v2 examinou os mesmos dados já
capturados,sem repetir o motor para corrigir esse erro de leitura,e produziu
`requirements-review-v2/requirements120-credit-review.json`.

Esta auditoria não substitui a qualificação119 nem encerra aFase4B. Produção
permanece bloqueada. Próxima correção deverá cobrir os contratos de ajuste de
crédito e documento significativo,com positivos,recusas,replays e preservação
dos demais condicionantes;depois seguir os26 IDs e demais lacunas originais.

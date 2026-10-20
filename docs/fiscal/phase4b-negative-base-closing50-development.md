# Bases negativas50 — declaração local no D1199

O bundle `2026.10.09-28-draft50` contém catálogo, controles e política próprios para declaração local de aproveitamento. As regras históricas de48 e os componentes49 foram preservados. A política específica seleciona contrato, conhecimento disponível, validade, hashes do MOD/AnexoI/XSD e modo explicitamente sintético.

`DereNegativeBaseClosingOptionsXmlProjector` recebe uma projeção mensal anterior íntegra, vinculada por hash, e um pedido separado. Qualifica fornecedor/período, reconcilia a cadeia da competência imediatamente anterior, verifica raízes e origens do estoque ativo e acrescenta `gUtilizBCN` ao D1199. Os filhos `infoBCN` e `detBCNeg` e seus campos são serializados na ordem do schema; os grupos já existentes de encerramento são conservados. Tanto o XML de entrada quanto o de saída são conferidos pelo XSD oficial sem alteração das fontes.

Essa declaração informa opção/método e limites solicitados. Não altera débitos brutos calculados pelo motor nem afirma consumo efetivo, recepção, assinatura ou encerramento governamental. O modo de recepção real permanece sem qualificação. Origens no mês do quinto aniversário ou depois continuam exigindo calendário/fronteira; a pesquisa pendente não foi convertida em autorização presumida.

Validação conjunta dos seis componentes49/50:88 testes/294 asserções; PHPStan do novo projetor e Pint aprovados. O registro inicial de falha de tipagem PHPStan e a fonte anterior foram preservados; o refinamento passou com guardas explícitas de hash e formato da cadeia. Captura/reprodução nativa da extensão e XML sintético estão em `storage/app/phase4b-negative-base50-closing-draft-20261009`. O D1199 original de48 permanece intacto.

O protótipo utiliza o fechamento `first-gain` nativo preservado de48 e uma cadeia sintética de agosto derivada dos exemplos49. A projeção nova é executada pelo script dedicado de captura; ainda não está selecionada automaticamente pelo comando `fiscal:rtc:project-monthly`. Não houve nova suíte completa nem recaptura do corpus integral sob50.

Ainda faltam: integração dessa política no comando mensal com snapshots/replays do motor, bases correntes por natureza e tributo comprovadas, cálculo de consumo aplicado à apuração tributária, calendário/fronteira, autenticidade e reprocessamento das dependências, negativos semânticos ampliados e regressão integral. O [escopo49](phase4b-next-scope-negative-bases49.md), o escopo28 e a Fase4B.8 continuam abertos. O candidato integral aceito permanece27 e produção bloqueada.

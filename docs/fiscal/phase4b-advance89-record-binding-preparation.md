# Antecipações89: vínculo de registros anteriores, preparação externa

## Resultado delimitado

Componente externo em
`D:/ProjetoSaasExports/phase4b-advance89-record-binding-preparation-20261015/`.
Fonte candidata final: `PriorAdvanceRecordSet89-formatted-v2.php`.
Relatório exclusivo: `staged89-record-binding-validation.json`.

43 casos nativos PHP do contêiner aprovados, incluindo quatro snapshots
adicionais avaliados pelo runner RTC com knowledge88 capturado. PHPStan nível8
e Pint do componente final aprovados. Auditoria conferiu todos os4.692 arquivos
de entrada congelados da regressão88, sem diferenças nem novas entradas de
runtime/testes/recursos fiscais. Nenhum componente89 foi instalado no motor.

## Contrato local

O verificador recebe contexto do definitivo, conjunto de snapshots e livro
independente previamente qualificado, identificado e vinculado por hash ao
contexto. Exige conjunto completo declarado das parcelas, fornecedor/operação/
contrato, BRL, destino, registro anterior ao fornecimento final, conhecimento,
período explicitamente ligado ao evento e ciclo de vida aberto.

Confere hash canônico do snapshot, corpo do resultado e replay nativo com
knowledge capturado. Os registros precisam referenciar snapshot/decisão/evento
e componentes IBS estadual, IBS municipal e CBS, com valores e fontes iguais
aos capturados. As somas usam esses valores; não recalcula a antecipação pela
alíquota atual do definitivo. Ordem dos registros ou chaves não altera o
resultado; IDs `0`, `01` e `0001` continuam distintos.

Recusa parcelas/registro/pagamento/snapshot duplicados, conjunto faltante ou
extra, vínculo estranho, valor/fonte divergente, registro revogado, substituído,
consumido, estado desconhecido, data futura, registro anterior ao snapshot ou
posterior ao fornecimento e total antecipado superior à base final.

O livro de teste é sintético. O resultado positivo é apenas
`LOCAL_PRIOR_ADVANCE_RECORD_SET_ONLY`: `prior_registration_locally_bound=true`,
mas `prior_advance_debits_proven=false`, autenticação governamental/extinção/
apropriação/lançamento/produção falsos. Prova contábil local declarada não é
recibo governamental, e vínculo íntegro não resolve a obrigação documental.
Não usa o bucket mensal de dívidas vencidas como se fosse antecipação.

## Descoberta de integridade e primeiras tentativas

`RtcScenarioRunService::replay` compara o `decision_hash` salvo com o hash do
resultado recalculado. Isso, isoladamente, não confere que o corpo salvo tenha
permanecido intacto se o hash antigo for mantido. O verificador89 passa a
conferir primeiro o hash canônico do corpo salvo. Casos separados adulteram
apenas o corpo e adulteram corpo mais hash; ambos são recusados.

A primeira tentativa recusou a adulteração no vínculo independente do livro,
mais adiante que o ponto esperado. O PHP/Laravel imprimiu a exceção com exit0:
essa execução não é aceitação. O wrapper final exige JSON estruturado de
sucesso e43 casos aprovados. Logs/fontes anteriores preservados. A primeira
formatação falhou por propriedade do arquivo temporário copiado; somente a
cópia `/tmp/PriorAdvanceRecordSet89.php` foi formatada com usuário adequado.
O fonte final foi copiado como novo arquivo e novamente testado.

## Continuidade exigida

Após resultado terminal e auditoria88: arquivar o predecessor; integrar o
contrato próprio, fatos/perguntas e validações permanentes; reforçar o replay
RTC quanto ao corpo salvo sem alterar hashes históricos íntegros; compor o
definitivo com os valores capturados, qualificação do registro e estados de
uso único. Validar valores negativos, retificação/cancelamento e distinção
entre débito, caixa e crédito. Novos runners/corpora e regressão continuam
obrigatórios. Isto não conclui temporalidade integral, Fase4B ou produção.

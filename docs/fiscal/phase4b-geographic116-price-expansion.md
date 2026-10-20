# Geografia116 — expansão de preços e regressão pendente

Os124 cenários do corte112 foram transportados para o bundle116, sem alterar
os valores monetários nem os fatos materiais fornecidos. Há249 novos testes
permanentes de transporte. A execução dirigida conjunta com112, geografia116
e ciclo temporal114 passou **593 testes/14.934 verificações**. PHPStan geral
sem erros; Pint geral aprovado em587 arquivos. Os88 hashes de runtime116
permanecem iguais aos do corte geográfico direcionado.

## Temporalidade preservada

A primeira construção ampliada recusou uma prova que, no conhecimento antigo,
era positiva, mas cuja revogação se tornou efetiva antes do novo conhecimento.
A tentativa e o código anterior estão preservados. Nenhuma saída de recursos
foi produzida por essa tentativa interrompida.

Dois cenários mudam legitimamente de composição local positiva para
ACTION_REQUIRED: `future-revoked_at` e `future-superseded_at`. Ambos mantêm o
evento literal `2026-10-19T21:00:00-03:00`, posterior ao conhecimento antigo e
anterior ao novo. Não deslocar eventos para manter resultados positivos.

No transporte, somente declarações sintéticas de mapeamento/preço/composição
são reemitidas para seus vínculos atuais. Datas materiais, regime, preços,
descontos, demais fatos de negócio, defeitos de hash e eventos de ciclo de vida
originais permanecem preservados. O caso negativo de observação futura usa um
novo instante futuro explicitamente sintético; não representa captura oficial.

Resultado esperado neste conhecimento:71 composições locais positivas,
incluindo11 fontesUSD, mais as recusas originais e as duas novas recusas de
ciclo de vida. O conhecimento sintético é20/10/2026,12:06:00.123456UTC,
posterior à disponibilidade do bundle e à observação da referênciaIBGE.
Nenhum preço local passa a representar base/impostoISS, conversãoISS, prova de
domicílio histórico, XML ou aprovação de produção.

## Evidências e execução atual

Sob `D:/ProjetoSaasExports/phase4b-geographic116-integration-20261020`:

- `price116-scenario-build-v1.log`: primeira recusa preservada.
- `build-geographic116-price-before-temporal-review.php`: código anterior.
- `price116-scenario-build-v2.log` e `price116-upstream-build-v1.log`:74+50 cenários.
- `price116-directed-v1.log`, `price116-phpstan-v1.log`, `price116-pint-v1.log`.
- `geographic116-expanded-preparation-validation.json`:3.657 conferências,
  comparação dos124 pares de entradas e preservação dos eventos/provas.
- `expanded-cli-v1/`: coletor em execução, com saídas e snapshots exclusivos.
  O arquivo `process-start.json` identifica o processo; não reiniciar por timeout.

CLI ampliado planejado:248 decisões,248 replays próprios e791 replays
históricos. Enquanto não existir e não for auditado o relatório terminal,
essas contagens são escopo planejado, não resultados aprovados. As32 decisões
e32 replays do corpus geográfico curto anterior já estão concluídos, mas não
substituem esta expansão.

Scripts de fechamento preparados, sintaxe conferida, **ainda não executados**:

- `verify_phase4b_geographic116_expanded_evidence.py`: auditoria independente,
  oráculosDecimal e conferência dos17.418 arquivos protegidos112 ou arquivos
  anteriores das duas mudanças autorizadas.
- `capture_phase4b_geographic116_full_suite.py`: somente após corpus terminal;
  suíte única com8.083 testes esperados, não aprovados, e entradas congeladas.
- `finish_phase4b_geographic116_development.py`: somente após suíte e qualidade
  terminais, nova auditoria e preservação do aceite112.

O último aceite integral continua112:7.738/116.599. O corte116 permanece em
5/6 marcos,83% desta entrega delimitada, sem percentual funcional global.
Os oito grupos4B continuam parciais; a auditoria114/115 examinou20 IDs e não
encerrou grupos. Ainda restam60 IDs não revistos nesses cortes, base/câmbioISS,
documentos e demais famílias do plano. Produção permanece bloqueada.

Atualização posterior: o coletor116 terminou248 decisões/replays próprios e791
históricos com entradas intactas. A auditoria independente foi iniciada. As
auditorias117/118 encontraram inconsistências materiais de recolhimento e de
entradas da base, incluindo exceções por booleano. Nenhuma regressão integral116
foi iniciada; os scripts acima continuam preparados, não executados. O próximo
aceite integral deve incorporar correções versionadas, preservando este corpus
como corte intermediário.44 IDs examinados nos quatro cortes,36 não revistos,
sem novo encerramento formal. Ver `phase4b-requirements118-base-review.md`.

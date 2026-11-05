# A156 — handoffs H6–H10 para o Claude, 05/11/2026

Base recebida por fast-forward: `1fa9969b2334806553a08b1a4276e22d1b7d7c8e` (B23), sobre A155 `213f2c5`.
Branch `codex/fiscal-engine-bootstrap`; clone exclusivo `D:/ProjetoSaasWorkspaces/agente-a-fase4b-20261103`.
Esta é a entrega local de implementação/evidência delimitada da rodada; **não é aceite integral dos pedidos,
dos44/80 requisitos ou liberação de produção**. As dependências restantes estão individualizadas abaixo.

| Pedido | Implementação/evidência e arquivos principais | Testes próprios no código final |
|---|---|---|
| H6 | `CreditEvidence156.php` e `ConsumptionReformEngine.php`: documento definitivo, titular, livro e pagamento IBS/CBS com document_id e datas qualificados antes de chamar o ramo IMPORT do CreditDecisionEngine. FactBag completo e provas preservados na trace. | 48 testes /140 asserções, compartilhados com H7 |
| H7 | Mesmo contrato liga cada crédito INPUT e entrada do pool art51/52 a documento escriturado, titular e valores. Exercita manutenção/anulação do fornecedor e vedação do adquirente sem confundir os dois efeitos. Sem posting. | Mesmos48/140; não somar novamente |
| H8 | `TemporalRateCatalog.php`, modo de evidência no motor e fixtures/testes156: estadual, municipal e CBS separados; municipal sintético não zero; seis perfis em24 cenários e duas bases financeiras em seis casos adicionais. | 31 testes /287 asserções;72 oráculos Python Decimal separados |
| H9 | `LegacyCoexistence156Test.php`: matriz2026/2027/2030/2033, metadata legada explícita e bloqueio de saída sintética no runner integrado. Suplemento histórico135 reexecutado comprova valores de ISS/PIS/Cofins no perfil de software. | 27 testes /137 asserções; suplemento72/110 em execução distinta |
| H10 | `LegalBaseReduction156.php`, motor, testes de redução/registro ausente e revisão44: metade da base na hipótese delimitada art12§8, alíquotas intactas; ausência de prova na nova família exige informação. | 19 testes /63 asserções; revisão individual em JSON |

H6/H7 também usam `SyntheticDocumentEvidence156Test.php`. H8 usa `ArithmeticEvidence156Test.php` e
`FinancialEvidence156Test.php`; H10 usa `LegalBaseReduction156Test.php` e `BaseReductionMissing156Test.php`.
Arquivos de fixture/captura/oráculo/revisão/pacote estão em `scripts/agent_a/*156*`.
Contratos e pedidos de fatos/perguntas/gates/classificação: [contracts-H6-H10.md](contracts-H6-H10.md).

## Validação e cortes de código

- Conferência inicial **antes de editar produção**: B23 + HandoffIntegration155,717 testes/1567 asserções, aprovados.
- **Código final** (`final-v2`):844 testes/2198 asserções, zero falhas/erros/skips. Inclui todos os testes novos,
  os dois arquivos da conferência inicial e a aceitação B19. Comando e hashes completos no recibo externo.
- Regressão dirigida ampla v1:2804/80763; v2:2807/80795, ambas aprovadas. São cortes anteriores à última guarda
  de registro ausente e aos rótulos finais dos candidatos. Bytes exatos do motor/fixture de v2 preservados e
  comparados aos hashes do comando. A validação final acima prova esses ajustes no código que será commitado.
- As contagens se sobrepõem: **não somar** execuções, pedidos ou oráculos como cobertura de requisitos.
- A regressão é **PARCIAL**, não integral. A seleção ampla usa68 arquivos;11 arquivos da seleção A154 original
  estão na lista de41 exclusões de B por fixtures ausentes. Não houve regeneração de prova histórica.
- PHPStan global nível8: sem erros. Pint:15 arquivos PHP aprovados. Console em leitura:28 verificações
  aprovadas e endpoint HTTP200 no último corte. Interface e monitoramento global não foram editados.

Os24 cenários aritméticos foram capturados novamente com IDs/versões `A156.IN_MEMORY.TEST` e verificados
por72 oráculos independentes Python Decimal, incluindo soma estadual+municipal e decisão final vazia.
Taxas sintéticas têm `source_kind=SYNTHETIC_TEST`, `synthetic=true`, `official=false` no modo explícito;
fora dele continuam recusadas. Records documentais/livros/redução sintéticos também bloqueiam a decisão final.
Nenhum catálogo/bundle/fato/pergunta instalado foi criado ou alterado por A.

## Revisão e continuidade por B

[requirements44-handoff-H10.json](requirements44-handoff-H10.json) retoma a auditoria A154 com44 IDs,
hashes atuais, provas anteriores preservadas, caminhos testados em cada corte e lacunas restantes.
Resultado:40 PARCIAIS e4 IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL. A ausência total de mecanismo de redução
de base foi superada para art12§8;4B.4.9 agora é PARCIAL, com classificação/ativação material por B pendente.
As demais lacunas funcionais não foram convertidas em dependências externas para esconder trabalho faltante.

[B-reclassification-proposal.json](B-reclassification-proposal.json) propõe a atualização dos sete itens B,
sem aplicá-la à matriz80. [console-proposal-A156.json](console-proposal-A156.json) é proposta para o escritor B.
O console observado ainda é o corte B23, inclusive texto anterior à publicação desse commit; B deve arquivar
esse histórico e atualizar o estado corrente ao consolidar. Fases3/4A fechadas,4B em andamento,5–7 planejadas.

**B precisa continuar:** criar nova versão com os contratos/gates/fatos/perguntas apropriados; criar classificação
real de transporte antes de ativar a redução; validar a integração instalada; consolidar matriz80/console.
H6 conserva a recusa da obrigação documental de importação: provas locais de crédito não resolvem os
impedimentos oficiais de schema/emissão já observados em A143/B23. Taxas futuras e famílias sem prova completa
continuam delimitadas, conforme [dependencies-and-family-coverage.md](dependencies-and-family-coverage.md).
Não declarar os sete itens B completos automaticamente nem interpretar candidato nominal como decisão oficial.

Fonte oficial consultada em05/11/2026: [LC214 consolidada na Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html),
SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`, mesmos bytes da rodada anterior.
Snapshot bruto e recibo de captura preservados no D:. Nenhuma alíquota futura ou parâmetro oficial novo inferido.

## Diff, pacote e publicação

Recibo compacto versionado: [handoffs-H6-H10-receipt.json](handoffs-H6-H10-receipt.json).
Pacote externo: `D:/ProjetoSaasExports/agent-a-A156-H6-H10-20261105-v1/A156-motorfiscal-H6-H10.zip`.
Inclui fontes próprias, logs/JUnit, recibos, fonte oficial e `A156-H6-H10.patch`, baseado em1fa9969.
`delivery-manifest.json` inventaria arquivos/hashes/diff por pedido. Os diffs por pedido são vistas de revisão
com trechos compartilhados; não aplicar cada fragmento como patch independente. Preferir o commit Git completo.
SHA do ZIP e comprovação de publicação ficam nos recibos externos posteriores, evitando hash circular.

Publicação autorizada: `Codex: finaliza handoffs H6-H10 para o Claude`, push normal e comparação com ls-remote.
Recebimento/consolidação por Claude não são presumidos. Continuidade regular em [resume-after-A156.md](resume-after-A156.md).

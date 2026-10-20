# Integração53 — proposta BCN no fechamento mensal nativo

Objetivo: selecionar a política de apuração local52 a partir do conhecimento capturado no snapshot, reconstruir as provas mensais e incluir a proposta de consumo/tributos no relatório nativo de fechamento. Preservar débitos brutos, resultado financeiro original, histórico e bloqueio de produção.

Base disponível: a cadeia composição51 → estoque ativo49 → consumo49 → proposta tributária52 passou em36 testes/88 asserções e captura/replay. O bundle52 tem política temporal própria,51 fontes válidas e gate bloqueado. Depois da auditoria do protótipo52, foi capturado o snapshot financeiro próprio `command-financial-snapshot.json` sob52; seu replay e a correspondência exata das etapas com a proposta foram registrados em `command-financial-validation.json`. O relatório anterior do protótipo conserva a ausência de snapshot próprio existente no momento em que foi emitido.

Critérios de conclusão:

1. Arquivar a fonte atual do comando antes de adicionar seleção explícita da proposta, preservando a identidade da implementação aceita50. Produzir relatório sucessor de evolução e compatibilidade; não reescrever hashes históricos.
2. Selecionar a política somente do bundle efetivamente capturado. Vincular pedido ao snapshot, operação financeira, entrada mensal e débitos brutos reconstruídos; recusar trocas de fornecedor, competência ou conhecimento.
3. Coordenar declaração D1199 e proposta de consumo. Se ambos os pedidos forem fornecidos, parâmetros e cadeia anterior precisam coincidir; divergência deve impedir saída de candidatos.
4. Conservar a memória original e apresentar base positiva após gross-down, dedução anterior, base efetiva, saldo proposto por origem e tributos por componente. A proposta local não qualifica recepção, assinatura, encerramento, prazo ou autorização de lançamento.
5. Capturar pedido/entrada mensal próprios, cinco XMLs originais com opções aplicáveis, relatório e replay exato sob52 ou sucessor temporal necessário. Conservar os XMLs auxiliares salvo a classificação de ISS explicitamente adicionada ao PGCC sucessor.
6. Verificar adulterações semânticas, execução sem novos pedidos, composição não suportada, opção desativada, estoque incompatível, temporalidade e dispensa2026. Rodar regressões50/27/26/25, qualidade adequada, suíte integral quando houver espaço suficiente e auditoria das fontes/artefatos.

Este escopo não encerra classificação de todas as famílias, transição/D9121, geração negativa integrada, calendário/fronteira, autenticidade e dependências. Escopos28/49/51 e Fase4B permanecem abertos; produção permanece bloqueada.

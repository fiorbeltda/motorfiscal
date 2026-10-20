# Semântica de precedência de regras

## Escopo

Relações explícitas `OVERRIDES`, `EXCEPTION_OF` e `SUPERSEDES` resolvem apenas efeitos decisórios concorrentes para o mesmo caminho de decisão. Exemplos: estratégia de localidade, fórmula, incidência, tratamento e grupo de detalhe.

## Efeitos não decisórios

`ADD_WARNING` e `REQUEST_MANUAL_REVIEW` são sinais de segurança cumulativos. Eles não são suprimidos por relações de precedência entre regras.

Essa escolha é conservadora: uma relação normativa que seleciona um valor decisório não constitui, por si só, evidência de que um alerta ou uma exigência de revisão humana deixou de ser aplicável. Para suprimir um desses sinais será necessário um contrato futuro explícito, tipado e com fonte própria; não será permitido inferir a supressão a partir das relações de precedência existentes.

Consequências:

- warnings de todas as regras verdadeiras são agregados e deduplicados;
- qualquer pedido de revisão manual de uma regra verdadeira mantém a decisão em `ACTION_REQUIRED`;
- `ERROR` de avaliação continua prevalecendo sobre fatos ausentes, revisão manual e warnings;
- precedência continua aplicável somente ao conjunto de candidatos do mesmo caminho decisório.

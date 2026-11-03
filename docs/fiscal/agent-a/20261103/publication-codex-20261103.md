# Publicação do desenvolvimento proveniente do Codex

Autorização específica recebida do usuário em 03/11/2026: realizar o commit no
GitHub de todo o desenvolvimento até aqui e identificar sua origem Codex no nome.
Essa autorização supera a restrição de publicação para este conjunto; o
desenvolvimento funcional permanece pausado a pedido do usuário.

## Manifesto de escrita desta operação

Único arquivo novo: `docs/fiscal/agent-a/20261103/publication-codex-20261103.md`.
Os demais caminhos são os arquivos de desenvolvimento A144–A153 já preservados
no inventário `source-package-final/manifest.json` de A153, mais o manifesto
`task-A154-manifest.md`, que registra apenas planejamento e pausa segura.
Nenhuma alteração de produção adicional é necessária para publicar.

Repositório: `https://github.com/fiorbeltda/motorfiscal.git`.
Branch de destino: `codex/fiscal-engine-bootstrap`.
Base local e remota conferidas: `87d22e70787b128a97e5aed4b175a39e619798af`.
Título do commit: `Codex: desenvolvimento do Agente A na Fase 4B (A144-A153)`.

## Conteúdo e validação preservados

- Composição de fornecimentos independentes e principal/acessórios.
- Guardas temporais e referências materiais de recolhimento.
- Localidade de serviços, áreas/extensões, estabelecimento atendido, matriz na
  centralização, domicílio pessoal e critérios de não cadastrados.
- Testes, fixtures próprias, verificadores, manifestos, recibos e handoff de A.
- A154 contém somente manifesto de revisão; não há implementação ou suíte A154.

Antes do commit, os 70 arquivos do pacote A153 foram comparados com seus hashes
preservados e permaneceram idênticos. Último corte dirigido acumulado: 508 testes
e 1.807 asserções; dirigidos A149–A153: 254/947. PHPStan global sem erros, Pint
aprovado e dez oráculos independentes no recibo A153. Não é regressão integral
final integrada, aceite dos 44 requisitos, encerramento de grupo ou produção.

Evidências pesadas, fontes brutas, logs e pacotes de recuperação permanecem no D:
conforme o protocolo. O Git recebe código, testes, fixtures e documentação/recibos
compactos; os caminhos, fontes e hashes estão nos relatórios versionados. Não
incluir ambiente local, credenciais, vendor, caches ou arquivos ignorados.

Nenhum arquivo de escrita de B, catálogo, crédito, schema, matriz ou console
global é modificado por esta publicação. As propostas ao consolidante continuam
no handoff, sem presumir recebimento ou integração. Textos anteriores de ausência
de commit/publicação descrevem seus respectivos cortes históricos.

O resultado efetivo do commit e do push deve ser verificado pelo SHA remoto e
registrado na resposta ao usuário e em recibo local externo, sem inserir uma
referência circular ao próprio SHA dentro deste commit. Não fazer merge, PR,
tag, force-push ou deploy nesta operação.

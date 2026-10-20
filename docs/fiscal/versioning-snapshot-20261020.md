# Snapshot de desenvolvimento — 20/10/2026

Desenvolvimento pausado por solicitação do usuário, antes da implementação119.
Destino solicitado: https://github.com/fiorbeltda/motorfiscal.git.

## Estado preservado

- Último aceite integral:112, com7.738 testes e116.599 verificações.
- Correções114 e116 instaladas; corpus116 terminado e auditado como etapa
  intermediária, sem suíte integral116 nem novo aceite integral.
- Auditorias117/118 concluídas:44 dos80 requisitos examinados,36 ainda não
  revisados e nenhum dos oito grupos4B formalmente encerrado.
- Plano119 documentado, mas não instalado. Produção permanece bloqueada.

O console fiscal mantém os detalhes, os marcos e o histórico. Não interpretar
o percentual da entrega Git como progresso funcional do motor.

## Conteúdo do versionamento

Código, testes, fixtures sintéticas, catálogos, cenários, fontes oficiais,
documentação e scripts existentes são preservados. Os bytes dos arquivos são
mantidos sem conversão automática de finais de linha, pois há vínculosSHA-256.

Não são incluídos `.env`, dependências instaladas, arquivos temporários,
logs, banco de dados ou evidências geradas em `storage/app` e no discoD:.
Essas evidências permanecem no ambiente original; referências locais não são
arquivos distribuídos pelo repositório. Scripts históricos com destinos
exclusivos não devem ser repetidos cegamente nem usados para sobrescrever
aceites anteriores. Um clone não contém o banco nem as evidências de execução.

## Publicação

Na preparação, o GitHub CLI tinha somente `tiacopfiles` autenticada, sem
permissão de escrita nesse repositório. A conta solicitada é `fiorbeltda`,
que precisa ser autenticada para concluir o envio. Nenhum push com outra
identidade foi realizado. A autoria Git local existente foi preservada;
autoria do commit e conta autenticada para envio são conceitos distintos.

Após autenticar `fiorbeltda`, conferir a conta e a permissão de escrita antes
de enviar a branch `codex/fiscal-engine-bootstrap`. Não usar force-push.

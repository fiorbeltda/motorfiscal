# Entradas119 — implementação e validação dirigida

Desenvolvimento retomado após a publicação do snapshot no GitHub. A versão119
é um candidato local; não substitui o último aceite integral112.

## Implementado

- `MaterialInputPolicy119`: contrato obrigatório vinculado à captura117 da
  LC214 compilada. Preserva observação original com precisão100ns e usa teto
  conservador de microssegundos para disponibilidade. Não comprova vigência
  histórica, aplicabilidade fiscal, cadastro, pagamento ou produção.
- `OrdinaryAdjustmentInput119`: verifica valores aninhados antes da conversão
  monetária. Admite string decimal exata e inteiroJSON; recusa booleanos,
  floats, tokens inválidos e identificadores materiais vazios/em branco.
  A mesma evidência/contexto exige referências de linha distintas para
  múltiplas linhas, sem deduplicar nem corrigir silenciosamente os valores.
  Campos irrelevantes não permitem criar um contexto artificial distinto.
- `CollectionBasisReference119`: aliases explícitos vinculados ao método
  fornecedor/adquirente/split. Fundamento desconhecido pede qualificação;
  fundamento reconhecido de outro método recusa o conflito. Fornecedor sem
  declaração opcional continua admitido. Importação mantém contrato próprio.
- Integração na guarda geral de fatos e na rota ordinária de recolhimento.
  Políticas119 e geografia herdada não podem ser removidas do catálogo119
  para contornar as validações, mesmo com hashes de transporte recalculados.

Catálogo, controles e bundle novos: `2026.10.20-119-draft1`.
Disponibilidade do bundle: `2026-10-20T14:22:19.619220+00:00`.
Conhecimento dos novos testes: `2026-10-20T14:22:21.000000+00:00`, contexto
sintético posterior à disponibilidade, não nova observação governamental.

## Resultado observado

350 testes dirigidos /1.079 verificações, incluindo os80 testes geográficos116
e16 testes de ciclo114. Desse total,254 testes são novos119. PHPStan geral sem
erros; Pint geral593 arquivos aprovado.98 fontes oficiais com hashes válidos;
127 bloqueios de produção, sem bloqueio de elegibilidade para validação local.

Preservadas as guardas anteriores de ajuste negativo/desconhecido, desconto
condicionado/documento divergente, reembolso não comprovado, exclusão de
tributo legado, instrumento de pagamento e implantação oficial do split.
Pagamento pelo adquirente permanece elegibilidade com extinçãoUNCONFIRMED.
Compatibilidade dirigida comprova que os defeitos dos bundles históricos112/116
não foram silenciosamente reescritos; isso não substitui replays históricos.

A captura117 da [LC214 compilada](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm)
foi reutilizada byte a byte, não recapturada nem declarada válida retroativamente.
Art.12 sustenta a referência da composição; arts.27II,31–35 e36 identificam as
modalidades, sem substituir suas condições materiais. Apenas essa fonte117 foi
incorporada ao bundle119; as outras duas capturas117 não foram ativadas.

## Evidências e pendências

Diretório `D:/ProjetoSaasExports/phase4b-material-input119-integration-20261020`:
`before-integration-archive.json`, `build-v1`, `directed-v1`, `directed-v2`,
`format-v1`, `format-v2`, `phpstan-final-v1`, `pint-final-v1`,
`production-assess-v1` e `material119-targeted-validation.json`.
O auditor verifica arquivos arquivados e atuais, fontes, transporte canônico,
instantes, saídas terminais e limites. Não publica aceite integral.

Faltam portar e requalificar os124 cenários de preços e o corpus geográfico
curto para119, rebater vínculos sintéticos de identidade/preço/composição após
a disponibilidade do bundle, preservar eventos de revogação/supersessão,
capturar decisões/replays próprios e históricos e executar regressão integral
com auditoria independente. Nenhuma suíte integral119 foi iniciada.

Continuam44/80 requisitos originais examinados,36 não revisados,0/8 grupos
formalmente encerrados. Fases3 e4A encerradas em Sandbox;4B em andamento;
5–7 planejadas. ISS/base/câmbio, documentação e demais requisitos do plano
permanecem no escopo. Produção bloqueada.

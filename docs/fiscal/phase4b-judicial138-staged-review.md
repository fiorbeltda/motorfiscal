# 138 — observações judiciais e efeitos materiais em preparação

Preparação externa noD:durante a integral137. Nenhuma classe138 instalada,
nenhuma aprovação fiscal geral e0/8 grupos4B encerrados.

Em26/10 foram capturados porGET anônimo os originais da página de
[andamentosTema118](https://portal.stf.jus.br/jurisprudenciaRepercussao/verAndamentoProcesso.asp?incidente=2637509&numeroTema=118)
e da [notícia oficialSTF](https://noticias.stf.jus.br/postsnoticias/stf-decide-que-iss-nao-integra-base-de-calculo-do-pis-e-da-cofins/).
O histórico registra mérito em21/10,ata em23/10 euma petição de suspensão
nacional em22/10. A notícia informa que a modulação não foi examinada.
Esses registros não são tratados como decisão de suspensão,prova de trânsito
em julgado,ou alcance material já qualificado para qualquer contribuinte.

Capturas em `D:/ProjetoSaasExports/phase4b-judicial138-20261026-v1/primary-v1`:

- Andamentos:363.091bytes,SHA256 `0333d844643d868d5cb17da782b5f57c1eff1d00db56b2085684a69f93c47545`.
- Notícia:67.341bytes,SHA256 `37e762ee51752e1552f1c0e487755de8f57676aeb708af1e24a41879f834e495`.

`JudicialObservation138.php` fixa os bytes euma data de disponibilidade própria,
reproduz os eventos doHTML sem executar scripts,eaceita somente observação
processual com fatos coerentes. Pedido de autorização para excluirISS da base
das contribuições recebe `MATERIAL_JUDICIAL_EFFECT_SCOPE_REQUIRED`.
Isso limita o protótipo; não decide sozinho o efeito jurídico concreto.

O ensaioPHP reexecutou observações idênticas erecusou16 entradas/consequências,
incluindo retrodatação,fonte alterada,ata promovida a trânsito,petição promovida
a decisão eproposta de voto histórico promovida a regra majoritária.
A auditoriaPython usou parserHTML independente,leu370 linhas de tabela e
conferiu quatro observações;130 hashes de runtime137 etrês de suporte ficaram
inalterados. Recibo externo: `audit-v1.json` na raiz138 doD:.

Pendente: qualificar a fonte e o alcance material que sustentariam uma aplicação
por operação/contribuinte eperíodo;integrar somente após terminar eauditar137.
O cálculo local135/137 e seus snapshots não foram alterados retroativamente.

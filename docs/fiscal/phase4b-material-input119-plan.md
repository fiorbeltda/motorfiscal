# Entradas119 — correção versionada a implementar

Plano histórico:implementação119,corpus,regressão integral e auditoria de aceite
agora concluídos. Estado atual em`phase4b-material119-day-closure-20261020.md`;
não tratar as pendências históricas abaixo como tarefas ainda abertas da119.

Plano de origem, preservado abaixo como checkpoint anterior à implementação.
Desenvolvimento retomado e contratos119 instalados: consulte
`phase4b-material-input119-targeted.md` para os350 testes dirigidos e limites
atuais. Corpus/replays e regressão integral119 continuam pendentes.

O corpus116 terminou e a auditoria independente passou:35.072 conferências,
568 comparaçõesDecimal e232 comparações nominais/cambiais108. Registro:
`D:/ProjetoSaasExports/phase4b-geographic116-integration-20261020/geographic116-intermediate-validation.json`,
SHA256 `c4a41e1267250414357b18e9b0474cee2ec3167bbded99025afc55a974b8f3b1`.
Não é aceite integral:nenhuma suíte completa116 foi iniciada. Os scripts dessa
regressão continuam preservados e não devem ser disparados automaticamente.

As falhas117/118 serão corrigidas antes do próximo aceite integral. Isso não
reduz o objetivo80 itens nem transforma validação de entrada em implantação,
cadastro autenticado, pagamento ou liberação de produção.44 IDs examinados,
36 não revistos, nenhum encerramento formal.112 é o último aceite integral.

## Estado protegido antes da correção

`D:/ProjetoSaasExports/phase4b-material-input119-integration-20261020/before-integration-archive.json`
registra91 arquivos:88 de runtime e manifest/catálogo/controles116. Cópias em
`before-integration/` espelham os caminhos do checkout. Esses são os arquivos
anteriores à119, não os anteriores à112. A cópia antiga do engine112 continua
em `phase4b-geographic116-integration-20261020/ConsumptionReformEngine-before116.php`;
TransitionCatalog anterior à114 continua no arquivo histórico114. Não misturar
os três cortes para fazer um verificador histórico passar.

Nenhuma classe119 está instalada neste checkpoint. Não repetir o arquivador
exclusivo `record_phase4b_geographic116_intermediate.py` nem os geradores116.

## Contrato de correção

1. Validador profundo dos ajustes (`OrdinaryAdjustmentInput119`):
   - aceitar string decimal exata e inteiroJSON representável exatamente;
     recusar booleano e número binário, sem coerção silenciosa;
   - recusar tokens vazios/não decimais antes de chamarDecimalValue;
     manter a guarda existente contra ajuste negativo;
   - exigir identificadores de evidência não vazios/em branco; no reembolso,
     exigir também terceiro e documento identificados;
   - identificar repetição ambígua de uma mesma linha/contexto de evidência;
     pedir desambiguação por referência de linha, sem eliminar/somar novamente
     silenciosamente. Uma referência documental pode conter várias linhas;
   - preservar as guardas de espécie de ajuste, desconto condicionado,
     documento divergente, reembolso e exclusão de tributo legado;
   - integrar antes da conversão monetária e das rotas que possam consumir
     esses valores, após a guarda geral de fatos ausentes.
2. Referência de recolhimento (`CollectionBasisReference119`):
   - referência canônica de norma/método vinculada a fonte/política observada;
     aliases legados conhecidos podem apontar explicitamente para essa referência;
   - branco/ausente pede prova; referência desconhecida pede qualificação;
     referência reconhecida de outro método deve recusar o conflito;
   - contribuinte fornecedor sem declaração opcional de base mantém seu caminho;
     não exigir fundamento doart.36 de importador que segue contrato próprio;
   - não substituir as condições materiais doCollectionDecisionEngine; pagador
     elegível e extinçãoUNCONFIRMED permanecem separados de pagamento/opção efetiva;
   - validar nas rotas ordinárias de recolhimento efetivamente alcançadas,
     sem alegar cobertura de importação/documentos ainda não materializados.
3. Políticas obrigatórias no novo catálogo/bundle119, sem opt-in do chamador e
   sem bypass quando o campo de política for removido do catálogo119. Bundles
   históricos devem preservar comportamento/replays. Não modificar JSONs antigos.

As três fontes117 estão capturadas com hashes/instantes completos. O manifesto
de captura usa precisão de100ns; se o runtime requer microssegundos, manter o
instante bruto e usar teto conservador, nunca disponibilidade anterior. O teto
do encerramento observado é `2026-10-20T12:49:04.592626+00:00`. Uma checagem de
referência normativa não aprova vigência fiscal histórica ou aplicabilidade.
Não copiar timestamps de conhecimento116 para o novo transporte119: reavaliar
as dependências de identidade/preço, preservando eventos originais de ciclo.

## Verificação exigida

- Transformar os testemunhos117/118 em regressões permanentes da versão nova:
  três bases incoerentes, tipos monetários, evidências vazias, exceções e
  repetição ambígua, com controles positivos e guardas anteriores.
- Preservar os comportamentos antigos de112/116; registrar explicitamente que
  os defeitos permanecem nos bundles históricos, sem reescrever snapshots.
- Portar os124 cenários e o corpus geográfico curto com provas sintéticas
  rebases necessárias, não somente trocar nomes de bundle. Manter as duas
  recusas temporais que surgiram com o conhecimento20/10.
- Qualidade, corpus/replays e nova regressão integral119, com auditoria
  independente. Não publicar aceite a partir de testes dirigidos/protótipo.
- Continuar os36 IDs e demais escopos originais, sobretudo ISS/base/câmbio e
  documentos. Oito grupos4B permanecem parciais, produção bloqueada.

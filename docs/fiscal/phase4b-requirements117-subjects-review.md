# Sujeitos117 — revisão dos dez requisitos originais4B.3

Auditoria delimitada concluída, sem alteração de runtime, fontes ativadas ou
aceite funcional integral. São40 cenários observados:78 avaliações nativas nos
dois executores e uma recusa no carregamento tipado. Não são novos testes
permanentes.186 conferências independentes passaram;88 hashes116 preservados.

## Evidência por requisito

| ID | Cobertura observada | Falta para conclusão |
|---|---|---|
|4B.3.1 Fornecedor|Papel próprio, recusa por ausência e ramo estrangeiro.|Qualificação material de contribuinte/cadastro, não contribuintes e demais perfis.|
|4B.3.2 Adquirente|Papel próprio e pagador elegível separado do fornecedor.|Base declarada coerente, demais perfis e pagamento comprovado.|
|4B.3.3 Destinatário|Ausência recusada e adquirente estrangeiro com destinatário brasileiro.|Identidade e domicílio material por contexto, não somente identificadores.|
|4B.3.4 Importador|Contribuinte selecionado entre adquirente/destinatário; conflitos recusados.|Documentos, cadastro, responsabilidade, crédito e parâmetros próprios completos.|
|4B.3.5 Plataforma|Qualificação, exclusões e solidariedade doméstica/estrangeira observadas.|Opções documentais/substitutivas, diferenças de arrecadação e provas materiais.|
|4B.3.6 Responsável|Plataforma responsável separada; aviso de fornecedor não inscrito exigido.|Demais responsabilidades gerais/especiais e efeitos, com provas qualificadas.|
|4B.3.7 Split|Implantação oficial ausente mantém recusa; conflito do instrumento recusado.|Segregação padrão/simplificada, parcelas, conciliação e implantação comprovada.|
|4B.3.8 Recolhimento pelo adquirente|Elegibilidade sem extinção presumida; lacuna de base reproduzida.|Vincular a base material ao método e comprovar pagamento/opção efetiva.|
|4B.3.9 Públicos/especiais|Tratamento2026, exceções e parâmetros posteriores recusados.|Consórcios públicos, regimes mínimos4B e parâmetros/documentos qualificados.|
|4B.3.10 Contribuinte/responsável|Contribuinte, pagador elegível e plataforma têm campos próprios.|Mapeamento e prova material completos em todas as rotas.|

Os dez itens permanecem parciais, sem aprovação formal. A revisão não transforma
um identificador interno em cadastro autenticado, aviso oficial ou pagamento.

## Lacuna reproduzida, ainda não corrigida

No cenário de recolhimento pelo adquirente, o campo material
`rtc.collection_legal_basis` é verificado somente contra ausência. Três valores
— espaço em branco, artigo relativo ao split e texto arbitrário — mantiveram
exatamente a mesma decisãoRTC do controle com `LC214 art. 36`:VALID noRTC e
VALID_WITH_WARNINGS no integrado. A decisão usa a referência estática doart.36,
embora a declaração material fornecida não esteja vinculada/qualificada.

Ausência é recusada; string vazia é recusada pelo carregador. O problema não
é falta dessas duas guardas, nem prova de que houve pagamento: o pagador é
apenas elegível e a extinção continuaUNCONFIRMED. Trata-se de uma inconsistência
material a corrigir por política versionada, com positivos, negativos, replays
e preservação dos bundles históricos. Não reduzir a correção a comparar textos
livres ou apagar o fato material.116 ainda está com corpus congelado; nenhuma
classe foi alterada durante esta auditoria.

Nos cenários públicos2029/2033, o integrado pode parar antes, por ISS/data de
evento. Essas razões foram preservadas; o relatório não as apresenta como se
o integrado tivesse alcançado a mesma guardaRTC dos testes diretos.

## Fontes observadas e limites

Três capturas oficiais completas, com TLS padrão, bytes/hash/observação:
[LC214 compilada](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm),
[texto com emendas](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm) e
[Decreto12.955](https://www.planalto.gov.br/ccivil_03/_ato2023-2026/2026/decreto/d12955.htm).
Foram examinadas definições de sujeitos, responsabilidades, modalidades de
extinção, recolhimento e compras públicas, mantendo CBS regulamentar distinta
de IBS/CBS da lei. Texto riscado não foi silenciosamente descartado na extração.
Observação atual não comprova vigência histórica, implantação remota ou opção
material do contribuinte. Nenhuma dessas três capturas foi ativada no catálogo;
o bundle116 permanece com97 fontes e produção bloqueada.

## Evidências e continuidade

Diretório `D:/ProjetoSaasExports/phase4b-requirements117-subjects-audit-20261020-v1`:

- `primary-source-v1/primary-source-observation.json` e HTMLs preservados.
- `primary-extract-v1/extraction-observation.json`, artigos e extratos indexados.
- `native-probe-v1/execution.json`, `observations.json` e `summary.json`.
- `requirements-review-v2/requirements117-subjects-review.json`:80 itens
  preservados, dez revistos neste corte e três contraexemplos vinculados.

A tentativa v1 do leitor cumulativo parou porque114 usa `reviewed`, enquanto115
usa `reviewed_in_this_cut`. Código e falha preservados; v2 reconhece os dois
contratos e exige exatamente8+12+10 IDs distintos. Não alterou relatórios antigos.

Agora são30 IDs originais examinados nos cortes114/115/117 e50 ainda não
revistos nesses cortes. Isso não significa30 requisitos aprovados:nenhum grupo
foi formalmente encerrado. Primeiro concluir corpus/regressão/aceite técnico116;
depois corrigir a lacuna de recolhimento em nova versão e prosseguir a auditoria,
bases/câmbioISS, documentação e demais requisitos originais da4B.

# Integração local 76 — comprador estrangeiro e consumo brasileiro

## Estado e escopo

Em 15/10/2026 foi ligado o componente estrangeiro 75 ao comando de projeção,
somente no contrato opt-in
`DPS-LOCAL-FOREIGN-ACQUIRER-CANDIDATE-2026.10.15-76` e bundle próprio
`consumption-phase4b-2026.10.15-76-draft1.json`.
É uma integração de decisão tributária com candidato estrutural XML local;
não é qualificação documental integral, assinatura, transmissão ou aprovação
de produção. A suíte completa e os gates finais foram encerrados com sucesso;
a aceitação limita-se ao desenvolvimento estrutural local descrito abaixo.

A reprodução anterior está em `storage/app/phase4b-international76-gap-20261015`.
Exportação integral alcançou resultado integrado VALID_WITH_WARNINGS, mas
faltaram os cinco campos documentais IBS/CBS na projeção. A operação com
comprador estrangeiro e destinatário brasileiro também chegou à decisão
integrada, mas o projetor recusou a identidade estrangeira. Essa segunda rota
foi ligada agora; não foi usada para declarar a primeira ou o par misto resolvidos.

## Implementação e preservação

`DpsForeignAcquirerCandidate` vincula ID lógico do comprador, documento, país,
nome e NIF quando presente nos fatos, sem substituir comprador estrangeiro
por CPF/CNPJ brasileiro. Exige destinatário brasileiro, mercado doméstico,
destino do destinatário, ambiente 2 e contrato próprio. O fragmento de pessoa
usa o componente 75; COMEX usa a sequência e facetas do XSD observado.
Moeda e valor comercial são reconciliados aos fatos originais, sem trocar
vServ, base ou valores tributários da decisão.

O catálogo novo preserva a tabela numérica BCB observada, com hash e instante
de captura. A emissão deve ser posterior à observação dessa fonte. Isso não
comprova vigência histórica da moeda na competência, origem material cambial,
qualificação fiscal do modo COMEX ou autenticação da pessoa. Essas flags
permanecem explicitamente falsas na evidência do candidato. O conjunto de
códigos observados não ativa moedas adicionais para cálculo tributário.

O comando e o seletor reconhecem o contrato novo; chamadas antigas continuam
sem habilitar a pessoa estrangeira. O avaliador mantém o bloqueio local mesmo
com assinatura sintética válida do revisor. Os arquivos anteriores foram
arquivados por hash em
`storage/app/phase4b-foreign-acquirer76-preparation-20261015/` antes das mudanças.
O teste antigo da guarda foi arquivado antes de incluir o segundo contrato.
Os relatórios 74 e de preparação 75 continuam como registros históricos;
não devem ser reexecutados contra um runtime posterior como se fosse o anterior.

## Verificações terminadas

- Corte de projetor/seletor: 55 testes e 143 assertions.
- Guarda de produção e avaliador: 15 testes e 82 assertions.
- Comandos novo/antigo e pessoa estrangeira: 70 testes e 397 assertions.
  Os cortes têm sobreposição e não devem ser somados como uma suíte única.
- PHPStan geral sem erros e Pint geral em 514 arquivos.
- CLI próprio: três candidatos completos válidos no XSD (NIF e dois motivos
  de ausência), três replays exatos, projeção repetida determinística, cinco
  recusas sem escrita de XML e 53/53 fontes conferidas. Princípios não provados
  do contexto fiscal permanecem falsos, mesmo quando o XML é estruturalmente válido.
- ISS 20,00, IBS nominal 1,00 e CBS nominal 9,00 foram preservados; os fatos
  de negócio são iguais aos da reprodução anterior, sem mudar países ou papéis.

Corpus: `storage/app/phase4b-foreign-acquirer76-cli-20261015`.
Conferência preliminar:
`foreign-acquirer-integration-preflight.json`, vinculando código, logs, manifesto,
catálogo, controles, XMLs, entradas, fontes e replays. Nenhuma aceitação integral
de desenvolvimento é declarada por esse relatório.

## Suíte completa — execução e fechamento

Wrapper novo: `D:/ProjetoSaasExports/capture_schema76_full_suite.py`.
Evidência exclusiva:
`D:/ProjetoSaasExports/phase4b-foreign-acquirer76-full-suite-20261015/`.
Handle de observação histórico 38213, agora encerrado. O wrapper usa o checkout real e PHP 8.5, grava
stdout diretamente no D: e compara hashes antes/depois; não confundir timeout
de observação com término. Não editar app/tests/recursos durante a execução.
O fechamento requer resultado terminal, lista de arquivos intacta, conferência
final de fontes e guarda de produção, sem sobrescrever tentativas anteriores.

Auditor de encerramento preparado em
`D:/ProjetoSaasExports/finish_foreign_acquirer76_development.py`.
Sua execução com `--preflight` passou enquanto o Pest permanecia ativo:
conferiu inventário completo (incluindo adições/remoções), hashes de entradas,
dez arquivos de runtime/testes, oito gates direcionados, fontes, fixtures,
corpus e replays. Esse modo não emite aceitação nem executa gates concorrentes.
O modo final exige primeiro `full-suite.json` terminal com saída 0, contagem
real e log íntegro; só então executa PHPStan/Pint gerais e avaliação de produção.
A contagem 2.370 é uma expectativa de composição, não um resultado observado.
O documento anterior a esta anotação está arquivado no diretório da suíte
como `before-final-auditor-doc.md` (SHA-256
`c0ab6f0a5ec832ae491321f8ec82ad1efdb21492d4e639f7988c8efeae513f97`).

Fechamento em 15/10/2026: suíte terminou às 08:50:35 de São Paulo com
**2.370 testes e 46.214 assertions**, exit 0, duração nativa 1.596,20 s.
SHA-256 do log completo:
`ee1a99df0f23e841e1da4bab167c879164f2ccf44b462e09414fe0b12e8bd781`.
O auditor final conferiu 3.775 entradas sem alterações, inclusive inventário
de adições/remoções, repetiu PHPStan geral e Pint (514 arquivos), e avaliou
53/53 fontes, elegibilidade local verdadeira e produção falsa, com 119 bloqueios.
Auditoria e cópia por hash foram concluídas; o modo final não deve ser reexecutado
com os mesmos labels exclusivos.

Aceitação copiada ao checkout:
`storage/app/phase4b-foreign-acquirer76-development-20261015/foreign-acquirer76-development-validation.json`,
SHA-256 `cf075d917afd05e8193358d1867f035e62a589e3f471204f612a5ca0f0fddfba`.
Os doze arquivos de evidência foram copiados por hash; recibo em
`evidence-copy-receipt.json`. Fontes/código/testes não foram modificados durante
a regressão. Os documentos anteriores ao fechamento foram preservados em
`D:/ProjetoSaasExports/phase4b-foreign76-final-docs-before-20261015/`.

Permanecem pendentes: qualificação de país/residência/identidade e moeda por
competência, critérios de ISS/COMEX, exportação integral documental e o par
da exportação parcial, seleção efetiva de schema NT009, matriz dos 80 requisitos
e aprovação de produção. Não inferir emissão real ou obrigação concluída
a partir destes XMLs locais. Fase 4B permanece incompleta.

# DeRE — infraestrutura documental em desenvolvimento

O candidato corrente permanece13. As classes abaixo ainda não são uma decisão financeira final nem uma declaração mensal homologada. Nenhum bundle histórico foi alterado.

## Chave e arredondamento

`DereAccessKey` implementa a regra de formação do anexoII1.2.0: raiz8, país2, tipo1, inscrição20, código de base4, período10, série4, DV1 e sequência3. Soma ponderada módulo11 com ASCII menos48; sequência individual conserva o DV da chave-mãe. Bloqueia períodos de meses distintos, dias invertidos, séries fora1–9999 e filhos fora1–999. IDs estrangeiros usam os últimos20 caracteres. Confere estrutura; não substitui validação cadastral, país/tabela ou enquadramento do código de base.

O exemplo oficial da página física13 foi reproduzido, inclusive DV2. Fonte: anexoII SHA256 `b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb`, preservado em `resources/fiscal/sources/official/dere-supplement-observed-20261002-04-leiautes-da-dere-anexo-ii-regras-de-validacao-v-1-2-0-2.pdf`.

`DereRounding` segue a regra expressa do mesmo anexo: intermediários em8 casas e finais em2, empate exato para o par. Testes usam os exemplos oficiais18,234/18,237/18,2351/18,245/18,235. Base5×0,1% resulta0,00 na regra documental; o cálculo financeiro histórico usa0,01. A integração deverá aplicar a regra correta em versão nova; não copiar automaticamente valores históricos divergentes nem reescrever snapshots.

## Esquema oficial e assinatura

`DereXmlSchemaValidator` confere o ZIP e a dependência XMLDSig por hashes independentes. Extrai somente nomes planos XSD, rejeita entradas duplicadas/caminhos inválidos e declarações de entidades no candidato; valida sem rede. Os arquivos oficiais permanecem íntegros.

Dependência oficial: https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd, arquivo `w3c-xmldsig-core-schema-observed-20261002.xsd`, SHA256 `d102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d`. Registro adicional em `dere-supplement-observation-20261002.json`.

Uma fixture sintética completa de D-2211 compila e passa pela avaliação do corpo no esquema oficial, restando somente a assinatura ausente. Retorna `ACTION_REQUIRED / RTC_DERE_UNSIGNED_LOCAL_CANDIDATE`, `production_ready=false`, `signature_verified=false`. Se houver qualquer erro adicional, não recebe esse resultado. Mesmo XML estruturalmente válido não implica assinatura autenticada nem aprovação operacional.

Validação dirigida:14 testes/23 asserções de chave/arredondamento e3/10 de esquema. PHPStan sem erros após as classes. Faltam o projetor conectado à operação qualificada, conciliação do valor mensal com os detalhes transacionais, regras de negócio, calendário/versionamento ativado e demais eventos mensais. Não se deve tratar a base mensal800 como base de uma transação isolada sem prova de alocação.

## Documentação adicional preservada

Foram preservados os anexos de tabelas/regras, histórico1.2.0, manual de desenvolvedor1.0.2, atos conjuntos3/4/5, ato técnico3 e dependência W3C. URLs e hashes constam de `dere-supplement-observation-20261002.json`. O nome do arquivo do ato técnico termina em02102026, mas a leitura visual do documento confirma **2 de setembro de2026**; não usar o nome do arquivo como data normativa. Ele aprova1.2.0 e ratifica a nota2026.001; efeitos exigem publicação.

O ato4 descreve, separadamente, início dos eventos de tabela, dos mensais e dos demais; a primeira entrega mensal contém outubro2026. A ativação temporal deverá vincular o ato integral e suas exceções, inclusive Simples, sem inferir disponibilidade operacional apenas desse calendário. A nota orientativa ainda precisa ser preservada pelo destino real do link: o endpoint inicial retornou HTML e foi recusado como PDF.

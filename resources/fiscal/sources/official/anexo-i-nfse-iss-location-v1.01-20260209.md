# Anexo I NFS-e — Regras para o `ISSLocationEngine`

**Arquivo-fonte:** `anexo_i-sefin_adn-dps_nfse-snnfse-v1-01-20260209 (1).xlsx`  
**Aba convertida:** `MUN.INCID_INFO.SERV.`  
**Versão indicada no nome do arquivo:** `v1.01-20260209`

> Este documento foi gerado diretamente da planilha anexada, preservando os códigos e a lógica nela publicada. A finalidade é tornar legível e implementável a determinação da Localidade de Incidência (LI) do ISSQN.

## Conclusão para o Motor Fiscal

**Sim: esta aba resolve a maior parte do problema de classificação territorial do `ISSLocationEngine`.** Ela fornece, por `cTribNac`, qual referência o Sistema Nacional utiliza como regra de localidade de incidência:

- `EP` — Estabelecimento/Domicílio do Prestador.
- `LP` — Local da Prestação.
- `ET` — Estabelecimento/Domicílio do Tomador.
- Em importação, a própria planilha indica uma regra prevalente usando o Estabelecimento/Domicílio do Emitente (`EDEmit`) quando o emitente é tomador ou intermediário.

Ela **não elimina a necessidade de termos os fatos da operação**. O catálogo informa *qual fato usar*; o sistema ainda precisa conhecer o município do prestador, do tomador ou o `cLocPrestacao` da operação.

## Resumo quantitativo da aba

- Total de códigos listados: **337**.
- Regra `EP`: **277** códigos.
- Regra `LP`: **58** códigos, incluindo o tratamento especial de `20.01.01`.
- Regra `ET`: **1** código.
- Sem localidade de incidência na tabela: **1** código (`99.01.01`).
- Regra de importação marcada com `X`: **336** códigos.
- Grupo `obra` obrigatório: **13** códigos.
- Grupo `atvEvento` obrigatório: **19** códigos.

## Notas oficiais da própria planilha

**Regra geral / águas marítimas:** LI para os serviços indicados seguem as regras geral de EP e as exceções para LP e ET.  * Com exceção do subitem 200101 da lista nacional de serviços,  todos os demais serviços podem ser prestados em "Águas Marítimas"  (cLocPrestacao = 0000000). Neste caso o LI será EDP.   ** Caso o emitente informe o subitem 200101,  o LP não poderá ser "Águas Marítimas" e o LI será o município informado em LP.   (Art 3º, § 3º da LC 116/03).

**Importação:** IMPORTAÇÃO    Emitente  = T \| I  País Exterior P  e/ou País Exterior LP  A incidência do ISSQN em casos de Importação de serviço prevalece sobre as regras do Art 3º da LC 116/03

## Interpretação operacional recomendada

```text
1. Determinar primeiro tribISSQN.
   - Se imunidade, exportação ou não incidência: não produzir cLocIncid.

2. Detectar importação de serviço.
   - Quando o cenário de importação da planilha for atendido, a regra de importação prevalece.
   - LI = município do Estabelecimento/Domicílio do Emitente (tomador ou intermediário).

3. Para operação tributável nacional, consultar cTribNac no catálogo abaixo.

4. Se regra = EP:
   - usar município do estabelecimento/domicílio do prestador.

5. Se regra = LP:
   - usar cLocPrestacao.
   - cLocPrestacao pode ser município IBGE, trecho de concessão de rodovia ou 0000000 (Águas Marítimas),
     observadas as exceções da própria documentação.

6. Se regra = ET:
   - usar município do estabelecimento/domicílio do tomador.

7. Validar o código de localidade contra os catálogos oficiais aceitos pelo Sistema Nacional.
```

## Campos mínimos que o `ISSLocationEngine` precisa

| Regra | Fato necessário | Origem sugerida |
|---|---|---|
| `EP` | Município do prestador | `CompanyFiscalProfile.establishment.municipality_ibge` |
| `LP` | Local da prestação | `OperationContext.cLocPrestacao` |
| `ET` | Município do tomador | `CustomerFiscalProfile.establishment.municipality_ibge` |
| Importação / `EDEmit` | Município do emitente tomador/intermediário | contexto da DPS + perfil do emitente |

Os grupos `obra` e `atvEvento` indicados na planilha são **obrigações adicionais de detalhamento da DPS**. Eles não devem ser tratados como sinônimos da regra territorial: há códigos com grupo `obra` cuja regra continua sendo `EP`.

## Casos especiais importantes

### `99.01.01`

A própria tabela marca `99.01.01 — Serviços sem a incidência de ISSQN e ICMS` sem `EP`, `LP` ou `ET`. Portanto, não deve ser usado como uma operação tributável comum com `cLocIncid`.

### Águas Marítimas

A planilha informa que, com exceção de `20.01.01`, os demais serviços podem usar `cLocPrestacao = 0000000` para Águas Marítimas; nesse cenário a LI volta ao estabelecimento/domicílio do prestador conforme a nota da fonte. Para `20.01.01`, `0000000` não é permitido e a LI é o LP informado.

### `22.01.01`

O leiaute informa que `cLocPrestacao` também pode representar trecho de concessão de exploração de rodovias. Portanto, o tipo do identificador de localidade não deve ser modelado como 'código IBGE obrigatório' em todos os casos.

## Divergência interna encontrada no próprio Excel

A aba `MUN.INCID_INFO.SERV.` e a regra textual da aba `RN DPS_NFS-e` não estão 100% alinhadas.

- Códigos marcados como `LP` em `MUN.INCID_INFO.SERV.` mas ausentes da lista textual de LP da RN: **14.14.01, 14.14.02, 14.14.03, 14.14.04, 20.01.01, 20.02.01, 20.03.01**.
- Código incluído na lista textual de LP da RN, mas marcado como `EP` em `MUN.INCID_INFO.SERV.`: **11.03.01**.

Por isso, a implementação deve usar a aba `MUN.INCID_INFO.SERV.` como **catálogo importável**, mas deve existir um teste automático de consistência entre abas/versões e um mecanismo de validação contra o retorno oficial da NFS-e. Não é seguro transformar listas textuais das RNs em `if/else` hardcoded.

## Modelo recomendado de registro

```text
ISSLocationRule
{
  ctribnac_code: "070202",
  strategy: "LP",
  import_override: "EDEmit",
  required_detail_group: "obra",
  source_sheet: "MUN.INCID_INFO.SERV.",
  source_version: "v1.01-20260209"
}
```

## Tabela completa — `MUN.INCID_INFO.SERV.`

| cTribNac | Descrição | LI | EP | LP | ET | Importação / EDEmit | Grupo obrigatório | infoComplem |
|---|---|---:|---:|---:|---:|---:|---|---:|
| `01.01.01` | Análise e desenvolvimento de sistemas. | **EP** | X | - | - | X | - | X |
| `01.02.01` | Programação. | **EP** | X | - | - | X | - | X |
| `01.03.01` | Processamento de dados, textos, imagens, vídeos, páginas eletrônicas, aplicativos e sistemas de informação, entre outros formatos, e congêneres. | **EP** | X | - | - | X | - | X |
| `01.03.02` | Armazenamento ou hospedagem de dados, textos, imagens, vídeos, páginas eletrônicas, aplicativos e sistemas de informação, entre outros formatos, e congêneres. | **EP** | X | - | - | X | - | X |
| `01.04.01` | Elaboração de programas de computadores, inclusive de jogos eletrônicos, independentemente da arquitetura construtiva da máquina em que o programa será executado, incluindo tablets, smartphones e congêneres. | **EP** | X | - | - | X | - | X |
| `01.05.01` | Licenciamento ou cessão de direito de uso de programas de computação. | **EP** | X | - | - | X | - | X |
| `01.06.01` | Assessoria e consultoria em informática. | **EP** | X | - | - | X | - | X |
| `01.07.01` | Suporte técnico em informática, inclusive instalação, configuração e manutenção de programas de computação e bancos de dados. | **EP** | X | - | - | X | - | X |
| `01.08.01` | Planejamento, confecção, manutenção e atualização de páginas eletrônicas. | **EP** | X | - | - | X | - | X |
| `01.09.01` | Disponibilização, sem cessão definitiva, de conteúdos de áudio por meio da internet (exceto a distribuição de conteúdos pelas prestadoras de Serviço de Acesso Condicionado, de que trata a Lei nº 12.485, de 12 de setembro de 2011, sujeita ao ICMS). | **EP** | X | - | - | X | - | X |
| `01.09.02` | Disponibilização, sem cessão definitiva, de conteúdos de vídeo, imagem e texto por meio da internet, respeitada a imunidade de livros, jornais e periódicos (exceto a distribuição de conteúdos pelas prestadoras de Serviço de Acesso Condicionado, de que trata a Lei nº 12.485, de 12 de setembro de 2011, sujeita ao ICMS). | **EP** | X | - | - | X | - | X |
| `02.01.01` | Serviços de pesquisas e desenvolvimento de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `03.02.01` | Cessão de direito de uso de marcas e de sinais de propaganda. | **EP** | X | - | - | X | - | X |
| `03.03.01` | Exploração de salões de festas, centro de convenções, stands e congêneres, para realização de eventos ou negócios de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `03.03.02` | Exploração de escritórios virtuais e congêneres, para realização de eventos ou negócios de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `03.03.03` | Exploração de quadras esportivas, estádios, ginásios, canchas e congêneres, para realização de eventos ou negócios de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `03.03.04` | Exploração de auditórios, casas de espetáculos e congêneres, para realização de eventos ou negócios de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `03.03.05` | Exploração de parques de diversões e congêneres, para realização de eventos ou negócios de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `03.04.01` | Locação, sublocação, arrendamento, direito de passagem ou permissão de uso, compartilhado ou não, de ferrovia. | **LP** | - | X | - | X | - | X |
| `03.04.02` | Locação, sublocação, arrendamento, direito de passagem ou permissão de uso, compartilhado ou não, de rodovia. | **LP** | - | X | - | X | - | X |
| `03.04.03` | Locação, sublocação, arrendamento, direito de passagem ou permissão de uso, compartilhado ou não, de postes, cabos, dutos e condutos de qualquer natureza. | **LP** | - | X | - | X | - | X |
| `03.05.01` | Cessão de andaimes, palcos, coberturas e outras estruturas de uso temporário. | **LP** | - | X | - | X | - | X |
| `04.01.01` | Medicina. | **EP** | X | - | - | X | - | X |
| `04.01.02` | Biomedicina. | **EP** | X | - | - | X | - | X |
| `04.02.01` | Análises clínicas e congêneres. | **EP** | X | - | - | X | - | X |
| `04.02.02` | Patologia e congêneres. | **EP** | X | - | - | X | - | X |
| `04.02.03` | Eletricidade médica (eletroestimulação de nervos e musculos, cardioversão, etc) e congêneres. | **EP** | X | - | - | X | - | X |
| `04.02.04` | Radioterapia, quimioterapia e congêneres. | **EP** | X | - | - | X | - | X |
| `04.02.05` | Ultra-sonografia, ressonância magnética, radiologia, tomografia e congêneres. | **EP** | X | - | - | X | - | X |
| `04.03.01` | Hospitais e congêneres. | **EP** | X | - | - | X | - | X |
| `04.03.02` | Laboratórios e congêneres. | **EP** | X | - | - | X | - | X |
| `04.03.03` | Clínicas, sanatórios, manicômios, casas de saúde, prontos-socorros, ambulatórios e congêneres. | **EP** | X | - | - | X | - | X |
| `04.04.01` | Instrumentação cirúrgica. | **EP** | X | - | - | X | - | X |
| `04.05.01` | Acupuntura. | **EP** | X | - | - | X | - | X |
| `04.06.01` | Enfermagem, inclusive serviços auxiliares. | **EP** | X | - | - | X | - | X |
| `04.07.01` | Serviços farmacêuticos. | **EP** | X | - | - | X | - | X |
| `04.08.01` | Terapia ocupacional. | **EP** | X | - | - | X | - | X |
| `04.08.02` | Fisioterapia. | **EP** | X | - | - | X | - | X |
| `04.08.03` | Fonoaudiologia. | **EP** | X | - | - | X | - | X |
| `04.09.01` | Terapias de qualquer espécie destinadas ao tratamento físico, orgânico e mental. | **EP** | X | - | - | X | - | X |
| `04.10.01` | Nutrição. | **EP** | X | - | - | X | - | X |
| `04.11.01` | Obstetrícia. | **EP** | X | - | - | X | - | X |
| `04.12.01` | Odontologia. | **EP** | X | - | - | X | - | X |
| `04.13.01` | Ortóptica. | **EP** | X | - | - | X | - | X |
| `04.14.01` | Próteses sob encomenda. | **EP** | X | - | - | X | - | X |
| `04.15.01` | Psicanálise. | **EP** | X | - | - | X | - | X |
| `04.16.01` | Psicologia. | **EP** | X | - | - | X | - | X |
| `04.17.01` | Casas de repouso e congêneres. | **EP** | X | - | - | X | - | X |
| `04.17.02` | Casas de recuperação e congêneres. | **EP** | X | - | - | X | - | X |
| `04.17.03` | Creches e congêneres. | **EP** | X | - | - | X | - | X |
| `04.17.04` | Asilos e congêneres. | **EP** | X | - | - | X | - | X |
| `04.18.01` | Inseminação artificial, fertilização in vitro e congêneres. | **EP** | X | - | - | X | - | X |
| `04.19.01` | Bancos de sangue, leite, pele, olhos, óvulos, sêmen e congêneres. | **EP** | X | - | - | X | - | X |
| `04.20.01` | Coleta de sangue, leite, tecidos, sêmen, órgãos e materiais biológicos de qualquer espécie. | **EP** | X | - | - | X | - | X |
| `04.21.01` | Unidade de atendimento, assistência ou tratamento móvel e congêneres. | **EP** | X | - | - | X | - | X |
| `04.22.01` | Planos de medicina de grupo ou individual e convênios para prestação de assistência médica, hospitalar, odontológica e congêneres. | **EP** | X | - | - | X | - | X |
| `04.23.01` | Outros planos de saúde que se cumpram através de serviços de terceiros contratados, credenciados, cooperados ou apenas pagos pelo operador do plano mediante indicação do beneficiário. | **EP** | X | - | - | X | - | X |
| `05.01.01` | Medicina veterinária | **EP** | X | - | - | X | - | X |
| `05.01.02` | Zootecnia. | **EP** | X | - | - | X | - | X |
| `05.02.01` | Hospitais e congêneres, na área veterinária. | **EP** | X | - | - | X | - | X |
| `05.02.02` | Clínicas, ambulatórios, prontos-socorros e congêneres, na área veterinária. | **EP** | X | - | - | X | - | X |
| `05.03.01` | Laboratórios de análise na área veterinária. | **EP** | X | - | - | X | - | X |
| `05.04.01` | Inseminação artificial, fertilização in vitro e congêneres. | **EP** | X | - | - | X | - | X |
| `05.05.01` | Bancos de sangue e de órgãos e congêneres. | **EP** | X | - | - | X | - | X |
| `05.06.01` | Coleta de sangue, leite, tecidos, sêmen, órgãos e materiais biológicos de qualquer espécie. | **EP** | X | - | - | X | - | X |
| `05.07.01` | Unidade de atendimento, assistência ou tratamento móvel e congêneres. | **EP** | X | - | - | X | - | X |
| `05.08.01` | Guarda, tratamento, amestramento, embelezamento, alojamento e congêneres. | **EP** | X | - | - | X | - | X |
| `05.09.01` | Planos de atendimento e assistência médico-veterinária. | **EP** | X | - | - | X | - | X |
| `06.01.01` | Barbearia, cabeleireiros, manicuros, pedicuros e congêneres. | **EP** | X | - | - | X | - | X |
| `06.02.01` | Esteticistas, tratamento de pele, depilação e congêneres. | **EP** | X | - | - | X | - | X |
| `06.03.01` | Banhos, duchas, sauna, massagens e congêneres. | **EP** | X | - | - | X | - | X |
| `06.04.01` | Ginástica, dança, esportes, natação, artes marciais e demais atividades físicas. | **EP** | X | - | - | X | - | X |
| `06.05.01` | Centros de emagrecimento, spa e congêneres. | **EP** | X | - | - | X | - | X |
| `06.06.01` | Aplicação de tatuagens, piercings e congêneres. | **EP** | X | - | - | X | - | X |
| `07.01.01` | Engenharia e congêneres. | **EP** | X | - | - | X | - | X |
| `07.01.02` | Agronomia e congêneres. | **EP** | X | - | - | X | - | X |
| `07.01.03` | Agrimensura e congêneres. | **EP** | X | - | - | X | - | X |
| `07.01.04` | Arquitetura, urbanismo e congêneres. | **EP** | X | - | - | X | - | X |
| `07.01.05` | Geologia e congêneres. | **EP** | X | - | - | X | - | X |
| `07.01.06` | Paisagismo e congêneres. | **EP** | X | - | - | X | - | X |
| `07.02.01` | Execução, por administração, de obras de construção civil, hidráulica ou elétrica e de outras obras semelhantes, inclusive sondagem, perfuração de poços, escavação, drenagem e irrigação, terraplanagem, pavimentação, concretagem e a instalação e montagem de produtos, peças e equipamentos (exceto o fornecimento de mercadorias produzidas pelo prestador de serviços fora do local da prestação dos serviços, que fica sujeito ao ICMS). | **LP** | - | X | - | X | obra | X |
| `07.02.02` | Execução, por empreitada ou subempreitada, de obras de construção civil, hidráulica ou elétrica e de outras obras semelhantes, inclusive sondagem, perfuração de poços, escavação, drenagem e irrigação, terraplanagem, pavimentação, concretagem e a instalação e montagem de produtos, peças e equipamentos (exceto o fornecimento de mercadorias produzidas pelo prestador de serviços fora do local da prestação dos serviços, que fica sujeito ao ICMS). | **LP** | - | X | - | X | obra | X |
| `07.03.01` | Elaboração de planos diretores, estudos de viabilidade, estudos organizacionais e outros, relacionados com obras e serviços de engenharia. | **EP** | X | - | - | X | - | X |
| `07.03.02` | Elaboração de anteprojetos, projetos básicos e projetos executivos para trabalhos de engenharia. | **EP** | X | - | - | X | - | X |
| `07.04.01` | Demolição. | **LP** | - | X | - | X | obra | X |
| `07.05.01` | Reparação, conservação e reforma de edifícios e congêneres (exceto o fornecimento de mercadorias produzidas pelo prestador dos serviços, fora do local da prestação dos serviços, que fica sujeito ao ICMS). | **LP** | - | X | - | X | obra | X |
| `07.05.02` | Reparação, conservação e reforma de estradas, pontes, portos e congêneres (exceto o fornecimento de mercadorias produzidas pelo prestador dos serviços, fora do local da prestação dos serviços, que fica sujeito ao ICMS). | **LP** | - | X | - | X | obra | X |
| `07.06.01` | Colocação e instalação de tapetes, carpetes, cortinas e congêneres, com material fornecido pelo tomador do serviço. | **EP** | X | - | - | X | obra | X |
| `07.06.02` | Colocação e instalação de assoalhos, revestimentos de parede, vidros, divisórias, placas de gesso e congêneres, com material fornecido pelo tomador do serviço. | **EP** | X | - | - | X | obra | X |
| `07.07.01` | Recuperação, raspagem, polimento e lustração de pisos e congêneres. | **EP** | X | - | - | X | obra | X |
| `07.08.01` | Calafetação. | **EP** | X | - | - | X | obra | X |
| `07.09.01` | Varrição, coleta e remoção de lixo, rejeitos e outros resíduos quaisquer. | **LP** | - | X | - | X | - | X |
| `07.09.02` | Incineração, tratamento, reciclagem, separação e destinação final de lixo, rejeitos e outros resíduos quaisquer. | **LP** | - | X | - | X | - | X |
| `07.10.01` | Limpeza, manutenção e conservação de vias e logradouros públicos, parques, jardins e congêneres. | **LP** | - | X | - | X | - | X |
| `07.10.02` | Limpeza, manutenção e conservação de imóveis, chaminés, piscinas e congêneres. | **LP** | - | X | - | X | - | X |
| `07.11.01` | Decoração. | **LP** | - | X | - | X | - | X |
| `07.11.02` | Jardinagem, inclusive corte e poda de árvores. | **LP** | - | X | - | X | - | X |
| `07.12.01` | Controle e tratamento de efluentes de qualquer natureza e de agentes físicos, químicos e biológicos. | **LP** | - | X | - | X | - | X |
| `07.13.01` | Dedetização, desinfecção, desinsetização, imunização, higienização, desratização, pulverização e congêneres. | **EP** | X | - | - | X | - | X |
| `07.16.01` | Florestamento, reflorestamento, semeadura, adubação, reparação de solo, plantio, silagem, colheita, corte e descascamento de árvores, silvicultura, exploração florestal e dos serviços congêneres indissociáveis da formação, manutenção e colheita de florestas, para quaisquer fins e por quaisquer meios. | **LP** | - | X | - | X | - | X |
| `07.17.01` | Escoramento, contenção de encostas e serviços congêneres. | **LP** | - | X | - | X | obra | X |
| `07.18.01` | Limpeza e dragagem de rios, portos, canais, baías, lagos, lagoas, represas, açudes e congêneres. | **LP** | - | X | - | X | - | X |
| `07.19.01` | Acompanhamento e fiscalização da execução de obras de engenharia, arquitetura e urbanismo. | **LP** | - | X | - | X | obra | X |
| `07.20.01` | Aerofotogrametria (inclusive interpretação), cartografia, mapeamento e congêneres. | **EP** | X | - | - | X | - | X |
| `07.20.02` | Levantamentos batimétricos, geográficos, geodésicos, geológicos, geofísicos e congêneres. | **EP** | X | - | - | X | - | X |
| `07.20.03` | Levantamentos topográficos e congêneres. | **EP** | X | - | - | X | - | X |
| `07.21.01` | Pesquisa, perfuração, cimentação, mergulho, perfilagem, concretação, testemunhagem, pescaria, estimulação e outros serviços relacionados com a exploração e explotação de petróleo, gás natural e de outros recursos minerais. | **EP** | X | - | - | X | - | X |
| `07.22.01` | Nucleação e bombardeamento de nuvens e congêneres. | **EP** | X | - | - | X | - | X |
| `08.01.01` | Ensino regular pré-escolar, fundamental e médio. | **EP** | X | - | - | X | - | X |
| `08.01.02` | Ensino regular superior. | **EP** | X | - | - | X | - | X |
| `08.02.01` | Instrução, treinamento, orientação pedagógica e educacional, avaliação de conhecimentos de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `09.01.01` | Hospedagem em hotéis, hotelaria marítima e congêneres (o valor da alimentação e gorjeta, quando incluído no preço da diária, fica sujeito ao Imposto Sobre Serviços). | **EP** | X | - | - | X | - | X |
| `09.01.02` | Hospedagem em pensões, albergues, pousadas, hospedarias, ocupação por temporada com fornecimento de serviços e congêneres (o valor da alimentação e gorjeta, quando incluído no preço da diária, fica sujeito ao Imposto Sobre Serviços). | **EP** | X | - | - | X | - | X |
| `09.01.03` | Hospedagem em motéis e congêneres (o valor da alimentação e gorjeta, quando incluído no preço da diária, fica sujeito ao Imposto Sobre Serviços). | **EP** | X | - | - | X | - | X |
| `09.01.04` | Hospedagem em apart-service condominiais, flat, apart-hotéis, hotéis residência, residence-service, suite service e congêneres (o valor da alimentação e gorjeta, quando incluído no preço da diária, fica sujeito ao Imposto Sobre Serviços). | **EP** | X | - | - | X | - | X |
| `09.02.01` | Agenciamento e intermediação de programas de turismo, passeios, viagens, excursões, hospedagens e congêneres. | **EP** | X | - | - | X | - | X |
| `09.02.02` | Organização, promoção e execução de programas de turismo, passeios, viagens, excursões, hospedagens e congêneres. | **EP** | X | - | - | X | - | X |
| `09.03.01` | Guias de turismo. | **EP** | X | - | - | X | - | X |
| `10.01.01` | Agenciamento, corretagem ou intermediação de câmbio. | **EP** | X | - | - | X | - | X |
| `10.01.02` | Agenciamento, corretagem ou intermediação de seguros. | **EP** | X | - | - | X | - | X |
| `10.01.03` | Agenciamento, corretagem ou intermediação de cartões de crédito. | **EP** | X | - | - | X | - | X |
| `10.01.04` | Agenciamento, corretagem ou intermediação de planos de saúde. | **EP** | X | - | - | X | - | X |
| `10.01.05` | Agenciamento, corretagem ou intermediação de planos de previdência privada. | **EP** | X | - | - | X | - | X |
| `10.02.01` | Agenciamento, corretagem ou intermediação de títulos em geral e valores mobiliários. | **EP** | X | - | - | X | - | X |
| `10.02.02` | Agenciamento, corretagem ou intermediação de contratos quaisquer. | **EP** | X | - | - | X | - | X |
| `10.03.01` | Agenciamento, corretagem ou intermediação de direitos de propriedade industrial, artística ou literária. | **EP** | X | - | - | X | - | X |
| `10.04.01` | Agenciamento, corretagem ou intermediação de contratos de arrendamento mercantil (leasing). | **EP** | X | - | - | X | - | X |
| `10.04.02` | Agenciamento, corretagem ou intermediação de contratos de franquia (franchising). | **EP** | X | - | - | X | - | X |
| `10.04.03` | Agenciamento, corretagem ou intermediação de faturização (factoring). | **EP** | X | - | - | X | - | X |
| `10.05.01` | Agenciamento, corretagem ou intermediação de bens móveis ou imóveis, não abrangidos em outros itens ou subitens, por quaisquer meios. | **EP** | X | - | - | X | - | X |
| `10.05.02` | Agenciamento, corretagem ou intermediação de bens móveis ou imóveis realizados no âmbito de Bolsas de Mercadorias e Futuros, por quaisquer meios. | **EP** | X | - | - | X | - | X |
| `10.06.01` | Agenciamento marítimo. | **EP** | X | - | - | X | - | X |
| `10.07.01` | Agenciamento de notícias. | **EP** | X | - | - | X | - | X |
| `10.08.01` | Agenciamento de publicidade e propaganda, inclusive o agenciamento de veiculação por quaisquer meios. | **EP** | X | - | - | X | - | X |
| `10.09.01` | Representação de qualquer natureza, inclusive comercial. | **EP** | X | - | - | X | - | X |
| `10.10.01` | Distribuição de bens de terceiros. | **EP** | X | - | - | X | - | X |
| `11.01.01` | Guarda e estacionamento de veículos terrestres automotores. | **LP** | - | X | - | X | - | X |
| `11.01.02` | Guarda e estacionamento de aeronaves e de embarcações. | **LP** | - | X | - | X | - | X |
| `11.02.01` | Vigilância, segurança ou monitoramento de bens, pessoas e semoventes. | **LP** | - | X | - | X | - | X |
| `11.03.01` | Escolta, inclusive de veículos e cargas. | **EP** | X | - | - | X | - | X |
| `11.04.01` | Armazenamento, depósito, guarda de bens de qualquer espécie. | **LP** | - | X | - | X | - | X |
| `11.04.02` | Carga, descarga, arrumação de bens de qualquer espécie. | **LP** | - | X | - | X | - | X |
| `11.05.01` | Serviços relacionados ao monitoramento e rastreamento a distância, em qualquer via ou local, de veículos, cargas, pessoas e semoventes em circulação ou movimento, realizados por meio de telefonia móvel, transmissão de satélites, rádio ou qualquer outro meio, inclusive pelas empresas de Tecnologia da Informação Veicular, independentemente de o prestador de serviços ser proprietário ou não da infraestrutura de telecomunicações que utiliza. | **EP** | X | - | - | X | - | X |
| `12.01.01` | Espetáculos teatrais. | **LP** | - | X | - | X | atvEvento | X |
| `12.02.01` | Exibições cinematográficas. | **LP** | - | X | - | X | atvEvento | X |
| `12.03.01` | Espetáculos circenses. | **LP** | - | X | - | X | atvEvento | X |
| `12.04.01` | Programas de auditório. | **LP** | - | X | - | X | atvEvento | X |
| `12.05.01` | Parques de diversões, centros de lazer e congêneres. | **LP** | - | X | - | X | atvEvento | X |
| `12.06.01` | Boates, taxi-dancing e congêneres. | **LP** | - | X | - | X | atvEvento | X |
| `12.07.01` | Shows, ballet, danças, desfiles, bailes, óperas, concertos, recitais, festivais e congêneres. | **LP** | - | X | - | X | atvEvento | X |
| `12.08.01` | Feiras, exposições, congressos e congêneres. | **LP** | - | X | - | X | atvEvento | X |
| `12.09.01` | Bilhares. | **LP** | - | X | - | X | atvEvento | X |
| `12.09.02` | Boliches. | **LP** | - | X | - | X | atvEvento | X |
| `12.09.03` | Diversões eletrônicas ou não. | **LP** | - | X | - | X | atvEvento | X |
| `12.10.01` | Corridas e competições de animais. | **LP** | - | X | - | X | atvEvento | X |
| `12.11.01` | Competições esportivas ou de destreza física ou intelectual, com ou sem a participação do espectador. | **LP** | - | X | - | X | atvEvento | X |
| `12.12.01` | Execução de música. | **LP** | - | X | - | X | atvEvento | X |
| `12.13.01` | Produção, mediante ou sem encomenda prévia, de eventos, espetáculos, entrevistas, shows, ballet, danças, desfiles, bailes, teatros, óperas, concertos, recitais, festivais e congêneres. | **EP** | X | - | - | X | atvEvento | X |
| `12.14.01` | Fornecimento de música para ambientes fechados ou não, mediante transmissão por qualquer processo. | **LP** | - | X | - | X | atvEvento | X |
| `12.15.01` | Desfiles de blocos carnavalescos ou folclóricos, trios elétricos e congêneres. | **LP** | - | X | - | X | atvEvento | X |
| `12.16.01` | Exibição de filmes, entrevistas, musicais, espetáculos, shows, concertos, desfiles, óperas, competições esportivas, de destreza intelectual ou congêneres. | **LP** | - | X | - | X | atvEvento | X |
| `12.17.01` | Recreação e animação, inclusive em festas e eventos de qualquer natureza. | **LP** | - | X | - | X | atvEvento | X |
| `13.02.01` | Fonografia ou gravação de sons, inclusive trucagem, dublagem, mixagem e congêneres. | **EP** | X | - | - | X | - | X |
| `13.03.01` | Fotografia e cinematografia, inclusive revelação, ampliação, cópia, reprodução, trucagem e congêneres. | **EP** | X | - | - | X | - | X |
| `13.04.01` | Reprografia, microfilmagem e digitalização. | **EP** | X | - | - | X | - | X |
| `13.05.01` | Composição gráfica, inclusive confecção de impressos gráficos, fotocomposição, clicheria, zincografia, litografia e fotolitografia, exceto se destinados a posterior operação de comercialização ou industrialização, ainda que incorporados, de qualquer forma, a outra mercadoria que deva ser objeto de posterior circulação, tais como bulas, rótulos, etiquetas, caixas, cartuchos, embalagens e manuais técnicos e de instrução, quando ficarão sujeitos ao ICMS. | **EP** | X | - | - | X | - | X |
| `14.01.01` | Lubrificação, limpeza, lustração, revisão, carga e recarga, conserto, restauração, blindagem, manutenção e conservação de máquinas, veículos, aparelhos, equipamentos, motores, elevadores ou de qualquer objeto (exceto peças e partes empregadas, que ficam sujeitas ao ICMS). | **EP** | X | - | - | X | - | X |
| `14.02.01` | Assistência técnica. | **EP** | X | - | - | X | - | X |
| `14.03.01` | Recondicionamento de motores (exceto peças e partes empregadas, que ficam sujeitas ao ICMS). | **EP** | X | - | - | X | - | X |
| `14.04.01` | Recauchutagem ou regeneração de pneus. | **EP** | X | - | - | X | - | X |
| `14.05.01` | Restauração, recondicionamento, acondicionamento, pintura, beneficiamento, lavagem, secagem, tingimento, galvanoplastia, anodização, corte, recorte, plastificação, costura, acabamento, polimento e congêneres de objetos quaisquer. | **EP** | X | - | - | X | - | X |
| `14.06.01` | Instalação e montagem de aparelhos, máquinas e equipamentos, inclusive montagem industrial, prestados ao usuário final, exclusivamente com material por ele fornecido. | **EP** | X | - | - | X | - | X |
| `14.07.01` | Colocação de molduras e congêneres. | **EP** | X | - | - | X | - | X |
| `14.08.01` | Encadernação, gravação e douração de livros, revistas e congêneres. | **EP** | X | - | - | X | - | X |
| `14.09.01` | Alfaiataria e costura, quando o material for fornecido pelo usuário final, exceto aviamento. | **EP** | X | - | - | X | - | X |
| `14.10.01` | Tinturaria e lavanderia. | **EP** | X | - | - | X | - | X |
| `14.11.01` | Tapeçaria e reforma de estofamentos em geral. | **EP** | X | - | - | X | - | X |
| `14.12.01` | Funilaria e lanternagem. | **EP** | X | - | - | X | - | X |
| `14.13.01` | Carpintaria. | **EP** | X | - | - | X | - | X |
| `14.13.02` | Serralheria. | **EP** | X | - | - | X | - | X |
| `14.14.01` | Guincho intramunicipal. | **LP** | - | X | - | X | - | X |
| `14.14.02` | Guindaste e içamento. | **LP** | - | X | - | X | - | X |
| `14.14.03` | Guincho intramunicipal em construção civil. | **LP** | - | X | - | X | obra | X |
| `14.14.04` | Guindaste e içamento em construção civil. | **LP** | - | X | - | X | obra | X |
| `15.01.01` | Administração de fundos quaisquer e congêneres. | **EP** | X | - | - | X | - | X |
| `15.01.02` | Administração de consórcio e congêneres. | **EP** | X | - | - | X | - | X |
| `15.01.03` | Administração de cartão de crédito ou débito e congêneres. | **EP** | X | - | - | X | - | X |
| `15.01.04` | Administração de carteira de clientes e congêneres. | **EP** | X | - | - | X | - | X |
| `15.01.05` | Administração de cheques pré-datados e congêneres. | **EP** | X | - | - | X | - | X |
| `15.02.01` | Abertura de conta-corrente no País, bem como a manutenção da referida conta ativa e inativa. | **EP** | X | - | - | X | - | X |
| `15.02.02` | Abertura de conta-corrente no exterior, bem como a manutenção da referida conta ativa e inativa. | **EP** | X | - | - | X | - | X |
| `15.02.03` | Abertura de conta de investimentos e aplicação no País, bem como a manutenção da referida conta ativa e inativa. | **EP** | X | - | - | X | - | X |
| `15.02.04` | Abertura de conta de investimentos e aplicação no exterior, bem como a manutenção da referida conta ativa e inativa. | **EP** | X | - | - | X | - | X |
| `15.02.05` | Abertura de caderneta de poupança no País, bem como a manutenção da referida conta ativa e inativa. | **EP** | X | - | - | X | - | X |
| `15.02.06` | Abertura de caderneta de poupança no exterior, bem como a manutenção da referida conta ativa e inativa. | **EP** | X | - | - | X | - | X |
| `15.02.07` | Abertura de contas em geral no País, não abrangida em outro subitem, bem como a manutenção das referidas contas ativas e inativas. | **EP** | X | - | - | X | - | X |
| `15.02.08` | Abertura de contas em geral no exterior, não abrangida em outro subitem, bem como a manutenção das referidas contas ativas e inativas. | **EP** | X | - | - | X | - | X |
| `15.03.01` | Locação de cofres particulares. | **EP** | X | - | - | X | - | X |
| `15.03.02` | Manutenção de cofres particulares. | **EP** | X | - | - | X | - | X |
| `15.03.03` | Locação de terminais eletrônicos. | **EP** | X | - | - | X | - | X |
| `15.03.04` | Manutenção de terminais eletrônicos. | **EP** | X | - | - | X | - | X |
| `15.03.05` | Locação de terminais de atendimento. | **EP** | X | - | - | X | - | X |
| `15.03.06` | Manutenção de terminais de atendimento. | **EP** | X | - | - | X | - | X |
| `15.03.07` | Locação de bens e equipamentos em geral. | **EP** | X | - | - | X | - | X |
| `15.03.08` | Manutenção de bens e equipamentos em geral. | **EP** | X | - | - | X | - | X |
| `15.04.01` | Fornecimento ou emissão de atestados em geral, inclusive atestado de idoneidade, atestado de capacidade financeira e congêneres. | **EP** | X | - | - | X | - | X |
| `15.05.01` | Cadastro, elaboração de ficha cadastral, renovação cadastral e congêneres. | **EP** | X | - | - | X | - | X |
| `15.05.02` | Inclusão no Cadastro de Emitentes de Cheques sem Fundos - CCF. | **EP** | X | - | - | X | - | X |
| `15.05.03` | Exclusão no Cadastro de Emitentes de Cheques sem Fundos - CCF. | **EP** | X | - | - | X | - | X |
| `15.05.04` | Inclusão em quaisquer outros bancos cadastrais. | **EP** | X | - | - | X | - | X |
| `15.05.05` | Exclusão em quaisquer outros bancos cadastrais. | **EP** | X | - | - | X | - | X |
| `15.06.01` | Emissão, reemissão e fornecimento de avisos, comprovantes e documentos em geral | **EP** | X | - | - | X | - | X |
| `15.06.02` | Abono de firmas. | **EP** | X | - | - | X | - | X |
| `15.06.03` | Coleta e entrega de documentos, bens e valores. | **EP** | X | - | - | X | - | X |
| `15.06.04` | Comunicação com outra agência ou com a administração central. | **EP** | X | - | - | X | - | X |
| `15.06.05` | Licenciamento eletrônico de veículos. | **EP** | X | - | - | X | - | X |
| `15.06.06` | Transferência de veículos. | **EP** | X | - | - | X | - | X |
| `15.06.07` | Agenciamento fiduciário ou depositário. | **EP** | X | - | - | X | - | X |
| `15.06.08` | Devolução de bens em custódia. | **EP** | X | - | - | X | - | X |
| `15.07.01` | Acesso, movimentação, atendimento e consulta a contas em geral, por qualquer meio ou processo, inclusive por telefone, fac-símile, internet e telex. | **EP** | X | - | - | X | - | X |
| `15.07.02` | Acesso a terminais de atendimento, inclusive vinte e quatro horas. | **EP** | X | - | - | X | - | X |
| `15.07.03` | Acesso a outro banco e à rede compartilhada. | **EP** | X | - | - | X | - | X |
| `15.07.04` | Fornecimento de saldo, extrato e demais informações relativas a contas em geral, por qualquer meio ou processo. | **EP** | X | - | - | X | - | X |
| `15.08.01` | Emissão, reemissão, alteração, cessão, substituição, cancelamento e registro de contrato de crédito. | **EP** | X | - | - | X | - | X |
| `15.08.02` | Estudo, análise e avaliação de operações de crédito. | **EP** | X | - | - | X | - | X |
| `15.08.03` | Emissão, concessão, alteração ou contratação de aval, fiança, anuência e congêneres. | **EP** | X | - | - | X | - | X |
| `15.08.04` | Serviços relativos à abertura de crédito, para quaisquer fins. | **EP** | X | - | - | X | - | X |
| `15.09.01` | Arrendamento mercantil (leasing) de quaisquer bens, inclusive cessão de direitos e obrigações, substituição de garantia, alteração, cancelamento e registro de contrato, e demais serviços relacionados ao arrendamento mercantil (leasing). | **EP** | X | - | - | X | - | X |
| `15.10.01` | Serviços relacionados a cobranças em geral, de títulos quaisquer, de contas ou carnês, de câmbio, de tributos e por conta de terceiros, inclusive os efetuados por meio eletrônico, automático ou por máquinas de atendimento. | **EP** | X | - | - | X | - | X |
| `15.10.02` | Serviços relacionados a recebimentos em geral, de títulos quaisquer, de contas ou carnês, de câmbio, de tributos e por conta de terceiros, inclusive os efetuados por meio eletrônico, automático ou por máquinas de atendimento. | **EP** | X | - | - | X | - | X |
| `15.10.03` | Serviços relacionados a pagamentos em geral, de títulos quaisquer, de contas ou carnês, de câmbio, de tributos e por conta de terceiros, inclusive os efetuados por meio eletrônico, automático ou por máquinas de atendimento. | **EP** | X | - | - | X | - | X |
| `15.10.04` | Serviços relacionados a fornecimento de posição de cobrança, recebimento ou pagamento. | **EP** | X | - | - | X | - | X |
| `15.10.05` | Serviços relacionados a emissão de carnês, fichas de compensação, impressos e documentos em geral. | **EP** | X | - | - | X | - | X |
| `15.11.01` | Devolução de títulos, protesto de títulos, sustação de protesto, manutenção de títulos, reapresentação de títulos, e demais serviços a eles relacionados. | **EP** | X | - | - | X | - | X |
| `15.12.01` | Custódia em geral, inclusive de títulos e valores mobiliários. | **EP** | X | - | - | X | - | X |
| `15.13.01` | Serviços relacionados a operações de câmbio em geral, edição, alteração, prorrogação, cancelamento e baixa de contrato de câmbio. | **EP** | X | - | - | X | - | X |
| `15.13.02` | Serviços relacionados a emissão de registro de exportação ou de crédito. | **EP** | X | - | - | X | - | X |
| `15.13.03` | Serviços relacionados a cobrança ou depósito no exterior. | **EP** | X | - | - | X | - | X |
| `15.13.04` | Serviços relacionados a emissão, fornecimento e cancelamento de cheques de viagem. | **EP** | X | - | - | X | - | X |
| `15.13.05` | Serviços relacionados a fornecimento, transferência, cancelamento e demais serviços relativos a carta de crédito de importação, exportação e garantias recebidas. | **EP** | X | - | - | X | - | X |
| `15.13.06` | Serviços relacionados a envio e recebimento de mensagens em geral relacionadas a operações de câmbio. | **EP** | X | - | - | X | - | X |
| `15.14.01` | Fornecimento, emissão, reemissão de cartão magnético, cartão de crédito, cartão de débito, cartão salário e congêneres. | **EP** | X | - | - | X | - | X |
| `15.14.02` | Renovação de cartão magnético, cartão de crédito, cartão de débito, cartão salário e congêneres. | **EP** | X | - | - | X | - | X |
| `15.14.03` | Manutenção de cartão magnético, cartão de crédito, cartão de débito, cartão salário e congêneres. | **EP** | X | - | - | X | - | X |
| `15.15.01` | Compensação de cheques e títulos quaisquer. | **EP** | X | - | - | X | - | X |
| `15.15.02` | Serviços relacionados a depósito, inclusive depósito identificado, a saque de contas quaisquer, por qualquer meio ou processo, inclusive em terminais eletrônicos e de atendimento. | **EP** | X | - | - | X | - | X |
| `15.16.01` | Emissão, reemissão, liquidação, alteração, cancelamento e baixa de ordens de pagamento, ordens de crédito e similares, por qualquer meio ou processo. | **EP** | X | - | - | X | - | X |
| `15.16.02` | Serviços relacionados à transferência de valores, dados, fundos, pagamentos e similares, inclusive entre contas em geral. | **EP** | X | - | - | X | - | X |
| `15.17.01` | Emissão e fornecimento de cheques quaisquer, avulso ou por talão. | **EP** | X | - | - | X | - | X |
| `15.17.02` | Devolução de cheques quaisquer, avulso ou por talão. | **EP** | X | - | - | X | - | X |
| `15.17.03` | Sustação, cancelamento e oposição de cheques quaisquer, avulso ou por talão. | **EP** | X | - | - | X | - | X |
| `15.18.01` | Serviços relacionados a crédito imobiliário, de avaliação e vistoria de imóvel ou obra. | **EP** | X | - | - | X | - | X |
| `15.18.02` | Serviços relacionados a crédito imobiliário, de análise técnica e jurídica. | **EP** | X | - | - | X | - | X |
| `15.18.03` | Serviços relacionados a crédito imobiliário, de emissão, reemissão, alteração, transferência e renegociação de contrato. | **EP** | X | - | - | X | - | X |
| `15.18.04` | Serviços relacionados a crédito imobiliário, de emissão e reemissão do termo de quitação. | **EP** | X | - | - | X | - | X |
| `15.18.05` | Demais serviços relacionados a crédito imobiliário. | **EP** | X | - | - | X | - | X |
| `16.01.01` | Serviços de transporte coletivo municipal rodoviário de passageiros. | **LP** | - | X | - | X | - | X |
| `16.01.02` | Serviços de transporte coletivo municipal metroviário de passageiros. | **LP** | - | X | - | X | - | X |
| `16.01.03` | Serviços de transporte coletivo municipal ferroviário de passageiros. | **LP** | - | X | - | X | - | X |
| `16.01.04` | Serviços de transporte coletivo municipal aquaviário de passageiros. | **LP** | - | X | - | X | - | X |
| `16.02.01` | Outros serviços de transporte de natureza municipal. | **LP** | - | X | - | X | - | X |
| `17.01.01` | Assessoria ou consultoria de qualquer natureza, não contida em outros itens desta lista. | **EP** | X | - | - | X | - | X |
| `17.01.02` | Análise, exame, pesquisa, coleta, compilação e fornecimento de dados e informações de qualquer natureza, inclusive cadastro e similares. | **EP** | X | - | - | X | - | X |
| `17.02.01` | Datilografia, digitação, estenografia e congêneres. | **EP** | X | - | - | X | - | X |
| `17.02.02` | Expediente, secretaria em geral, apoio e infra-estrutura administrativa e congêneres. | **EP** | X | - | - | X | - | X |
| `17.02.03` | Resposta audível e congêneres. | **EP** | X | - | - | X | - | X |
| `17.02.04` | Redação, edição, revisão e congêneres. | **EP** | X | - | - | X | - | X |
| `17.02.05` | Interpretação, tradução e congêneres. | **EP** | X | - | - | X | - | X |
| `17.03.01` | Planejamento, coordenação, programação ou organização técnica. | **EP** | X | - | - | X | - | X |
| `17.03.02` | Planejamento, coordenação, programação ou organização financeira. | **EP** | X | - | - | X | - | X |
| `17.03.03` | Planejamento, coordenação, programação ou organização administrativa. | **EP** | X | - | - | X | - | X |
| `17.04.01` | Recrutamento, agenciamento, seleção e colocação de mão-de-obra. | **EP** | X | - | - | X | - | X |
| `17.05.01` | Fornecimento de mão-de-obra, mesmo em caráter temporário, inclusive de empregados ou trabalhadores, avulsos ou temporários, contratados pelo prestador de serviço. | **ET** | - | - | X | X | - | X |
| `17.06.01` | Propaganda e publicidade, inclusive promoção de vendas, planejamento de campanhas ou sistemas de publicidade, elaboração de desenhos, textos e demais materiais publicitários. | **EP** | X | - | - | X | - | X |
| `17.08.01` | Franquia (franchising). | **EP** | X | - | - | X | - | X |
| `17.09.01` | Perícias, laudos, exames técnicos e análises técnicas. | **EP** | X | - | - | X | - | X |
| `17.10.01` | Planejamento, organização e administração de feiras, exposições, e congêneres. | **LP** | - | X | - | X | - | X |
| `17.10.02` | Planejamento, organização e administração de congressos e congêneres. | **LP** | - | X | - | X | - | X |
| `17.11.01` | Organização de festas e recepções. | **EP** | X | - | - | X | - | X |
| `17.11.02` | Bufê (exceto o fornecimento de alimentação e bebidas, que fica sujeito ao ICMS). | **EP** | X | - | - | X | - | X |
| `17.12.01` | Administração em geral, inclusive de bens e negócios de terceiros. | **EP** | X | - | - | X | - | X |
| `17.13.01` | Leilão e congêneres. | **EP** | X | - | - | X | - | X |
| `17.14.01` | Advocacia | **EP** | X | - | - | X | - | X |
| `17.15.01` | Arbitragem de qualquer espécie, inclusive jurídica. | **EP** | X | - | - | X | - | X |
| `17.16.01` | Auditoria. | **EP** | X | - | - | X | - | X |
| `17.17.01` | Análise de Organização e Métodos. | **EP** | X | - | - | X | - | X |
| `17.18.01` | Atuária e cálculos técnicos de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `17.19.01` | Contabilidade, inclusive serviços técnicos e auxiliares. | **EP** | X | - | - | X | - | X |
| `17.20.01` | Consultoria e assessoria econômica ou financeira. | **EP** | X | - | - | X | - | X |
| `17.21.01` | Estatística. | **EP** | X | - | - | X | - | X |
| `17.22.01` | Cobrança em geral. | **EP** | X | - | - | X | - | X |
| `17.23.01` | Assessoria, análise, avaliação, atendimento, consulta, cadastro, seleção, gerenciamento de informações, administração de contas a receber ou a pagar e em geral, relacionados a operações de faturização (factoring). | **EP** | X | - | - | X | - | X |
| `17.24.01` | Apresentação de palestras, conferências, seminários e congêneres. | **EP** | X | - | - | X | - | X |
| `17.25.01` | Inserção de textos, desenhos e outros materiais de propaganda e publicidade, em qualquer meio (exceto em livros, jornais, periódicos e nas modalidades de serviços de radiodifusão sonora e de sons e imagens de recepção livre e gratuita). | **EP** | X | - | - | X | - | X |
| `18.01.01` | Serviços de regulação de sinistros vinculados a contratos de seguros e congêneres. | **EP** | X | - | - | X | - | X |
| `18.01.02` | Serviços de inspeção e avaliação de riscos para cobertura de contratos de seguros e congêneres. | **EP** | X | - | - | X | - | X |
| `18.01.03` | Serviços de prevenção e gerência de riscos seguráveis e congêneres. | **EP** | X | - | - | X | - | X |
| `19.01.01` | Serviços de distribuição e venda de bilhetes e demais produtos de loteria, cartões, pules ou cupons de apostas, sorteios, prêmios, inclusive os decorrentes de títulos de capitalização e congêneres. | **EP** | X | - | - | X | - | X |
| `19.01.02` | Serviços de distribuição e venda de bingos e congêneres. | **EP** | X | - | - | X | - | X |
| `20.01.01` | Serviços portuários, ferroportuários, utilização de porto, movimentação de passageiros, reboque de embarcações, rebocador escoteiro, atracação, desatracação, serviços de praticagem, capatazia, armazenagem de qualquer natureza, serviços acessórios, movimentação de mercadorias, serviços de apoio marítimo, de movimentação ao largo, serviços de armadores, estiva, conferência, logística e congêneres. | **LP** | - | (**) X | - | X | - | X |
| `20.02.01` | Serviços aeroportuários, utilização de aeroporto, movimentação de passageiros, armazenagem de qualquer natureza, capatazia, movimentação de aeronaves, serviços de apoio aeroportuários, serviços acessórios, movimentação de mercadorias, logística e congêneres. | **LP** | - | X | - | X | - | X |
| `20.03.01` | Serviços de terminais rodoviários, ferroviários, metroviários, movimentação de passageiros, mercadorias, inclusive suas operações, logística e congêneres. | **LP** | - | X | - | X | - | X |
| `21.01.01` | Serviços de registros públicos, cartorários e notariais. | **EP** | X | - | - | X | - | X |
| `22.01.01` | Serviços de exploração de rodovia mediante cobrança de preço ou pedágio dos usuários, envolvendo execução de serviços de conservação, manutenção, melhoramentos para adequação de capacidade e segurança de trânsito, operação, monitoração, assistência aos usuários e outros serviços definidos em contratos, atos de concessão ou de permissão ou em normas oficiais. | **LP** | - | X | - | X | - | X |
| `23.01.01` | Serviços de programação e comunicação visual e congêneres. | **EP** | X | - | - | X | - | X |
| `23.01.02` | Serviços de desenho industrial e congêneres. | **EP** | X | - | - | X | - | X |
| `24.01.01` | Serviços de chaveiros, confecção de carimbos e congêneres. | **EP** | X | - | - | X | - | X |
| `24.01.02` | Serviços de placas, sinalização visual, banners, adesivos e congêneres. | **EP** | X | - | - | X | - | X |
| `25.01.01` | Funerais, inclusive fornecimento de caixão, urna ou esquifes; aluguel de capela; transporte do corpo cadavérico; fornecimento de flores, coroas e outros paramentos; desembaraço de certidão de óbito; fornecimento de véu, essa e outros adornos; embalsamento, embelezamento, conservação ou restauração de cadáveres. | **EP** | X | - | - | X | - | X |
| `25.02.01` | Translado intramunicipal de corpos e partes de corpos cadavéricos. | **EP** | X | - | - | X | - | X |
| `25.02.02` | Cremação de corpos e partes de corpos cadavéricos. | **EP** | X | - | - | X | - | X |
| `25.03.01` | Planos ou convênio funerários. | **EP** | X | - | - | X | - | X |
| `25.04.01` | Manutenção e conservação de jazigos e cemitérios. | **EP** | X | - | - | X | - | X |
| `25.05.01` | Cessão de uso de espaços em cemitérios para sepultamento. | **EP** | X | - | - | X | - | X |
| `26.01.01` | Serviços de coleta, remessa ou entrega de correspondências, documentos, objetos, bens ou valores, inclusive pelos correios e suas agências franqueadas. | **EP** | X | - | - | X | - | X |
| `26.01.02` | Serviços de courrier e congêneres. | **EP** | X | - | - | X | - | X |
| `27.01.01` | Serviços de assistência social. | **EP** | X | - | - | X | - | X |
| `28.01.01` | Serviços de avaliação de bens e serviços de qualquer natureza. | **EP** | X | - | - | X | - | X |
| `29.01.01` | Serviços de biblioteconomia. | **EP** | X | - | - | X | - | X |
| `30.01.01` | Serviços de biologia e biotecnologia. | **EP** | X | - | - | X | - | X |
| `30.01.02` | Serviços de química. | **EP** | X | - | - | X | - | X |
| `31.01.01` | Serviços técnicos em edificações e congêneres. | **EP** | X | - | - | X | - | X |
| `31.01.02` | Serviços técnicos em eletrônica, eletrotécnica e congêneres. | **EP** | X | - | - | X | - | X |
| `31.01.03` | Serviços técnicos em mecânica e congêneres. | **EP** | X | - | - | X | - | X |
| `31.01.04` | Serviços técnicos em telecomunicações e congêneres. | **EP** | X | - | - | X | - | X |
| `32.01.01` | Serviços de desenhos técnicos. | **EP** | X | - | - | X | - | X |
| `33.01.01` | Serviços de desembaraço aduaneiro, comissários, despachantes e congêneres. | **EP** | X | - | - | X | - | X |
| `34.01.01` | Serviços de investigações particulares, detetives e congêneres. | **EP** | X | - | - | X | - | X |
| `35.01.01` | Serviços de reportagem e jornalismo. | **EP** | X | - | - | X | - | X |
| `35.01.02` | Serviços de assessoria de imprensa. | **EP** | X | - | - | X | - | X |
| `35.01.03` | Serviços de relações públicas. | **EP** | X | - | - | X | - | X |
| `36.01.01` | Serviços de meteorologia. | **EP** | X | - | - | X | - | X |
| `37.01.01` | Serviços de artistas, atletas, modelos e manequins. | **EP** | X | - | - | X | - | X |
| `38.01.01` | Serviços de museologia. | **EP** | X | - | - | X | - | X |
| `39.01.01` | Serviços de ourivesaria e lapidação (quando o material for fornecido pelo tomador do serviço). | **EP** | X | - | - | X | - | X |
| `40.01.01` | Obras de arte sob encomenda. | **EP** | X | - | - | X | - | X |
| `99.01.01` | Serviços sem a incidência de ISSQN e ICMS | **SEM LI** | - | - | - | - | - | X |

## Uso no sistema

O caminho recomendado é:

```text
cTribNac
   ↓
ISSLocationRuleCatalog (importado desta planilha)
   ↓
EP / LP / ET / Import Override
   ↓
resolver fato da operação
   ↓
localidade prevista de incidência
   ↓
API / base oficial de parâmetros municipais
   ↓
alíquota + retenções + regimes + benefícios
   ↓
emissão da DPS
   ↓
comparar com cLocIncid oficial retornado na NFS-e
```

**Conclusão:** a planilha resolve o mapeamento operacional `cTribNac → referência territorial`, que era o núcleo do `ISSLocationEngine`. O engine ainda precisa resolver os dados concretos da operação, tratar importação e casos especiais, e validar divergências/documentação por versão.
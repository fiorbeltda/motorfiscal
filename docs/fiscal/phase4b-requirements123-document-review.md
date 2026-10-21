# Documentos123 — revisão dos quatro itens originais4B.7

Auditoria isolada,sem alterar runtime,schemas ou recursos fiscais da captura122.
Relatório independente:376 conferências,quatro IDs examinados e inventário
integral dos80 itens preservado. A cobertura de revisão passa de54 para58 IDs;
22 ainda não foram revistos. Isso não significa58 requisitos concluídos.

## Resultado por requisito

|Item|Evidência verificada|Estado e limite|
|---|---|---|
|4B.7.1 ProjeçãoDPS/NFS-e|Três decisões integradas no bundle122 geraram seisXML locais válidos noXSD,dois por rota,com bytes idênticos;três tentativas em produção foram recusadas.|Parcial:camposNT009,importação,documentos separados da exportação parcial,ajustes e demais perfis ainda não concluídos.|
|4B.7.2 Fontes oficiais e manual|Manual original conferido integralmente;arquivos oficiais,versões,fechamento de schemas e seleção do ambiente cruzados com execução nativa122.|Parcial:validade estrutural não comprova todas as regras de negócio ou implantação e obrigação documental.|
|4B.7.3 Manual indicado no plano|Arquivo no caminho originalK: e cópia oficial preservada têm o mesmoSHA256;seis páginas lidas e inspecionadas visualmente.|Obrigação de consultar o arquivo comprovada;isso não encerra o grupo documental.|
|4B.7.4 Mudança de schema versionada|106 declarações de schema nos catálogosRTC,agrupadas em três identidades explícitas de versão/hash;raízes únicas e includes/imports locais fechados.|Versionamento comprovado nas declarações examinadas;preservar o mecanismo nas futuras alteraçõesNT009.|

Não foi aprovado encerramento formal de nenhum grupo4B nem produção.

## Manual original e proveniência

Fonte exigida pelo plano:
`K:/GERAL/TI/Projeto - SAAS/Motor Fiscal/Pesquisas Definitivas/Manual Municípios Emissor Público API - Sistema Nacional NFS-e v1.2 out21025.pdf`.

Cópia oficial já preservada:
`resources/fiscal/sources/official/identity104-municipal_manual-observed20261016.pdf`.

Ambas têmSHA256
`3b5e982be9298ecc72ee254e26eb6824dc13d2e05704a83f01aa7a8fd321916e`.
O nome indica1.2/out2025,mas o histórico interno informa1.0/17-03-2025.
Ambas as informações foram mantidas;não se corrigiu ou renomeou a fonte.
O manual trata gerenciamento municipal,parâmetros,consultaDPS/NFS-e e eventos;
não substitui os leiautesRTC/NT009 ou prova de autorização cadastral.
As descrições das rotas de regimes/retenções da página3 também não foram
silenciosamente reinterpretadas como regras fiscais pelo motor.

## Sonda documental corrente

Bundle122,com as mesmas fontes de schema já preservadas. Cenários históricos74
federal,educação e presencial foram usados como origem,sem mudar os fatos
monetários;novos metadados de conhecimento e identidade sintética foram
declarados explicitamente. Cada rota produziu doisXML locais iguais e válidos
no schema restrito. Os cinco camposIBSCBS do emissor foram conciliados com a
decisão;enriquecimentos nacionais não foram emitidos como campos do emissor.
Nenhuma assinatura ou transmissão foi executada.

As três identidades de schema encontradas são:

- `NT004-DPS-XSD-2025-12-10`;
- `NFSE-PRODUCTION-DPS-XSD-2026-02-09`;
- `NFSE-RESTRICTED-DPS-XSD-OBSERVED-2026.10.14`.

O relatório preserva o arquivo oficial,hash,raiz efetiva,fechamento e cada
catálogo associado. Nenhuma mesma versão examinada aponta para hashes de
origem divergentes. A mera existência de um catálogo de produção histórico
não habilita esse ambiente na seleção local restrita.

## Evidência e continuidade

`D:/ProjetoSaasExports/phase4b-requirements123-document-audit-20261021-v1/`:
extração completa e renderizações do manual,logs eXML nativos,recibos e
`requirements-review-v1/requirements123-document-review.json`.
Os94 hashes de runtime122 foram reconferidos sem alteração.

Próximo:concluir os perfis/documentos e campos exigidos,seleção temporal/ambiental,
proveniência dos parâmetros e base/câmbioISS por documento. Não transformar
consulta de manual,XML válido ou versão explícita em homologação integral.

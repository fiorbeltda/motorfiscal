# Correção temporal cambial — 69

A revisão da integração68 demonstrou seis datas impossíveis aceitas pelo qualificador transacional: fim de mês inexistente, início malformado e publicação/observação com31 de setembro, nos contextos de política e testemunho. Comparações lexicográficas e normalização permissiva de `DateTimeImmutable` não provavam validade temporal. Os contraexemplos foram capturados e reproduzidos em protótipo isolado, sem modificar a aplicação durante a primeira execução da suíte.

A correção exige datas de calendário reais no formato AAAA-MM-DD e timestamps com data, hora e offset explícitos, com até seis casas fracionárias. Também exige intervalo ordenado. A data/timestamp reconstruído precisa reproduzir exatamente o informado. O comportamento monetário da transação válida foi preservado. A implementação anterior e o protótipo foram arquivados antes da promoção.

A suíte68 foi interrompida deliberadamente após confirmar a falha. Seu log e código de saída permanecem históricos; ela não comprova aceitação. A suíte sucessora69 começou depois da instalação e formatação da correção. Os56 testes cambiais dirigidos passaram com193 asserções, e o PHPStan geral passou. A conclusão local ainda depende do resultado terminal da nova suíte, formatação geral, replays das18 decisões cambiais/crédito e auditoria final da geração corrigida.

O bundle68 continua como política própria de conversão direta; sua publicação e evidências não foram reescritas. A correção não altera as regras de cotação nem qualifica conversão cruzada, fallback não USD, obrigação documental ou autenticidade governamental. Os escopos68 amplo e4B permanecem abertos. Produção continua bloqueada.

# 137 — retomada e regressão integral em26/10/2026

Estado observado: **TERMINAL_APROVADO_E_AUDITADO**. Meta original
de80 requisitos ativa;0/8 grupos4B encerrados eprodução bloqueada.

Término em26/10/2026 às16:07:24 deSãoPaulo:exit0,9.585 testes e145.896
asserções. Hashes das5.917 entradas iguais antes/depois e na auditoria posterior;
130 arquivos de runtime,três de suporte,fontes e replaysCLI conferidos.
Recibo imutável:
`storage/app/phase4b-retention137-development-20261023/retention137-full-validation.json`,
SHA256 `84ad1d283f5d575482984f6e3b302be52f662c96b4dd3f42a90cc1af34b05542`.
A matriz80 integral está na mesma pasta,`requirements80-full-matrix.json`.
O contêiner temporário foi removido automaticamente após terminar.
As restrições de congelamento descritas abaixo foram cumpridas durante a captura;
a implementação139 começou somente após a auditoria terminal137.

O checkout retomado corresponde aos commits publicados `cca43e8`/`8670d83`.
O Docker estava desligado. Foi iniciado viaWin32_Process.Create fora da árvore
transitória do executor. Os contêineres existentes foram iniciados sem recriação,
mantendo volumes,banco,imagem e caminhos das evidências.

A conferência verificou a mesma imagem econtêiner de aplicação,os mesmos mounts,
130 hashes de runtime etrês arquivos de suporte137. Todos os349 arquivos das
duas últimas migrações conferem entreC:,D: econtêiner;duas amostras anteriores
também conferem. Recibo:
`D:/ProjetoSaasExports/resume137-20261026-v1/preflight-validation.json`.

A suíte sem filtros iniciou em **2026-10-26T18:06:43.718436UTC**
(15:06:43 deSãoPaulo), com5.917 entradas congeladas. ExecutaPest com512MB
na mesma imagem/ambiente da aplicação ebind adicional de temporários noD:.
Contêiner: `phase4b-retention137-full-suite-v1`;
ID `e56d042dc2af3b1c2c397af7fd5a2ca20f95ef7adecb3f9d9c8d0b863d866f43`.
Sessão de captura82441;PIDLinux observado1775.

Evidências volumosas:
`D:/ProjetoSaasExports/phase4b-retention137-20261023-v1/full-suite-v1`.
O nome da pasta preserva o vínculo com a integração de23/10;os horários dos
recibos identificam a execução de26/10. Não reiniciar esta execução por timeout
de observação e não modificarapp,tests,resources/fiscal,scripts,config,routes
oucompose.yaml durante a captura. Documentação,monitoring epreparação externa
noD: podem avançar sem mudar as entradas congeladas.

Na retomada,a última integral aceita era136,9.569 testes/145.843 asserções. A integração137 tem
41 testes dirigidos/118 asserções,CLI real repetido comquatroXML/XSD ePHPStan/Pint
aprovados. Esses resultados permanecem delimitados e não substituem a nova
regressão integral. Não foi criado um aceite137 com base no início da suíte.

Após término: conferirstdout,exitcode,hashes antes/depois/atuais,imagem,mounts,
fontes,recibosCLI ematriz80. Auditor preparado,sem iniciar testes:
`D:/ProjetoSaasExports/phase4b-retention137-20261023-v1/audit_full137.py`.
Foram atualizados os três registros do console eoito grupos somente com o resultado
efetivamente observado. O console passou28 checks eoendpoint registra4B
em andamento,fases3/4A encerradas emSandbox efases5–7 planejadas.

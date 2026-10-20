# Limpeza adicional de caches - 19/10/2026,14:36

Depois de o C: cair para cerca de162 MB livres,foram removidos1.793 arquivos
com mais de48 horas,totalizando539.818.347 bytes logicos,com copias locais
recuperaveis no D: e SHA256 conferido antes de cada exclusao.

Alvos:cache de downloadsVSIX do VSCode e entradas individuais de cacheJS
compilado do Chrome/Edge. Extensoes instaladas,perfis,cookies,senhas,indices,
projeto,evidencias,ambientes,discos virtuais,paginacao e processos preservados.
Nao houve exclusao recursiva de diretorios.

Relatorio e mapa de recuperacao:
`D:/ProjetoSaasExports/cleanup-c-user-caches-20261019-v2/cleanup-report.json`.
Copias em `recoverable/`,com caminho original e hash de cada arquivo.

Livre durante a execucao:161.767.424→626.905.088 bytes. A variacao inclui
atividade concorrente do sistema;nao equivale aos bytes logicos removidos.
O C: continua com pouca capacidade;novos artefatos/logs permanecem no D:.

Tambem foi identificado `C:/Windows/MEMORY.DMP`,de16/10,1.088.760.882 bytes.
A tentativa de abrir exclusivamente para copia foi negada pelo Windows,
antes de criar copia ou remover o arquivo. Nenhuma elevacao,mudanca de
permissao ou propriedade foi aplicada ao dump. Ele permanece intacto.
Registro:`D:/ProjetoSaasExports/cleanup-c-crashdump-20261019-v1-failure.json`.

Regressao108 permaneceu viva e sem reinicio. Manutencao3/3 marcos concluida
(selecao,copias/remocao,acompanhamento);nao e avance funcional fiscal.
Fases3/4A encerradas em Sandbox,4B em andamento com oito grupos parciais,
5–7 planejadas,producao bloqueada. Nenhum novo aceite108.

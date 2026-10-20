"""Record audited candidate25 while preserving chronological documentation."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
current='Estado corrente — Candidato25 (06/10/2026): rendimento art.195 de custo constante qualificado por emissor/oferta/posição, integrado à base financeira e ao D2101 com origem fiscal única. Base conjunta R$798,17 no exemplo, D2101 R$98,17; gross contábil preservado e perdas sem compensação entre títulos. Arredondamento DeRE8/2 HALF_EVEN, XSD1.3.0. Suíte998/3.315, PHPStan sem erros, Pint369,39 casos/72 corpos XML/12 replays; regressões25/24/23 e2.593 históricos preservados. Gate47/47 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-d2101-yield-integration.md).'
for name in ['phase4b-status.md','implementation-status.md','phase4b-dere-monthly-closing.md','phase4b-financial-base.md']:
 p=ROOT/'docs/fiscal'/name;s=p.read_text(encoding='utf-8')
 if current in s:continue
 s=s.replace('Estado corrente — Candidato24','Histórico — Candidato24',1)
 parts=s.split('\n',2);p.write_text(parts[0]+'\n\n'+current+'\n\n'+parts[2],encoding='utf-8')
p=ROOT/'docs/fiscal/phase4b-next-scope-public-offer-yield.md'
s=p.read_text(encoding='utf-8')
note='## Aceitação25 e sequência\n\nO objetivo25 foi concluído localmente:998 testes/3.315 asserções,39 casos/72 XML/12 replays, regressões25/24/23 e2.593 históricos preservados. Relatório em `storage/app/phase4b-yield-20261006-25/verification-report.json`; contrato em `phase4b-dere-d2101-yield-integration.md`. O25 usa RN DeRE HALF_EVEN8/2 com fonte preservada, mantendo24 inalterado.\n\nPróximo objetivo: construir memória extracontábil diária para custo médio com movimentação, incluindo datas, quantidades, PU, custo de entrada/saída e Selic, antes da integração tributária mensal. Pesquisar a convenção temporal e o tratamento de alienações; regras locais devem ser explícitas e não declaradas homologadas quando a documentação oficial não as define. Posições e curvas comprovadas, reconciliação completa, recusas de ambiguidade e regressão25 são condições de aceitação. Não encerrar a4B por concluir esse componente.\n'
if '## Aceitação25 e sequência' not in s:p.write_text(s+'\n'+note,encoding='utf-8')

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Motor Fiscal — Monitoramento</title>
    <style>
        :root{--bg:#f6f7f9;--paper:#fff;--ink:#202a37;--muted:#657184;--line:#e1e6ed;--accent:#315a68}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.6 "Segoe UI",Arial,sans-serif}
        main{max-width:1240px;margin:auto;padding:28px 32px 40px}h1,h2,h3,p{margin:0}h1{font-size:29px;letter-spacing:-.7px;font-weight:600}h2{font-size:17px}h3{font-size:13px;margin-bottom:8px}
        a{color:var(--accent);text-underline-offset:3px}button,input{font:inherit}button,summary{cursor:pointer}button:focus-visible,a:focus-visible,summary:focus-visible,input:focus-visible{outline:3px solid #568eab;outline-offset:3px}
        .top,.heading,.panel-head,.row,.footer{display:flex;align-items:center;justify-content:space-between;gap:16px}.top,.footer{font-size:11px;color:var(--muted)}.top{padding-bottom:20px;border-bottom:1px solid var(--line)}.heading{padding:26px 0 22px}.eyebrow{font-size:10px;letter-spacing:1.6px;text-transform:uppercase;color:var(--muted);margin-bottom:4px}.actions{display:flex;align-items:center;gap:15px;font-size:12px}
        button{border:1px solid var(--line);background:var(--paper);padding:8px 15px;border-radius:6px;color:var(--ink)}button:disabled{opacity:.5;cursor:wait}.tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--line);padding-bottom:12px}.tabs button{border-color:transparent;background:transparent;color:var(--muted)}.tabs button[aria-pressed=true]{background:var(--ink);color:white}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.stat,.panel{background:var(--paper);border:1px solid var(--line);border-radius:9px;min-width:0}.stat{padding:18px 20px}.label,.muted{color:var(--muted);font-size:12px}.value{font-size:28px;letter-spacing:-.6px;margin:5px 0}.stat small{display:block;color:var(--muted);font-size:11px}.panel{margin-bottom:18px}.panel-head{padding:18px 22px;border-bottom:1px solid var(--line)}.body{padding:20px 22px}.count{font-size:12px;color:var(--muted);white-space:nowrap}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}.body p+p{margin-top:10px}.summary{max-width:850px;font-size:15px}.track{height:5px;background:#e9edf2;border-radius:4px;overflow:hidden;margin:14px 0}.track i{display:block;height:100%;background:var(--accent)}.next{border-top:1px solid var(--line);padding-top:16px;margin-top:18px}.badge{display:inline-block;padding:3px 9px;background:#eef2f5;border-radius:4px;font-size:11px;white-space:nowrap}.good{color:#27634d;background:#eaf4ef}.blocked{color:#815b22;background:#fbf3e5}
        ul{padding-left:19px;margin:8px 0 0}li+li{margin-top:8px}li,p,code{overflow-wrap:anywhere}details{min-width:0}details>summary{color:var(--ink);font-size:13px}details[open]>summary{margin-bottom:12px}.fold{border-top:1px solid var(--line);padding:15px 22px}.fold .fold{padding:12px 0}.notice{background:#fbf3e5;border:1px solid #ebd9b9;border-radius:6px;padding:12px 18px;margin-bottom:18px}.notice p{font-size:12px;margin-top:10px}.phase,.objective{border-top:1px solid var(--line);padding:16px 22px}.phase:first-of-type{border-top:0}.phase .row>div:first-child,.objective .row>div:first-child{min-width:0}.phase-code{color:var(--muted);font:11px Consolas,monospace;display:inline-block;min-width:40px}.phase-name{font-weight:600;font-size:13px}.phase details{margin-top:8px}.objective>summary{display:flex;align-items:center;justify-content:space-between;gap:18px;list-style:none}.objective>summary::after{content:'+';color:var(--muted)}.objective[open]>summary::after{content:'−'}.objective-body{padding-top:8px}.objective-body>details{padding:12px 0;border-top:1px solid var(--line)}.objective-body li{font-size:13px}.note{padding:14px 22px;border-top:1px solid var(--line);font-size:12px;color:var(--muted)}code{font:11px/1.7 Consolas,monospace;color:var(--muted)}.history-item{padding:16px 0;border-top:1px solid var(--line)}.history-item:first-child{border:0}.history-item p{font-size:13px;margin-top:10px}.document{display:block;padding:13px 0;border-top:1px solid var(--line);font-size:13px}.document:first-child{border:0}.document small{display:block;color:var(--muted)}.search{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:5px;margin-bottom:14px}.footer{margin-top:24px}.offline{color:#9c4141}#refresh-message:not(:empty){padding:12px 0;color:#9c4141} [hidden]{display:none!important}
        @media(max-width:760px){main{padding:20px 16px}.stats{grid-template-columns:1fr 1fr;gap:10px}.grid{grid-template-columns:1fr;gap:18px}.stat{padding:14px}.value{font-size:24px}.panel-head,.body,.phase,.objective,.fold{padding:16px}.tabs{gap:2px}.tabs button{padding:8px 9px;font-size:12px}.actions a{display:none}.heading{align-items:center}.panel-head{align-items:flex-start}.count{white-space:normal;text-align:right}.footer{align-items:flex-start;font-size:10px}}
        @media(prefers-reduced-motion:reduce){*{scroll-behavior:auto}}
    </style>
</head>
<body>
<main>
    <header class="top"><span>Workspace / Monitoramento fiscal</span><span id="connection" role="status">Conectado</span></header>
    <div class="heading"><div><div class="eyebrow">Desenvolvimento local</div><h1>Motor fiscal</h1></div><div class="actions"><a href="{{ route('fiscal-console.status') }}" target="_blank" rel="noopener">Exportar JSON ↗</a><button type="button" id="refresh">Atualizar</button></div></div>
    <nav class="tabs" aria-label="Áreas do console">
        <button type="button" data-view="overview" aria-pressed="true" aria-controls="overview">Visão geral</button>
        <button type="button" data-view="objectives" aria-pressed="false" aria-controls="objectives">Fases e objetivos</button>
        <button type="button" data-view="history" aria-pressed="false" aria-controls="history">Histórico e evidências</button>
    </nav>
    <div id="refresh-message" role="status" aria-live="polite"></div>
    <div id="dashboard" aria-busy="false"></div>
    <footer class="footer"><span>Somente leitura · atualização a cada 30 s</span><span id="updated"></span></footer>
</main>
<script>
(() => {
    const initial = {{ Illuminate\Support\Js::from($snapshot) }};
    const statusUrl = {{ Illuminate\Support\Js::from(route('fiscal-console.status', [], false)) }};
    const documentBase = {{ Illuminate\Support\Js::from(url('/console-fiscal/documento')) }};
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const number = value => value == null ? '—' : new Intl.NumberFormat('pt-BR').format(value);
    const date = value => value && !Number.isNaN(Date.parse(value)) ? new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short',timeZone:'America/Sao_Paulo'}).format(new Date(value)) : 'Sem registro';
    const labels = {APPROVED:'Encerrado',PARTIAL:'Parcial',OPEN:'Aberto',UNKNOWN:'Não aferido',CLOSED:'Encerrada',IN_PROGRESS:'Em andamento',IMPLEMENTED:'Implementada',UNMEASURED:'Não aferida',PLANNED:'Planejada'};
    const status = state => `<span class="badge ${['CLOSED','APPROVED','IMPLEMENTED'].includes(state)?'good':''}">${esc(labels[state] || state)}</span>`;
    const list = items => items?.length ? `<ul>${items.map(t=>`<li>${esc(t)}</li>`).join('')}</ul>` : '<p class="muted">Sem itens registrados.</p>';
    const docLink = path => `<a class="document" href="${esc(documentBase+'/'+encodeURIComponent(path.split('/').pop()))}" target="_blank" rel="noopener">${esc(path)} ↗</a>`;
    const names = {'3':'ISS','4A':'Retenções federais','4B':'IBS / CBS','5':'Submotores verticais','6':'Governança','7':'Validação final',base:'Kernel e infraestrutura'};
    const dashboard = document.getElementById('dashboard');
    let activeView = 'overview';
    let historyQuery = '';
    function selectView(view) {
        activeView = view;
        dashboard.querySelectorAll('[data-page]').forEach(el=>{el.hidden=el.dataset.page!==view;});
        document.querySelectorAll('[data-view]').forEach(el=>el.setAttribute('aria-pressed',String(el.dataset.view===view)));
    }
    function filterHistory() {
        dashboard.querySelectorAll('[data-history-item]').forEach(el=>{el.hidden=!el.textContent.toLocaleLowerCase('pt-BR').includes(historyQuery.toLocaleLowerCase('pt-BR'));});
        const count = dashboard.querySelector('#history-count');
        if(count) count.textContent = dashboard.querySelectorAll('[data-history-item]:not([hidden])').length+' registros';
    }
    function render(s) {
        const openDetails = [...dashboard.querySelectorAll('details[open][id]')].map(el=>el.id);
        const focused = document.activeElement;
        const searchFocused = focused?.id==='history-search';
        const selection = searchFocused ? [focused.selectionStart,focused.selectionEnd] : null;
        const accepted = s.acceptance || {};
        const current = s.phases.find(p=>p.id===s.current_phase);
        const objectives = current?.details || [];
        const task = s.task_progress;
        const focus = task?.focus;
        const integrity = s.integrity || {};
        dashboard.innerHTML = `
            <section data-page="overview" id="overview" aria-label="Visão geral">
                <div class="stats">
                    <article class="stat"><div class="label">Fase atual</div><div class="value">${esc(s.current_phase)}</div><small>${esc(names[s.current_phase] || current?.title)}</small></article>
                    <article class="stat"><div class="label">Grupos 4B encerrados</div><div class="value">${number(current?.completed)} / ${number(current?.total)}</div><small>${objectives.filter(o=>o.state==='PARTIAL').length} grupos parciais · sem % global auditada</small></article>
                    <article class="stat"><div class="label">Testes no último aceite</div><div class="value">${number(accepted.tests)}</div><small>${number(accepted.assertions)} verificações</small></article>
                    <article class="stat"><div class="label">Produção</div><div class="value" style="font-size:23px">${accepted.production_ready===true?'Aprovada':accepted.production_ready===false?'Bloqueada':'Não aferida'}</div><small>${number(accepted.blocker_count)} impedimentos no registro</small></article>
                </div>
                ${s.warnings?.length?`<details class="notice" id="warnings-detail"><summary>Atenção às evidências · ${s.warnings.length} aviso(s)</summary>${s.warnings.map(w=>`<p>${esc(w)}</p>`).join('')}<p><code>${(integrity.mismatches||[]).map(esc).join('<br>')}</code></p></details>`:''}
                ${task?`<article class="panel"><div class="panel-head"><div><div class="eyebrow">Entrega acompanhada</div><h2>${esc(task.title)}</h2></div><span class="count">${number(task.percent)}% · ${number(task.completed)}/${number(task.total)} marcos</span></div><div class="body"><p class="summary">${esc(focus?.summary || task.state)}</p><div class="track" role="progressbar" aria-label="Marcos da entrega, não progresso global da Fase 4B" aria-valuenow="${task.percent}" aria-valuemin="0" aria-valuemax="100"><i style="width:${task.percent}%"></i></div><div class="grid"><div><h3>Concluído neste corte</h3>${list(focus?.finished || (task.finished||[]).slice(0,2))}</div><div><h3>Pendências atuais</h3>${list(focus?.pending || (task.pending||[]).slice(0,2))}</div></div><div class="next"><h3>Próximo passo</h3><p>${esc(focus?.next_step || task.next_step)}</p></div></div><details class="fold" id="task-milestones"><summary>Critério e marcos da entrega</summary><p class="muted">${esc(task.criterion)}</p>${list((task.milestones||[]).map(m=>(m.complete?'Concluído · ':'Pendente · ')+m.title))}<p class="muted">Revisado ${date(task.updated_at)}. Histórico completo na terceira área.</p></details></article>`:''}
                <article class="panel"><div class="panel-head"><h2>Última aceitação local</h2><span class="count">${date(accepted.recorded_at)}</span></div><div class="body"><p><code>${esc(s.bundle || 'Sem bundle aceito')}</code></p><p class="muted">${number(integrity.checked)} arquivos conferidos · ${integrity.state==='MATCH'?'Hashes coincidem':integrity.state==='CHANGED'?number(integrity.mismatches?.length)+' alterações posteriores':'Integridade não aferida'}. Aceitação local não libera produção.</p></div><details class="fold" id="validation-source"><summary>Origem e alcance da validação</summary><p><code>${esc(accepted.source || 'Sem relatório')}</code></p><p class="muted">${number(accepted.scenarios)} cenários · ${number(accepted.replays)} replays · ${number(accepted.sources)} fontes. Registro histórico; não valida mudanças posteriores ou arquivos fora do relatório.</p></details></article>
            </section>
            <section data-page="objectives" id="objectives" hidden aria-label="Fases e objetivos">
                <article class="panel"><div class="panel-head"><h2>Fases do projeto</h2><span class="count">${number(s.roadmap_summary?.closed_phases)} encerradas em Sandbox</span></div>${s.phases.map(p=>`<div class="phase"><div class="row"><div><span class="phase-code">${p.id==='base'?'01–02':esc(p.id)}</span><span class="phase-name">${esc(names[p.id] || p.title)}</span></div><div>${status(p.state)} <span class="count">${p.functional_percent!=null?' · '+number(p.functional_percent)+'%':''}</span></div></div><details id="phase-${esc(p.id)}"><summary>Cobertura e critério</summary><p class="muted">${esc(p.progress_note)}</p>${p.source?.startsWith('docs/fiscal/')?docLink(p.source):''}</details></div>`).join('')}<div class="note">Fases 3 e 4A encerradas funcionalmente em Sandbox. 4B parcial, sem percentual funcional global auditado. Fases 5–7 planejadas.</div></article>
                <article class="panel"><div class="panel-head"><h2>Objetivos da Fase ${esc(s.current_phase)}</h2><span class="count">${number(current?.completed)} / ${number(current?.total)} encerrados</span></div>${objectives.map(o=>`<details class="objective" id="objective-${esc(o.id)}"><summary><span><span class="phase-code">${esc(o.id)}</span>${esc(o.title)}</span>${status(o.state)}</summary><div class="objective-body"><h3>Falta para encerrar</h3>${list(o.pending)}<details id="delivered-${esc(o.id)}"><summary>Entregas locais (${o.finished?.length || 0})</summary>${list(o.finished)}</details><details id="sources-${esc(o.id)}"><summary>Evidências e controles formais</summary>${(o.evidence||[]).map(docLink).join('')}${(o.controls||[]).map(c=>`<p><code>${esc(c.id)} · ${esc(c.state)}</code></p><p class="muted">${esc(c.gap || 'Sem pendência descrita.')}</p>`).join('')}</details></div></details>`).join('')}<div class="note">Encerramento dos grupos não mede volume de implementação. Os 80 requisitos originais exigem auditoria individual.</div></article>
            </section>
            <section data-page="history" id="history" hidden aria-label="Histórico e evidências">
                <article class="panel"><div class="panel-head"><h2>Histórico das entregas</h2><span class="count" id="history-count"></span></div><div class="body"><p class="muted">Registros de cortes anteriores, preservados sem substituir a situação atual.</p><label for="history-search" class="muted">Buscar nos registros</label><input id="history-search" class="search" type="search" placeholder="Ex.: 108, câmbio, limpeza…" value="${esc(historyQuery)}">${(task?.history||[]).map((h,i)=>`<details class="history-item" data-history-item id="history-${i}"><summary>${esc(h.title)} <span class="muted">· ${date(h.updated_at || h.recorded_at || h.at || h.date)}</span></summary><p>${esc(h.state)}</p><p>${esc(h.summary)}</p>${h.next_step?`<p>${esc(h.next_step)}</p>`:''}</details>`).join('')}<details class="fold" id="task-full"><summary>Registro detalhado: entregas e pendências</summary><h3>Entregas acumuladas</h3>${list(task?.finished)}<h3>Pendências do registro atual</h3>${list(task?.pending)}<p>${esc(task?.state)}</p><p>${esc(task?.next_step)}</p></details></div></article>
                <article class="panel"><div class="panel-head"><h2>Documentação e evidências</h2><span class="count">${s.documents.length} documentos</span></div><details class="fold" id="documents-all"><summary>Consultar documentos por atualização</summary>${s.documents.map(d=>`<a class="document" target="_blank" rel="noopener" href="${esc(documentBase+'/'+encodeURIComponent(d.name))}">${esc(d.title)} ↗<small>${date(d.modified_at)}</small></a>`).join('')}</details><details class="fold" id="dependencies-all"><summary>Dependências do último aceite (${s.dependencies.length})</summary>${list(s.dependencies.map(d=>d.code))}</details></article>
            </section>`;
        openDetails.forEach(id=>{const el=document.getElementById(id);if(el) el.open=true;});
        selectView(activeView);
        filterHistory();
        if(searchFocused){const input=document.getElementById('history-search');input.focus({preventScroll:true});input.setSelectionRange(...selection);}
        document.getElementById('updated').textContent='Leitura '+date(s.observed_at)+' · Brasília';
    }
    document.querySelector('.tabs').addEventListener('click',event=>{const button=event.target.closest('[data-view]');if(button) selectView(button.dataset.view);});
    dashboard.addEventListener('input',event=>{if(event.target.id==='history-search'){historyQuery=event.target.value;filterHistory();}});
    let loading=false;
    async function refresh() {
        if(loading) return;
        loading=true;
        const button=document.getElementById('refresh');button.disabled=true;dashboard.setAttribute('aria-busy','true');
        try {
            const response=await fetch(statusUrl,{cache:'no-store',signal:AbortSignal.timeout(12000),headers:{Accept:'application/json'}});
            if(!response.ok) throw new Error('HTTP '+response.status);
            render(await response.json());
            document.getElementById('connection').classList.remove('offline');document.getElementById('connection').textContent='Conectado';document.getElementById('refresh-message').textContent='';
        } catch {
            document.getElementById('connection').classList.add('offline');document.getElementById('connection').textContent='Sem conexão';document.getElementById('refresh-message').textContent='Atualização indisponível. Exibindo a última leitura.';
        } finally {loading=false;button.disabled=false;dashboard.setAttribute('aria-busy','false');}
    }
    render(initial);
    document.getElementById('refresh').addEventListener('click',refresh);
    setInterval(()=>{if(!document.hidden) refresh();},30000);
})();
</script>
</body>
</html>

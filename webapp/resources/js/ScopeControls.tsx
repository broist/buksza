import { router } from '@inertiajs/react';
import { Ft } from './components';
import type { FinanceProps } from './types';

export default function ScopeControls({p}:{p:FinanceProps}) {
    const change=(account:string,person:string)=>router.get(location.pathname,{year:p.year,month:p.month,...(account?{account_id:account}:{}),...(person?{person}:{})},{preserveScroll:true});
    const filtered=Boolean(p.filters.account_id||p.filters.person);
    return <div className="scope-controls"><div className="scope-selects"><label>Számla<select aria-label="Számla" value={p.filters.account_id??''} onChange={e=>change(e.target.value,p.filters.person??'')}><option value="">Minden számla</option>{p.accounts.map(a=><option key={a.id} value={a.id}>{a.name}{!a.active?' · Archivált':''}</option>)}</select></label><label>Személy<select aria-label="Személy" value={p.filters.person??''} onChange={e=>change(String(p.filters.account_id??''),e.target.value)}><option value="">Mindenki</option>{p.people.map(person=><option key={person}>{person}</option>)}</select></label></div>{filtered&&<div className="notice"><strong>Szűrt havi tételek</strong><div className="detail-line"><span>Kiadás a családi elszámolásban</span><Ft value={p.scopedReport.expense}/></div><div className="detail-line"><span>Bevétel a családi elszámolásban</span><Ft value={p.scopedReport.income}/></div><p>A napi lista és diagramok szűrtek. A családi CF, megtakarítási cél és kategóriakeretek az egész család számai maradnak; ezekből nem készül személyes részkeret.</p><button className="text-link" onClick={()=>change('','')}>Szűrők törlése</button></div>}</div>;
}

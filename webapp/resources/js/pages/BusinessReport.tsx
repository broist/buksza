import { useMemo, useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Download, Pencil } from 'lucide-react';
import { ErrorText, Ft } from '../components';
import type { FinanceProps, Transaction } from '../types';

type Totals = {income:number;expense:number;difference:number};
export type BusinessReportData = {from:string;to:string;group:'all'|'business'|'personal';totals:Record<'all'|'business'|'personal',Totals>;months:Record<string,Record<'all'|'business'|'personal',Totals>>;categories:{id:number;all:number;business:number;personal:number}[];rows:Transaction[];rawTransactions:Transaction[]};
const groupNames = {all:'Összesen',business:'Üzleti',personal:'Magán'};

export default function BusinessReport({p,edit}:{p:FinanceProps;edit:(tx:Transaction)=>void}) {
    const report=p.businessReport!;
    const form=useForm({from:report.from,to:report.to,group:report.group});
    const [search,setSearch]=useState(''),[page,setPage]=useState(0);
    const rows=useMemo(()=>report.rows.filter(tx=>`${tx.date} ${tx.amount} ${tx.note??''} ${p.categories.find(c=>c.id===tx.category_id)?.name??''} ${p.accounts.find(a=>a.id===tx.account_id)?.name??''}`.toLocaleLowerCase('hu-HU').includes(search.toLocaleLowerCase('hu-HU'))),[report.rows,search,p.categories,p.accounts]);
    const current=Math.min(page,Math.max(0,Math.ceil(rows.length/20)-1));
    const query=new URLSearchParams({from:report.from,to:report.to,group:report.group});
    const choose=(group:BusinessReportData['group'])=>router.get('/uzleti-riport',{from:report.from,to:report.to,group},{preserveScroll:true});
    return <div className="business-report">
        <section className="card"><h2>Riport időszaka</h2><form onSubmit={event=>{event.preventDefault();form.get('/uzleti-riport',{preserveScroll:true,onSuccess:()=>{setPage(0);setSearch('');}});}}><div className="form-grid">
            <label>Kezdőnap<input type="date" value={form.data.from} onChange={event=>form.setData('from',event.target.value)} min="2000-01-01" max="2100-12-31" required/><ErrorText error={form.errors.from}/></label>
            <label>Zárónap<input type="date" value={form.data.to} onChange={event=>form.setData('to',event.target.value)} min={form.data.from} max="2100-12-31" required/><ErrorText error={form.errors.to}/></label>
        </div><button className="button primary" disabled={form.processing}>Riport lekérése</button></form>
        <p className="muted">Az üzleti jelölés szerinti bevétel- és kiadásbontás. A különbség megtakarítás előtti összeg; a családi CF és a közös keretek változatlanok. Az átvezetések és megtakarítások nem szerepelnek kiadásként, a banki díjak egyszer számítanak.</p></section>
        <div className="business-totals">{(['all','business','personal'] as const).map(group=><section className="card" key={group} aria-label={`${groupNames[group]} időszaki összesítő`}>
            <h2>{groupNames[group]}</h2><div className="detail-line"><span>Időszaki bevétel</span><Ft value={report.totals[group].income}/></div><div className="detail-line"><span>Időszaki kiadás</span><Ft value={report.totals[group].expense}/></div><div className="detail-line"><span>Bevétel mínusz kiadás</span><Ft value={report.totals[group].difference}/></div>
        </section>)}</div>
        <section className="card"><h2>Részletek · {report.from} – {report.to}</h2><div className="business-tabs" role="group" aria-label="Riport bontása">{(['business','personal','all'] as const).map(group=><button className={`button ${report.group===group?'primary':'secondary'}`} key={group} aria-pressed={report.group===group} onClick={()=>choose(group)}>{groupNames[group]}</button>)}</div>
            <div className="quick-actions"><a className="button secondary" href={`/uzleti-riport/export?${query}&format=xlsx`}><Download size={18}/>Riport · Excel</a><a className="button secondary" href={`/uzleti-riport/export?${query}&format=csv`}>Tételek · CSV</a></div>
            <p className="muted">Az export a kiválasztott időszak teljes riportját tartalmazza; a tétellista az itt kiválasztott bontást követi. A kereső és lapozás az exportot nem szűri.</p>
            <h3>Kiadások kategóriánként · {groupNames[report.group]}</h3>{report.categories.filter(row=>row[report.group]>0).map(row=><div className="detail-line" key={row.id}><span>{p.categories.find(c=>c.id===row.id)?.name}</span><Ft value={row[report.group]}/></div>)}
            {!report.categories.some(row=>row[report.group]>0)&&<p>Nincs kiadás ebben a bontásban.</p>}
            <h3>Havi bontás · {groupNames[report.group]}</h3>{Object.entries(report.months).map(([month,groups])=><div className="business-month" key={month}><strong>{month}</strong><div className="detail-line"><span>Bevétel</span><Ft value={groups[report.group].income}/></div><div className="detail-line"><span>Kiadás</span><Ft value={groups[report.group].expense}/></div></div>)}
        </section>
        <section className="card"><h2>Időszaki tételek · {groupNames[report.group]}</h2><label>Keresés a riport tételeiben<input type="search" value={search} onChange={event=>{setSearch(event.target.value);setPage(0);}}/></label>
            <p className="muted">{rows.length} elszámolási sor. A díj külön soron szerepel; a szerkesztés az eredeti pénzmozgást nyitja meg.</p>
            {rows.slice(current*20,current*20+20).map(tx=><article className="business-entry" key={`${tx.id}-${tx.is_fee?'fee':'main'}`}>
                <strong>{tx.is_fee?'Banki díj':tx.type==='income'?'Bevétel':p.categories.find(category=>category.id===tx.category_id)?.name}</strong><small>{tx.date} · {tx.business?'Üzleti':'Magán'}</small><span>{p.accounts.find(account=>account.id===tx.account_id)?.name}{tx.target_account_id?` → ${p.accounts.find(account=>account.id===tx.target_account_id)?.name}`:''}</span>{tx.note&&<p>{tx.note}</p>}<Ft value={tx.amount}/>
                <button className="button secondary" onClick={()=>edit(report.rawTransactions.find(raw=>raw.id===tx.id)!)}><Pencil size={16}/>{tx.is_fee?'Kapcsolódó pénzmozgás szerkesztése':'Tétel szerkesztése'}</button>
            </article>)}{!rows.length&&<p>Nincs megfelelő tétel.</p>}
            {rows.length>20&&<div className="quick-actions"><button className="button secondary" disabled={current===0} onClick={()=>setPage(current-1)}>Előző oldal</button><span>{current+1} / {Math.ceil(rows.length/20)}</span><button className="button secondary" disabled={(current+1)*20>=rows.length} onClick={()=>setPage(current+1)}>Következő oldal</button></div>}
        </section>
    </div>;
}

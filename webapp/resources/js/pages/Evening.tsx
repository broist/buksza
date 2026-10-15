import { Link, router, useForm } from '@inertiajs/react';
import { Plus, ArrowRight, Check } from 'lucide-react';
import type { FinanceProps, Transaction } from '../types';

export default function Evening({p,open,row}:{p:FinanceProps;open:(type?:string,date?:string)=>void;row:(tx:Transaction)=>React.ReactNode}) {
    const date=p.eveningDate,items=p.transactions.filter(t=>t.date===date);
    const note=useForm({date,note:p.notes[date]??''});
    const anna=p.accounts.find(a=>a.kind==='snapshot'&&a.owner==='Anna');
    const monthEnd=Number(date.slice(8))===p.report.daysCount;
    return <div className="flow-stack"><section className="card"><h2>Egy perc a mai kasszára</h2><label>Rögzítés napja<input type="date" value={date} max={p.today} min="2000-01-01" onChange={e=>e.target.value&&router.get('/esti-rogzites',{date:e.target.value})}/></label><p className="muted">Írd be a tényleges költéseket. A „Mentés és következő” gombbal egymás után több tételt is felvehetsz.</p><div className="quick-actions"><button className="button primary" onClick={()=>open('expense',date)}><Plus size={18}/>Kiadás</button><button className="button secondary" onClick={()=>open('income',date)}><Plus size={18}/>Bevétel</button><button className="button secondary" onClick={()=>open('transfer',date)}>Átvezetés</button></div></section>
        {monthEnd&&anna&&!anna.month_reported&&<section className="card"><h2>Anna hónap végi helyzetjelentése</h2><p className="muted">Add meg Anna saját számlájának egyenlegét ezen a napon.</p><Link className="text-link" href={`/szamlak?year=${p.year}&month=${p.month}&anna=1`}>Helyzetjelentés megadása<ArrowRight size={18}/></Link></section>}
        <section className="card"><h2>Ezen a napon rögzítve · {items.length} tétel</h2>{items.map(tx=><div key={tx.id}>{row(tx)}</div>)}{!items.length&&<p className="muted">Még nincs rögzített pénzmozgás ezen a napon.</p>}</section>
        <section className="card"><form onSubmit={e=>{e.preventDefault();note.post('/napi-megjegyzes',{preserveScroll:true});}}><label>Napi megjegyzés<textarea value={note.data.note} onChange={e=>note.setData('note',e.target.value)} maxLength={2000}/></label><button className="button secondary" disabled={note.processing}><Check size={18}/>Megjegyzés mentése</button></form></section>
        {(p.insights.budgets.length>0||p.insights.changes.length>0)&&<Link className="text-link" href={`/?year=${p.year}&month=${p.month}`}>Havi figyelmeztetések megnyitása<ArrowRight size={18}/></Link>}
        <Link className="text-link" href={`/rendszeres?year=${p.year}&month=${p.month}`}>Rendszeres tételek ellenőrzése<ArrowRight size={18}/></Link></div>;
}

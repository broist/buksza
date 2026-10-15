import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Ft, months } from './components';
import type { CategoryReport, FinanceProps } from './types';

export type AmountChange = {transaction_id:number;template_id:number;name:string;due_date:string;record_date:string;actual:number;reference:number;reference_kind:'plan'|'previous';reference_date:string;difference:number};
export type InsightsData = {budgets:CategoryReport[];changes:AmountChange[]};

export function AmountChanges({items}:{items:AmountChange[]}) {
    return <>{items.map(item=><div className="insight-item" key={item.transaction_id}>
        <strong>{item.name}</strong><small>Fizetés: {item.record_date} · esedékesség: {item.due_date}</small>
        <div className="detail-line"><span>{item.reference_kind==='plan'?'Sablon szerinti összeg':'Korábbi fizetés összege'}<small>{item.reference_date}</small></span><Ft value={item.reference}/></div>
        <div className="detail-line"><span>Tényleges fizetés</span><Ft value={item.actual}/></div>
        <div className="detail-line"><span>{item.difference>0?'Növekedés':'Csökkenés'}</span><Ft value={Math.abs(item.difference)}/></div>
    </div>)}</>;
}

export default function InsightsPanel({p,budgets=true,changes=true}:{p:FinanceProps;budgets?:boolean;changes?:boolean}) {
    const [expanded,setExpanded]=useState(false);
    const rows=budgets?p.insights.budgets:[];
    const altered=changes?p.insights.changes:[];
    const count=rows.length+altered.length;
    return <section className="card insights-panel" aria-label="Havi figyelmeztetések">
        <div className="section-title"><h2>Havi figyelmeztetések</h2><Bell size={22}/></div>
        <p className="muted">{p.year}. {months[p.month-1]} · {count?`${count} figyelmeztetés`:'Jelenleg nincs figyelmeztetés.'}</p>
        {rows.length>0&&<><p className="muted">Közös családi keretek, az áthozott maradékkal együtt. A számla- és személyszűrés ezeket nem módosítja.</p>{rows.slice(0,expanded?undefined:3).map(category=><div className="insight-item" key={category.id} data-state={category.budgetState}>
            <strong>{category.name}</strong><p>{category.budgetState==='unplanned'?'Költés felhasználható keret nélkül':category.budgetState==='over'?'A felhasználható keret túllépve':category.budgetState==='reached'?'A felhasználható keret elfogyott':'A felhasználható keret legalább 80%-a elfogyott'}</p>
            <div className="detail-line"><span>Havi terv + áthozott maradék</span><Ft value={category.available}/></div>
            <div className="detail-line"><span>Elköltve</span><Ft value={category.spent}/></div>
            <div className="detail-line"><span>Maradék</span><Ft value={category.remaining} className={category.remaining<0?'negative':''}/></div>
        </div>)}</>}
        {altered.length>0&&<><h3>Rendszeres fizetések összegváltozása</h3><p className="muted">Az eltérés önmagában nem hiba. A sablon összege változatlan marad, amíg át nem írod.</p><AmountChanges items={expanded?altered:altered.slice(0,3)}/><Link className="text-link" href={`/rendszeres?year=${p.year}&month=${p.month}`}>Rendszeres tételek megnyitása</Link></>}
        {(rows.length>3||altered.length>3)&&<button className="button secondary" onClick={()=>setExpanded(!expanded)}>{expanded?'Kevesebb figyelmeztetés':'Összes figyelmeztetés'}</button>}
    </section>;
}

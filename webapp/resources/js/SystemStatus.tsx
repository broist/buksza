import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Activity, RefreshCw } from 'lucide-react';

type BackupRun = {status:'running'|'failed'|'succeeded';trigger:'manual'|'scheduled';started_at:string;finished_at:string|null;bytes:number|null};
export type OperationsStatus = {
    checked_at:string;
    scheduler:{state:'unknown'|'healthy'|'stale';last_seen_at:string|null};
    backup:{state:'unknown'|'healthy'|'stale'|'failed'|'running'|'stalled';scheduled_state:'unknown'|'healthy'|'stale';attempt:BackupRun|null;last_success:BackupRun|null;last_scheduled_success:BackupRun|null};
    deliveries:{id:number;device_name:string;kind:string;category_name:string|null;status:string;attempted_at:string|null;sent_at:string|null}[];
};

const date = (value:string|null|undefined) => value ? new Intl.DateTimeFormat('hu-HU',{timeZone:'Europe/Budapest',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(new Date(value)) : 'Még nincs adat';

export default function SystemStatus({status}:{status:OperationsStatus}) {
    const [refreshing,setRefreshing]=useState(false);
    const schedulerText={unknown:'Még nincs életjel',healthy:'Az ütemező működik',stale:'Az ütemező életjele nem friss'};
    const backupText={unknown:'A mentés állapota nem ellenőrizhető',healthy:'A legutóbbi mentés rendben',stale:'A legutóbbi mentés több mint 26 órás',failed:'Az utolsó mentési kísérlet sikertelen',running:'Mentés folyamatban',stalled:'A mentés 30 perce nem fejeződött be'};
    return <section className="card system-status" aria-labelledby="system-status-title">
        <div className="section-title"><h2 id="system-status-title">Működési állapot és mentések</h2><Activity size={22}/></div>
        <p className="muted">Ellenőrizve: {date(status.checked_at)}. Minden időpont Budapest szerint értendő.</p>
        <button className="button secondary" disabled={refreshing} onClick={()=>{setRefreshing(true);router.reload({only:['systemStatus'],onFinish:()=>setRefreshing(false)});}}><RefreshCw size={18}/>{refreshing?'Frissítés…':'Állapot frissítése'}</button>
        <div className="system-status-grid">
            <div className="system-status-item" data-state={status.scheduler.state}>
                <h3>Értesítések ütemezője</h3><strong>{schedulerText[status.scheduler.state]}</strong>
                <p>Utolsó életjel: <time>{date(status.scheduler.last_seen_at)}</time></p>
                <p className="muted">Percenként ellenőrzi az esedékes jelzéseket. Három percnél régebbi életjelnél ellenőrizni kell a szerver működését.</p>
            </div>
            <div className="system-status-item" data-state={status.backup.state}>
                <h3>Szerveres biztonsági mentés</h3><strong>{backupText[status.backup.state]}</strong>
                <p>Utolsó kísérlet: <time>{date(status.backup.attempt?.started_at)}</time></p>
                <p>Utolsó sikeres mentés: <time>{date(status.backup.last_success?.finished_at)}</time></p>
                {status.backup.last_success&&<p>Mentett adatbázis: {new Intl.NumberFormat('hu-HU').format(status.backup.last_success.bytes!)} bájt. A konfiguráció külön fájlokban kerül a mentésbe.</p>}
                <p>Napi automatikus futás: <time>{date(status.backup.last_scheduled_success?.finished_at)}</time></p>
                <p className={status.backup.scheduled_state==='healthy'?'muted':'notice'}>{status.backup.scheduled_state==='healthy'?'A napi automatikus mentés rendben.':status.backup.scheduled_state==='stale'?'Több mint 26 órája nincs sikeres napi automatikus mentés.':'Még nincs igazolt napi automatikus mentés.'}</p>
                <p className="muted">Naponta 03:00-kor. A kézi vagy telepítés közbeni mentés nem igazolja a napi automatikus futást. A mentések a szerveren maradnak; másik eszközre még külön másolat szükséges.</p>
                {status.backup.state==='unknown'&&<p className="notice">Még nincs olvasható szerveres mentési jelentés. Ez nem igazolja sem a mentés sikerét, sem a hiányát.</p>}
                {(status.backup.state==='failed'||status.backup.state==='stalled'||status.backup.state==='stale')&&<p className="notice">A szerver mentési szolgáltatását ellenőrizni kell. Az alábbi pénzügyi mentést most is letöltheted saját eszközödre.</p>}
            </div>
        </div>
        <h3>Értesítések · az utolsó 20 küldés</h3>
        <p className="muted">A „szolgáltató átvette” a küldés eredménye. A telefonon történő megjelenést külön ellenőrizd. A próbaértesítés eredménye feljebb, az eszköznél látható.</p>
        {status.deliveries.length===0?<p>Még nincs értesítésküldés rögzítve.</p>:<ul className="system-deliveries">{status.deliveries.map(delivery=><li key={delivery.id}>
            <strong>{delivery.kind.startsWith('budget:')?`Kategóriakeret elérése · ${delivery.category_name??'Kategória'}`:delivery.kind==='anna'?'Anna hónap végi jelzése':'Napi költésrögzítési jelzés'}</strong>
            <span>{delivery.device_name}</span>
            <span>{delivery.status==='sent'?'A szolgáltató átvette':delivery.status==='failed'?'Sikertelen küldés':'A küldés eredménye még nem ismert'}</span>
            <time>{date(delivery.attempted_at)}</time>
        </li>)}</ul>}
    </section>;
}

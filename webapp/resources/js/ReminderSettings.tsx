import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Bell, Check } from 'lucide-react';
import { ErrorText } from './components';
import { createTransactionId } from './transaction-id';
import type { PushConfig } from './types';

export default function ReminderSettings({p}:{p:PushConfig}) {
    const form=useForm({instant_budget_enabled:p.instant_budget_enabled,insights_enabled:p.insights_enabled,daily_enabled:p.daily_enabled,daily_time:p.daily_time,anna_enabled:p.anna_enabled,anna_time:p.anna_time});
    const [busy,setBusy]=useState(false),[message,setMessage]=useState('');
    const supported=window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const ios=/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
    const installed=matchMedia('(display-mode: standalone)').matches || Boolean((navigator as Navigator & {standalone?:boolean}).standalone);
    const heartbeat=p.heartbeat ? new Date(p.heartbeat.replace(' ','T')+'Z') : null;
    const schedulerAlive=heartbeat&&Date.now()-heartbeat.getTime()<180000;
    const register=async()=>{
        setBusy(true);setMessage('');
        try {
            if(await Notification.requestPermission()!=='granted') {setMessage('Az értesítések engedélye nincs megadva. A telefon beállításaiban engedélyezheted.');return;}
            const registration=await navigator.serviceWorker.ready;
            const raw=atob(p.public_key!.replace(/-/g,'+').replace(/_/g,'/'));
            const key=Uint8Array.from(raw,c=>c.charCodeAt(0));
            const subscription=await registration.pushManager.getSubscription() ?? await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
            let id=localStorage.getItem('buksza-device-id');if(!id){id=createTransactionId();localStorage.setItem('buksza-device-id',id);}
            router.post('/ertesitesek/eszkoz',{device_id:id,name:ios?'iPhone':'Böngésző',subscription:subscription.toJSON() as any},{preserveScroll:true,onError:()=>setMessage('A regisztráció nem sikerült. Ellenőrizd a kapcsolatot és a szerver beállításait.')});
        } catch {setMessage('A telefon nem tudott regisztrálni. Ellenőrizd az értesítési engedélyt, majd próbáld újra.');}
        finally {setBusy(false);}
    };
    return <section className="card reminder-settings"><div className="section-title"><h2>Telefonos emlékeztetők</h2><Bell size={22}/></div>
        <p className="muted">A napi jelzés az esti rögzítést nyitja meg. Anna jelzése a hónap utolsó napján esedékes; azonos időpontban együtt érkeznek. Minden időpont Budapest szerint értendő.</p>
        <form onSubmit={e=>{e.preventDefault();form.post('/ertesitesek',{preserveScroll:true});}}><div className="form-grid"><label className="checkbox-label"><input type="checkbox" checked={form.data.daily_enabled} onChange={e=>form.setData('daily_enabled',e.target.checked)}/>Napi költésrögzítési jelzés</label><label>Napi jelzés időpontja<input type="time" value={form.data.daily_time} onChange={e=>form.setData('daily_time',e.target.value)} required/><ErrorText error={form.errors.daily_time}/></label><label className="checkbox-label"><input type="checkbox" checked={form.data.anna_enabled} onChange={e=>form.setData('anna_enabled',e.target.checked)}/>Anna hónap végi egyenlegének jelzése</label><label>Hónap végi jelzés időpontja<input type="time" value={form.data.anna_time} onChange={e=>form.setData('anna_time',e.target.value)} required/><ErrorText error={form.errors.anna_time}/></label></div><label className="checkbox-label"><input type="checkbox" checked={form.data.insights_enabled} onChange={e=>form.setData('insights_enabled',e.target.checked)}/>Keretek és összegváltozások összefoglalója az esti jelzésben</label><p className="muted">A napi összefoglaló csak darabszámokat mutat. Kikapcsolt napi jelzésnél ez az összefoglaló sem érkezik.</p><label className="checkbox-label"><input type="checkbox" checked={form.data.instant_budget_enabled} onChange={e=>form.setData('instant_budget_enabled',e.target.checked)}/>Azonnali jelzés a kategóriakeret elérésekor</label><p className="muted">A jelenlegi hónapban rögzített vagy módosított költésnél, ha eléred vagy túlléped a keretet. Kategóriánként és telefononként havonta egy küldési kísérlet; a jelzés a kategória nevét tartalmazza, összeget nem. A 80%-os állapot a felületen és az esti összefoglalóban látszik.</p><button className="button secondary" disabled={form.processing}><Check size={18}/>Emlékeztetők mentése</button></form>
        {!p.ready&&<p className="notice">A telefonos értesítés a HTTPS-en működő szerver beállítása után kapcsolható be. A jelenlegi Wi-Fi-s HTTP-címről iPhone-ra nem küldhető jelzés.</p>}
        {ios&&!installed&&<p className="notice">iPhone-on: Safari → Megosztás → Hozzáadás a Főképernyőhöz. Ezután a Buksza ikonról nyisd meg az appot, és itt engedélyezd az értesítéseket. iOS 16.4 vagy újabb szükséges.</p>}
        {p.ready&&!supported&&(!ios||installed)&&<p className="notice">Ez a böngésző most nem támogatja a telefonos értesítéseket. HTTPS-en, támogatott böngészőben nyisd meg az appot.</p>}
        <button className="button primary" disabled={busy||!p.ready||!supported||(ios&&!installed)} onClick={register}><Bell size={18}/>{busy?'Regisztrálás…':'Ezen a telefonon engedélyezem'}</button>
        {message&&<p role="status" className="notice">{message}</p>}
        <p className="muted">Ütemező: {schedulerAlive?'működik':'jelenleg nem igazolt'}. A küldéshez az ütemezőnek a szerveren folyamatosan futnia kell.</p>
        {p.devices.map(d=><div className="reminder-device" key={d.id}><strong>{d.name}</strong><small>{d.enabled?'Bekapcsolva':'Kikapcsolva'} · {d.last_status==='sent'?'A szolgáltató átvette az utolsó jelzést':d.last_status==='failed'?'Az utolsó küldés sikertelen':'Még nem volt küldés'}</small>{d.enabled&&<div className="quick-actions"><button className="button secondary" onClick={()=>router.post(`/ertesitesek/eszkoz/${d.id}/proba`,{},{preserveScroll:true})}>Próbaértesítés</button><button className="button secondary" onClick={()=>router.delete(`/ertesitesek/eszkoz/${d.id}`,{preserveScroll:true})}>Kikapcsolás</button></div>}</div>)}
    </section>;
}

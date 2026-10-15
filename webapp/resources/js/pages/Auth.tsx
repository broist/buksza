import { useState } from 'react';
import { loginPasskey, passkeyError, passkeySupported } from '../passkeys';
import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Leaf, LockKeyhole } from 'lucide-react';
import { ErrorText } from '../components';

export default function Auth({ setup, setupLocked=false }: { setup: boolean; setupLocked?:boolean }) {
    const [passkeyBusy,setPasskeyBusy]=useState(false),[passkeyMessage,setPasskeyMessage]=useState('');
    const passkeyLogin=async()=>{setPasskeyBusy(true);setPasskeyMessage('');try{await loginPasskey();location.assign('/');}catch(error){setPasskeyMessage(passkeyError(error));}finally{setPasskeyBusy(false);}};
    const form = useForm({ name: 'Isti', email: '', password: '', password_confirmation: '' });
    return <div className="auth-page"><Head title={setup ? 'Saját kassza indítása' : 'Belépés'}/>
        <div className="auth-art"><div className="brand"><span className="brand-icon"><Leaf size={24}/></span>Buksza</div><span className="eyebrow">EGYÜTT, ÁTLÁTHATÓAN</span><h1>Több nyugalom.<br/>Tisztább pénzügyek.</h1><p>A mindennapi költésektől a közös terveitekig.<br/>A család pénzügyei egy helyen.</p><div className="art-ring ring-one"/><div className="art-ring ring-two"/></div>
        <main className="auth-card"><div className="auth-symbol"><LockKeyhole size={24}/></div><h2>{setupLocked?'Saját kassza indítása':setup ? 'Indítsuk el a saját kasszád.' : 'Jó újra itt látni.'}</h2><p className="muted">{setupLocked?'Az első belépés létrehozásához nyisd meg a telepítéskor kapott saját indítólinkedet.':setup ? 'Hozd létre a saját belépésed. A pénzügyi adataidat ezután te adod meg.' : 'Lépj be a családi pénzügyeidhez.'}</p>
        {!setupLocked&&<form onSubmit={e => { e.preventDefault(); form.post(setup ? '/elso-belepes' : '/belepes'); }}>
            {setup && <label>Hogyan szólítsunk?<input autoComplete="given-name" value={form.data.name} onChange={e => form.setData('name',e.target.value)} required/><ErrorText error={form.errors.name}/></label>}
            <label>E-mail-cím<input type="email" autoComplete="username" value={form.data.email} onChange={e => form.setData('email',e.target.value)} required/><ErrorText error={form.errors.email}/></label>
            <label>Jelszó<input aria-label="Jelszó" type="password" autoComplete={setup ? 'new-password' : 'current-password'} minLength={setup ? 12 : undefined} value={form.data.password} onChange={e => form.setData('password',e.target.value)} required/><ErrorText error={form.errors.password}/>{setup && <small>Legalább 12 karakter.</small>}</label>
            {setup && <label>Jelszó még egyszer<input type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation',e.target.value)} required/></label>}
            <button className="button primary full" disabled={form.processing}>{form.processing ? 'Egy pillanat…' : setup ? 'Saját kassza létrehozása' : 'Belépés'}<ArrowRight size={18}/></button>
        </form>}{!setup&&!setupLocked&&passkeySupported()&&<div className="p4-space"><button className="button secondary full" disabled={passkeyBusy} onClick={passkeyLogin}>{passkeyBusy?'Készülék ellenőrzése…':'Belépés passkeyjel'}</button><ErrorText error={passkeyMessage}/></div>}<p className="auth-footer">A belépésedet megjegyezzük ezen az eszközön.</p></main>
    </div>;
}

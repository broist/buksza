const fromBase64 = (value:string):ArrayBuffer => Uint8Array.from(atob(value.replace(/-/g,'+').replace(/_/g,'/')), c=>c.charCodeAt(0)).buffer;
const toBase64 = (value:ArrayBuffer) => btoa(Array.from(new Uint8Array(value), v=>String.fromCharCode(v)).join(''));
async function post(path:string,data:unknown) {
    const csrf=document.cookie.split('; ').find(c=>c.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
    const response=await fetch(path,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json',...(csrf?{'X-XSRF-TOKEN':decodeURIComponent(csrf)}:{})},body:JSON.stringify(data)});
    if(!response.ok){const result=await response.json().catch(()=>({}));throw new Error(result.errors?Object.values(result.errors).flat().join(' '):'A kérés nem sikerült. Frissítsd az oldalt, majd próbáld újra.');}
    return response.json();
}
export const passkeySupported=()=>window.isSecureContext&&Boolean(window.PublicKeyCredential&&navigator.credentials);
export async function registerPasskey(password:string,name:string) {
    const {publicKey:json}=await post('/passkey/register/opciok',{password});
    const options:PublicKeyCredentialCreationOptions={...json,challenge:fromBase64(json.challenge),user:{...json.user,id:fromBase64(json.user.id)},excludeCredentials:json.excludeCredentials?.map((c:{id:string})=>({...c,id:fromBase64(c.id)}))};
    const credential=await navigator.credentials.create({publicKey:options}) as PublicKeyCredential|null;
    if(!credential)throw new Error('Nem készült passkey.');
    const response=credential.response as AuthenticatorAttestationResponse;
    await post('/passkey/register',{name,rawId:toBase64(credential.rawId),clientDataJSON:toBase64(response.clientDataJSON),attestationObject:toBase64(response.attestationObject)});
}
export async function loginPasskey() {
    const {publicKey:json}=await post('/passkey/login/opciok',{});
    const options:PublicKeyCredentialRequestOptions={...json,challenge:fromBase64(json.challenge),allowCredentials:json.allowCredentials?.map((c:{id:string})=>({...c,id:fromBase64(c.id)}))};
    const credential=await navigator.credentials.get({publicKey:options}) as PublicKeyCredential|null;
    if(!credential)throw new Error('Nem választottál passkeyt.');
    const response=credential.response as AuthenticatorAssertionResponse;
    await post('/passkey/login',{rawId:toBase64(credential.rawId),clientDataJSON:toBase64(response.clientDataJSON),authenticatorData:toBase64(response.authenticatorData),signature:toBase64(response.signature),userHandle:response.userHandle?toBase64(response.userHandle):''});
}
export const passkeyError=(error:unknown)=>error instanceof DOMException&&['NotAllowedError','AbortError'].includes(error.name)?'A passkey-művelet megszakadt vagy lejárt. Újra próbálhatod; jelszóval is beléphetsz.':error instanceof Error?error.message:'A passkey-művelet nem sikerült.';

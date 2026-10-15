import {chromium} from '@playwright/test';
import {randomBytes} from 'node:crypto';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'../../..');
const dc=(...args)=>execFileSync('docker',['compose','-p','buksza-installer-qa','-f','docker/production/compose.yaml','-f','webapp/tests/deployment/compose.yaml',...args],{cwd:root,env:process.env,stdio:'pipe'});
const password='QA-'+randomBytes(16).toString('hex');
// Disposable localhost CA only; actual deployment requires a trusted public certificate.
const browser=await chromium.launch({args:['--ignore-certificate-errors']});
const errors=[];
let diagnosticPage;
try {
    const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width:360,height:800}});
    const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
    diagnosticPage=page;
    await page.goto('https://localhost:18443/belepes');
    await page.getByText('Az első belépés létrehozásához',{exact:false}).waitFor();
    assert.equal(await page.locator('form').count(),0);
    const cookies=await context.cookies();
    const xsrf=decodeURIComponent(cookies.find(c=>c.name==='XSRF-TOKEN').value);
    const denied=await context.request.post('https://localhost:18443/elso-belepes',{headers:{'X-XSRF-TOKEN':xsrf},form:{name:'QA',email:'installer-qa@example.test',password,password_confirmation:password}});
    assert.equal(denied.status(),403);
    await page.goto('https://localhost:18443/belepes?setup=installer-test-token');
    assert.equal(page.url(),'https://localhost:18443/belepes');
    await page.getByLabel('Hogyan szólítsunk?').fill('Telepítő QA');
    await page.getByLabel('E-mail-cím').fill('installer-qa@example.test');
    await page.getByLabel('Jelszó',{exact:true}).fill(password);
    await page.getByLabel('Jelszó még egyszer').fill(password);
    await page.getByRole('button',{name:'Saját kassza létrehozása',exact:true}).click();
    await page.getByRole('heading',{name:'Szia, Telepítő QA!'}).waitFor();
    assert((await context.cookies()).find(c=>c.name==='buksza_installer_qa').secure);
    await page.getByRole('button',{name:'Új tétel',exact:true}).click();
    await page.getByLabel('Összeg',{exact:true}).fill('1234');
    await page.getByRole('button',{name:'élelmiszer',exact:true}).click();
    await page.getByRole('button',{name:'Tétel mentése',exact:true}).click();
    await page.getByRole('status').filter({hasText:'Tétel elmentve.'}).waitFor();
    const reg=await page.evaluate(async()=>{await Promise.race([navigator.serviceWorker.ready,new Promise((_,reject)=>setTimeout(()=>reject(new Error('Service worker registration timed out')),10000))]);return Boolean(await navigator.serviceWorker.getRegistration());});
    assert.equal(reg,true);
    const keysBefore=dc('exec','-T','app','php','artisan','buksza:push-keys').toString();assert(keysBefore.includes('már létezik'));
    dc('up','-d','--force-recreate','app','web','scheduler');
    // Wait for the recreated FPM/backend without assuming a startup duration.
    for(let i=0;i<30;i++){const r=await context.request.get('https://localhost:18443/up');if(r.ok())break;await new Promise(resolve=>setTimeout(resolve,1000));}
    await page.reload();await page.getByRole('heading',{name:'Szia, Telepítő QA!'}).waitFor();
    await page.goto('https://localhost:18443/esti-rogzites');
    assert.equal(await page.locator('.transaction-row').count(),1);
    assert.match(await page.locator('.transaction-row').innerText(),/1 234/);
    assert.deepEqual(errors,[]);
    const manifest=await (await context.request.get('https://localhost:18443/manifest.webmanifest')).json();assert.equal(manifest.name,'Buksza');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),360);
    for(const route of ['/zsebpenzek','/befektetesek','/eves']){
        await page.goto('https://localhost:18443'+route);
        await page.locator('h1').waitFor();
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),360);
    }
    const exported=await context.request.get('https://localhost:18443/export?year=2026&format=xlsx');
    assert.equal(exported.status(),200);assert.equal((await exported.body()).subarray(0,2).toString(),'PK');
    const backup=await context.request.get('https://localhost:18443/mentes');
    assert.equal(backup.status(),200);assert(!((await backup.text()).includes('installer-qa@example.test')));
    const webauthn=await context.newCDPSession(page);await webauthn.send('WebAuthn.enable');
    await webauthn.send('WebAuthn.addVirtualAuthenticator',{options:{protocol:'ctap2',transport:'internal',hasResidentKey:true,hasUserVerification:true,isUserVerified:true,automaticPresenceSimulation:true}});
    await page.goto('https://localhost:18443/beallitasok');
    const operations=page.getByRole('region',{name:'Működési állapot és mentések'});
    await operations.getByText('A legutóbbi mentés rendben',{exact:true}).waitFor();
    assert.match(await operations.innerText(),/Napi automatikus futás:\s*Még nincs adat/);
    dc('exec','-T','app','php','-r','exit(is_writable("/run/buksza-status") ? 1 : 0);');
    await page.getByLabel('Passkey neve',{exact:true}).fill('Production QA passkey');
    await page.getByLabel('Jelenlegi jelszó a passkey kezeléséhez',{exact:true}).fill(password);
    await page.getByRole('button',{name:'Új passkey hozzáadása',exact:true}).click();
    await page.locator('.p4-month').filter({hasText:'Production QA passkey'}).waitFor();
    await page.getByRole('button',{name:'Továbbiak',exact:true}).click();
    await page.locator('dialog').getByRole('button',{name:'Kilépés',exact:true}).click();
    await page.getByRole('button',{name:'Belépés passkeyjel',exact:true}).click();
    await page.getByRole('heading',{name:'Szia, Telepítő QA!'}).waitFor();
    await page.screenshot({path:resolve(root,'docs/screenshots/installer-qa-360.png'),fullPage:true});
    console.log('Production QA: HTTPS/FPM, protected setup, actual entry, restart persistence, push key, PWA, 360px, phase four pages, XLSX/encrypted backup and real passkey registration/login passed.');
} catch(error) {
    if(diagnosticPage) {
        await diagnosticPage.screenshot({path:resolve(root,'docs/screenshots/installer-qa-failure.png'),fullPage:true}).catch(()=>{});
        console.error('Production QA failure:',diagnosticPage.url(),errors);
    }
    throw error;
} finally {await browser.close();}

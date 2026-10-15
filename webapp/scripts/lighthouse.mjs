import { chromium } from '@playwright/test';
import lighthouse from 'lighthouse';
import fs from 'node:fs';
import path from 'node:path';

const browser=await chromium.launch({args:['--remote-debugging-port=19227']});
const reportName=process.argv[2]??'lighthouse-mobile';
if(!/^[a-z0-9-]+$/.test(reportName)) throw new Error('Invalid report name');
try {
    const context=await browser.newContext();
    const page=await context.newPage();await page.goto('http://localhost:18081/belepes');
    await page.getByLabel('E-mail-cím').fill('qa@example.test');await page.getByLabel('Jelszó',{exact:true}).fill('only-for-qa-2026');
    await page.getByRole('button',{name:'Belépés',exact:true}).click();await page.waitForURL('http://localhost:18081/');
    const cookies=await context.cookies();
    const result=await lighthouse('http://localhost:18081/',{port:19227,output:['html','json'],logLevel:'error',onlyCategories:['performance','accessibility'],disableStorageReset:true,extraHeaders:{Cookie:cookies.map(c=>`${c.name}=${c.value}`).join('; ')}});
    const out=path.resolve('..','docs','checks');fs.mkdirSync(out,{recursive:true});
    fs.writeFileSync(path.join(out,`${reportName}.html`),result.report[0]);
    fs.writeFileSync(path.join(out,`${reportName}.json`),result.report[1]);
    console.log(JSON.stringify({performance:result.lhr.categories.performance.score*100,accessibility:result.lhr.categories.accessibility.score*100,metrics:Object.fromEntries(['first-contentful-paint','largest-contentful-paint','total-blocking-time','cumulative-layout-shift'].map(k=>[k,result.lhr.audits[k].displayValue]))}));
} finally {await browser.close();}

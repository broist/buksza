import { chromium } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const svg=await readFile(new URL('../public/icons/buksza.svg',import.meta.url),'utf8');
const browser=await chromium.launch({headless:true});
try {
    for(const [size,name] of [[180,'apple-touch-icon'],[192,'buksza-192'],[512,'buksza-512']]) {
        const page=await browser.newPage({viewport:{width:size,height:size},deviceScaleFactor:1});
        await page.setContent(`<style>html,body{margin:0;background:#214d3f}svg{display:block;width:100vw;height:100vh}</style>${svg}`);
        await page.screenshot({path:fileURLToPath(new URL(`../public/icons/${name}.png`,import.meta.url)),type:'png'});
        await page.close();
    }
} finally {await browser.close();}

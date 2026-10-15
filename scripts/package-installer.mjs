import { readdir, readFile, writeFile, mkdir, mkdtemp, copyFile, lstat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const stage=await mkdtemp(join(tmpdir(),'buksza-package-'));
const files=[];
const directories=['webapp/app','webapp/config','webapp/lang','webapp/resources','webapp/routes','webapp/database/migrations','docker/production'];
const singles=['.dockerignore','webapp/artisan','webapp/bootstrap/app.php','webapp/bootstrap/providers.php','webapp/database/seeders/DatabaseSeeder.php','webapp/composer.json','webapp/composer.lock','webapp/package.json','webapp/package-lock.json','webapp/tsconfig.json','webapp/vite.config.js','webapp/public/index.php','webapp/public/robots.txt','webapp/public/sw.js','webapp/public/manifest.webmanifest','scripts/server-backup.sh','scripts/server-status.sh'];
async function collect(relative){
    const stat=await lstat(join(root,relative));
    if(stat.isSymbolicLink())throw new Error(`Symlinks are not packaged: ${relative}`);
    if(stat.isDirectory())for(const entry of (await readdir(join(root,relative))).sort())await collect(`${relative}/${entry}`);
    else files.push(relative);
}
for(const dir of [...directories,'webapp/public/icons'])await collect(dir);
files.push(...singles);
for(const relative of files.sort()){
    if(/(?:^|\/)(?:\.env|node_modules|vendor|storage|tests|QaSeeder\.php)(?:\/|$)|\.sqlite/.test(relative))throw new Error(`Forbidden payload path: ${relative}`);
    const target=join(stage,relative);
    await mkdir(dirname(target),{recursive:true});
    if(relative.endsWith('.sh'))await writeFile(target,(await readFile(join(root,relative),'utf8')).replace(/\r\n/g,'\n'));
    else await copyFile(join(root,relative),target);
}
const archive=join(stage,'payload.tar.gz');
execFileSync('tar',['-czf',archive,'-C',stage,...files],{stdio:'pipe'});
const payload=await readFile(archive);
const digest=createHash('sha256').update(payload).digest('hex');
const template=(await readFile(join(root,'scripts/install-ubuntu.sh'),'utf8')).replace(/\r\n/g,'\n').replace('@PAYLOAD_SHA256@',digest);
if(!template.includes('# __BUKSZA_PAYLOAD__\n'))throw new Error('Payload marker missing');
const installer=template+payload.toString('base64').match(/.{1,76}/g).join('\n')+'\n';
const out=join(root,'dist');
await mkdir(out,{recursive:true});
await writeFile(join(out,'buksza-telepito.sh'),installer,{mode:0o755});
await writeFile(join(out,'buksza-telepito.sha256'),createHash('sha256').update(installer).digest('hex')+'  buksza-telepito.sh\n');
await writeFile(join(out,'buksza-package-files.json'),JSON.stringify({payloadSha256:digest,files},null,2)+'\n');
console.log(`Telepítő: ${join(out,'buksza-telepito.sh')} (${Buffer.byteLength(installer)} bájt, ${files.length} forrásfájl). Saját adatbázis, .env, titkosítási kulcs és jelszó nincs a csomagban.`);
// Keep the staging directory for inspection; only application source is copied there.

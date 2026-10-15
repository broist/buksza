import { spawn } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const completed = (child) => new Promise((resolve, reject) => {
    child.once('error', reject);
    child.once('exit', (code) => code === 0 ? resolve() : reject(new Error(`Command exited with ${code}`)));
});

// Extract the actual installer in Linux under its restrictive umask, then stream
// the context directly to Docker. A Windows round trip would lose Unix modes.
for (const target of ['app', 'web']) {
    const context = spawn('docker', [
        'run', '--rm', '-v', `${root}:/work:ro`, 'composer:2', 'sh', '-c',
        'apk add --no-cache bash coreutils >/dev/null && bash /work/dist/buksza-telepito.sh --extract /tmp/source >&2 && chmod 0700 /tmp/source/webapp/bootstrap && chmod 0600 /tmp/source/webapp/bootstrap/app.php && tar -czf - -C /tmp/source .',
    ], { cwd: root, stdio: ['ignore', 'pipe', 'inherit'] });
    const build = spawn('docker', [
        'build', '--target', target, '-f', 'docker/production/Dockerfile',
        '-t', `buksza-${target}:installer-qa`, '-',
    ], { cwd: root, stdio: ['pipe', 'inherit', 'inherit'] });
    context.stdout.pipe(build.stdin);
    await Promise.all([completed(context), completed(build)]);
}

const check = spawn('docker', [
    'run', '--rm', 'buksza-app:installer-qa', 'sh', '-c',
    'test "$(id -un)" = www-data && test -r /app/bootstrap/app.php && test ! -w /app/bootstrap/app.php && php artisan --version',
], { cwd: root, stdio: 'inherit' });
await completed(check);
console.log('Packaged Linux installer images: restrictive source permissions normalized; www-data can boot Laravel and cannot write application source.');

import { execFileSync, spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

if (process.env.SIHATECH_E2E !== '1' || process.env.APP_ENV !== 'testing') throw new Error('Run through npm run test:e2e.');
const backend = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../backend');
const phpArgs = execFileSync('php', ['-r', 'echo extension_loaded("pdo_sqlite") ? "yes" : "no";'], { encoding: 'utf8' }).trim() === 'yes'
    ? [] : ['-d', 'extension=pdo_sqlite'];
const child = spawn('php', [...phpArgs, '-S', 'localhost:8310', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],
    { cwd: path.join(backend, 'public'), env: process.env, stdio: 'inherit' });
child.on('error', error => { console.error(error.message); process.exitCode = 1; });
child.on('exit', code => { process.exit(code ?? 1); });
process.on('exit', () => child.kill());
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => { child.kill(); process.exit(); });

import { execFileSync, spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, mkdtemp, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const frontend = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const backend = path.resolve(frontend, '../backend');
const root = path.join(backend, 'storage/app/e2e-runs');
await mkdir(root, { recursive: true });
const run = await mkdtemp(path.join(root, 'run-'));
const database = path.join(run, 'database.sqlite');
await writeFile(database, '', { flag: 'wx' });
const documentsRoot = path.join(run, 'private');
await mkdir(documentsRoot);
const env = {
    ...process.env,
    SIHATECH_E2E: '1', SIHATECH_E2E_RUN_DIR: run,
    APP_ENV: 'testing', APP_DEBUG: 'false', APP_TIMEZONE: 'UTC', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_URL: 'http://localhost:8310', FRONTEND_URL: 'http://localhost:4310',
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
    DOCUMENTS_ROOT: documentsRoot,
    SESSION_DRIVER: 'file', SESSION_COOKIE: `e2e_${path.basename(run)}`, SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
    SANCTUM_STATEFUL_DOMAINS: 'localhost:4310,localhost:8310',
    CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4',
    MAIL_MAILER: 'smtp', MAIL_URL: '', MAIL_HOST: '127.0.0.1', MAIL_PORT: '8265', MAIL_ENCRYPTION: '',
    MAIL_USERNAME: '', MAIL_PASSWORD: '', MAIL_FROM_ADDRESS: 'notifications@e2e.test', MAIL_FROM_NAME: 'SIHATECH E2E',
    VITE_API_BASE_URL: 'http://localhost:8310',
};
const phpArgs = execFileSync('php', ['-r', 'echo extension_loaded("pdo_sqlite") ? "yes" : "no";'], { encoding: 'utf8' }).trim() === 'yes'
    ? [] : ['-d', 'extension=pdo_sqlite'];
execFileSync('php', [...phpArgs, 'tests/Browser/seed.php'], { cwd: backend, env, stdio: 'inherit' });
const child = spawn(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', ...process.argv.slice(2)], { cwd: frontend, env, stdio: 'inherit' });
child.on('error', error => { console.error(error.message); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code ?? 1; });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => child.kill(signal));

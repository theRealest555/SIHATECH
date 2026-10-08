import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, lstatSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const frontend = join(repo, 'frontend');
const output = join(repo, '..', '.local-tools', `free-hosting-${Date.now()}`);
if (existsSync(output)) throw new Error('Output already exists; refusing to overwrite.');

// The fixed build command contains no user input. API requests use this origin.
const options = { cwd: frontend, stdio: 'inherit', env: { ...process.env, VITE_API_BASE_URL: '/' } };
if (process.platform === 'win32') {
    execFileSync('cmd.exe', ['/d', '/s', '/c', 'npm.cmd run build -- --base=/frontend/ --outDir=dist-free-hosting'], options);
} else {
    execFileSync('npm', ['run', 'build', '--', '--base=/frontend/', '--outDir=dist-free-hosting'], options);
}

const files = execFileSync('git', ['ls-files', '-z', 'backend'], { cwd: repo, encoding: 'utf8' }).split('\0').filter(Boolean);
mkdirSync(output, { recursive: true });
let count = 0;
for (const file of files) {
    if (/^backend\/(?:tests|node_modules|vendor)\//.test(file)) continue;
    if (/^backend\/(?:phpunit\.xml|package(?:-lock)?\.json|vite\.config\.js)$/.test(file)) continue;
    if (/(^|\/)\.env(?:\.|$)/.test(file) && file !== 'backend/.env.example') continue;
    if (file.startsWith('backend/storage/') && !file.endsWith('/.gitignore')) continue;
    if (file.startsWith('backend/bootstrap/cache/') && !file.endsWith('/.gitignore')) continue;
    const source = join(repo, file);
    if (lstatSync(source).isSymbolicLink()) throw new Error('Refusing to package a symbolic link.');
    const target = join(output, file);
    mkdirSync(dirname(target), { recursive: true });
    cpSync(source, target);
    count++;
}
cpSync(join(frontend, 'dist-free-hosting'), join(output, 'backend/public/frontend'), { recursive: true });
cpSync(join(repo, 'deployment/apache-testing.htaccess'), join(output, 'backend/public/.htaccess'));
cpSync(join(repo, 'deployment/backend.env.example'), join(output, 'backend.env.example'));
cpSync(join(repo, 'deployment/free-hosting.md'), join(output, 'SETUP.md'));
const revision = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: repo, encoding: 'utf8' }).trim();
writeFileSync(join(output, 'BUILD.json'), JSON.stringify({ revision, builtAt: new Date().toISOString(), backendFiles: count, apiBase: '/', assetBase: '/frontend/', credentialsIncluded: false, dependenciesIncluded: false }, null, 2));
const index = readFileSync(join(output, 'backend/public/frontend/index.html'), 'utf8');
if (!index.includes('/frontend/assets/')) throw new Error('Unexpected asset paths in the built frontend.');
console.log(`Prepared testing deployment: ${output}`);
console.log('No provider requests or deployment performed. Install production Composer dependencies on the host.');

/**
 * Vyomika Atelier LLP — full local preview (all Laravel routes).
 * Usage: node scripts/local-preview.js
 *
 * Uses .env.preview (SQLite) — never overwrites production .env.
 * Falls back to static multi-page preview if composer/vendor is missing.
 */
const { spawn, spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

const ROOT = path.join(__dirname, '..');
const PORT = Number(process.env.PORT) || 8765;
const URL = `http://127.0.0.1:${PORT}/`;
const SQLITE = path.join(ROOT, 'database', 'database.sqlite');
const PREVIEW_ENV = path.join(ROOT, '.env.preview');
const ENV_TEMPLATE = path.join(ROOT, 'env.preview');
const ARTISAN_ENV = ['--env=preview'];

function run(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, {
    cwd: ROOT,
    stdio: 'inherit',
    shell: process.platform === 'win32',
    ...opts,
  });
  return r.status === 0;
}

function hasPhp() {
  return run('php', ['-v'], { stdio: 'pipe' });
}

function ensurePreviewEnvFile() {
  if (!fs.existsSync(PREVIEW_ENV) && fs.existsSync(ENV_TEMPLATE)) {
    fs.copyFileSync(ENV_TEMPLATE, PREVIEW_ENV);
    console.log('  Created .env.preview from env.preview (SQLite, preview bar on)');
  }
}

function envNeedsKey() {
  if (!fs.existsSync(PREVIEW_ENV)) return true;
  const content = fs.readFileSync(PREVIEW_ENV, 'utf8');
  const m = content.match(/^APP_KEY=(.*)$/m);
  return !m || !m[1] || m[1].trim() === '';
}

function composerInstall() {
  if (fs.existsSync(path.join(ROOT, 'vendor', 'autoload.php'))) {
    return true;
  }
  const phar = path.join(ROOT, 'composer.phar');
  const cacert = path.join(ROOT, 'cacert.pem');
  const env = { ...process.env };
  if (fs.existsSync(cacert)) {
    env.SSL_CERT_FILE = cacert;
    env.CURL_CA_BUNDLE = cacert;
  }
  if (fs.existsSync(phar)) {
    return run('php', [phar, 'install', '--no-interaction', '--prefer-dist'], { env });
  }
  if (spawnSync('composer', ['--version'], { cwd: ROOT, stdio: 'pipe', shell: true }).status === 0) {
    return run('composer', ['install', '--no-interaction'], { env });
  }
  return false;
}

function ensureSetup() {
  ensurePreviewEnvFile();

  if (!composerInstall()) {
    throw new Error('composer install required');
  }

  if (!fs.existsSync(SQLITE)) {
    fs.mkdirSync(path.dirname(SQLITE), { recursive: true });
    fs.writeFileSync(SQLITE, '');
    console.log('  Created database/database.sqlite');
  }

  if (envNeedsKey()) {
    run('php', ['artisan', 'key:generate', '--force', ...ARTISAN_ENV]);
  }

  console.log('  Running migrations & seed…');
  if (!run('php', ['artisan', 'migrate', '--seed', '--force', ...ARTISAN_ENV])) {
    throw new Error('migrate --seed failed');
  }
}

function openBrowser() {
  const cmd = process.platform === 'win32' ? 'start' : process.platform === 'darwin' ? 'open' : 'xdg-open';
  spawn(cmd, process.platform === 'win32' ? ['', URL] : [URL], { shell: true, stdio: 'ignore' }).unref();
}

function startStaticFallback(reason) {
  if (reason) {
    console.log('');
    console.log(`  ${reason}`);
  }
  console.log('');
  console.log('  Starting static multi-page preview (shop, cart, checkout, blog, contact).');
  console.log(`  Open: ${URL}`);
  console.log('  For full Laravel (admin, real cart, forms): fix Composer SSL, then run composer install');
  console.log('  Press Ctrl+C to stop');
  console.log('');
  const child = spawn('node', ['serve-preview.js'], {
    cwd: path.join(ROOT, 'public'),
    stdio: 'inherit',
    shell: true,
  });
  openBrowser();
  child.on('exit', (code) => process.exit(code ?? 0));
}

async function main() {
  console.log('');
  console.log('  Vyomika Atelier LLP — local preview setup');
  console.log('');

  if (!hasPhp()) {
    startStaticFallback('PHP not found — using Node static preview.');
    return;
  }

  try {
    ensureSetup();
  } catch (e) {
    startStaticFallback('Laravel setup unavailable: ' + e.message);
    return;
  }

  console.log('');
  console.log(`  Full site: ${URL}`);
  console.log('  Pages: /shop, /cart, /checkout, /services, /projects, /blog, /contact, /about');
  console.log('  Admin: /admin/login  (admin@vyomikaatelier.com — set ADMIN_PASSWORD in .env)');
  console.log('  Press Ctrl+C to stop');
  console.log('');

  const server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${PORT}`, ...ARTISAN_ENV], {
    cwd: ROOT,
    stdio: 'inherit',
    shell: process.platform === 'win32',
  });

  openBrowser();

  server.on('exit', (code) => process.exit(code ?? 0));
  process.on('SIGINT', () => server.kill('SIGINT'));
  process.on('SIGTERM', () => server.kill('SIGTERM'));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});

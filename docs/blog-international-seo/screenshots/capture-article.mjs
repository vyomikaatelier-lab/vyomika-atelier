import { chromium } from 'playwright-core';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = __dirname;
const slug = process.argv[2] || 'pvd-coating-explained';
const url = `http://127.0.0.1:8000/blog/${slug}`;

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
await page.goto(url, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);

const outPath = path.join(outDir, `article-mockup-match-${slug}-1440.png`);
await page.screenshot({ path: outPath, fullPage: true });
console.log('Saved:', outPath);

await browser.close();

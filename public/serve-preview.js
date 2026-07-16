/**
 * Minimal static server for public/preview.html (no dependencies).
 * Usage: node serve-preview.js
 */
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = Number(process.env.PORT) || 8765;
const ROOT = __dirname;

const MIME = {
    '.html': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.js': 'application/javascript; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.webp': 'image/webp',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ico': 'image/x-icon',
};

const server = http.createServer((req, res) => {
    let urlPath = decodeURIComponent((req.url || '/').split('?')[0]);
    if (urlPath === '/') urlPath = '/preview.html';

    const filePath = path.normalize(path.join(ROOT, urlPath));
    if (!filePath.startsWith(ROOT)) {
        res.writeHead(403);
        res.end('Forbidden');
        return;
    }

    fs.readFile(filePath, (err, data) => {
        if (err) {
            if (err.code === 'ENOENT' && !path.extname(urlPath)) {
                fs.readFile(path.join(ROOT, 'preview.html'), (err2, html) => {
                    if (err2) {
                        res.writeHead(500);
                        res.end('Preview shell missing');
                        return;
                    }
                    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                    res.end(html);
                });
                return;
            }
            res.writeHead(err.code === 'ENOENT' ? 404 : 500);
            res.end(err.code === 'ENOENT' ? 'Not found' : 'Server error');
            return;
        }
        const ext = path.extname(filePath).toLowerCase();
        res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
        res.end(data);
    });
});

server.listen(PORT, '127.0.0.1', () => {
    const url = `http://127.0.0.1:${PORT}/`;
    console.log('');
    console.log(`  Vyomika Atelier LLP static preview: ${url}`);
    console.log('  Routes: /, /shop, /services/partitions, /cart, /contact');
    console.log('  Press Ctrl+C to stop');
    console.log('');
}).on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
        console.log('');
        console.log(`  Preview already running at http://127.0.0.1:${PORT}/`);
        console.log('  Open that URL in your browser.');
        console.log('');
        process.exit(0);
    }
    throw err;
});

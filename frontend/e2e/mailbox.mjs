import net from 'node:net';
import http from 'node:http';
import path from 'node:path';
import { mkdir, writeFile, rename } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';

if (process.env.SIHATECH_E2E !== '1' || process.env.APP_ENV !== 'testing') throw new Error('Use the isolated E2E runner.');
const directory = path.join(process.env.SIHATECH_E2E_RUN_DIR, 'mail');
await mkdir(directory, { recursive: true });
const smtp = net.createServer(socket => {
    let buffer = '', data = false, lines = [], recipients = [];
    socket.setTimeout(30000, () => socket.destroy());
    socket.on('error', () => socket.destroy());
    socket.write('220 localhost SIHATECH test inbox\r\n');
    socket.on('data', async chunk => {
        buffer += chunk.toString();
        while (buffer.includes('\r\n')) {
            const end = buffer.indexOf('\r\n');
            const line = buffer.slice(0, end); buffer = buffer.slice(end + 2);
            if (data) {
                if (line !== '.') { lines.push(line.replace(/^\.\./, '.')); continue; }
                const file = path.join(directory, `${randomUUID()}.json`);
                try {
                    await writeFile(`${file}.tmp`, JSON.stringify({ recipients, text: lines.join('\r\n') }));
                    await rename(`${file}.tmp`, file);
                    socket.write('250 Message saved locally\r\n');
                } catch { socket.write('451 Local inbox unavailable\r\n'); }
                data = false; lines = []; recipients = [];
            } else if (/^(EHLO|HELO) /i.test(line)) socket.write('250 localhost\r\n');
            else if (/^MAIL FROM:/i.test(line)) { recipients = []; socket.write('250 OK\r\n'); }
            else if (/^RCPT TO:/i.test(line)) {
                const recipient = line.match(/<([^>]+)>/)?.[1]?.toLowerCase();
                if (!recipient?.endsWith('.test')) socket.write('550 Synthetic .test recipients only\r\n');
                else { recipients.push(recipient); socket.write('250 OK\r\n'); }
            } else if (/^DATA$/i.test(line) && recipients.length) { data = true; socket.write('354 End with a dot\r\n'); }
            else if (/^QUIT$/i.test(line)) socket.end('221 Bye\r\n');
            else if (/^(RSET|NOOP)$/i.test(line)) { recipients = []; socket.write('250 OK\r\n'); }
            else socket.write('502 Unsupported test-inbox command\r\n');
        }
    });
});
smtp.listen(8265, '127.0.0.1', () => {
    http.createServer((request, response) => { response.writeHead(request.url === '/health' ? 200 : 404); response.end('Test inbox'); }).listen(8266, '127.0.0.1');
});

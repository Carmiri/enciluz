import puppeteer from 'puppeteer-core';
import { readFileSync } from 'node:fs';
const creds = readFileSync(new URL('../../CREDENCIALES-LOCAL.txt', import.meta.url), 'utf8');
const pass = creds.match(/cliente \/ (\S+)/)[1];
const B = 'http://localhost:8088';
const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.goto(`${B}/acceso-enciluz/`, { waitUntil: 'networkidle2' });
await page.type('#user_login', 'cliente');
await page.type('#user_pass', pass);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#wp-submit')]);
const ids = JSON.parse(process.argv[2]);
let bad = 0;
for (const [slug, id] of Object.entries(ids)) {
  await page.goto(`${B}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'networkidle2', timeout: 90000 });
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor')?.getBlocks()?.length > 0, { timeout: 60000 });
  const res = await page.evaluate(() => {
    const out = [];
    const walk = (bs) => bs.forEach(b => { if (!b.isValid) out.push(b.name + ': ' + (b.validationIssues?.[0]?.args?.slice(0,3).join(' | ') || '').slice(0, 300)); walk(b.innerBlocks); });
    walk(wp.data.select('core/block-editor').getBlocks());
    return { total: wp.data.select('core/block-editor').getClientIdsWithDescendants().length, invalid: out };
  });
  bad += res.invalid.length;
  console.log(`${slug}: ${res.total} bloques, ${res.invalid.length} no válidos`);
  res.invalid.slice(0, 4).forEach(x => console.log('   ', x));
}
await browser.close();
process.exit(bad ? 1 : 0);

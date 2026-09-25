import puppeteer from 'puppeteer-core';
const B = 'http://localhost:8088';
const pages = ['/', '/quienes-somos/', '/que-hacemos/', '/transparencia/', '/contacto/', '/creditos/', '/no-existe/'];
const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
let problems = 0;
for (const w of [360, 390, 768]) {
  const page = await browser.newPage();
  await page.setViewport({ width: w, height: 800, isMobile: w < 700, hasTouch: w < 700, deviceScaleFactor: 2 });
  for (const p of pages) {
    await page.goto(B + p, { waitUntil: 'networkidle0' });
    const r = await page.evaluate(() => {
      const vw = document.documentElement.clientWidth, out = { overflow: [], small: [], taps: [] };
      if (document.documentElement.scrollWidth > vw + 1) out.overflow.push('scrollWidth ' + document.documentElement.scrollWidth + ' > ' + vw);
      for (const el of document.querySelectorAll('body *')) {
        const b = el.getBoundingClientRect();
        if (b.width && b.right > vw + 1 && getComputedStyle(el).position !== 'absolute' && !el.closest('.enciluz-hp,.screen-reader-text,.wp-block-navigation__responsive-container:not(.is-menu-open)')) out.overflow.push(el.tagName + '.' + [...el.classList].slice(0,2).join('.') + ' right=' + Math.round(b.right));
        if (el.childNodes.length && [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim()) && b.width) {
          const fs = parseFloat(getComputedStyle(el).fontSize);
          if (fs < 14 && !el.closest('.screen-reader-text,.enciluz-hp')) out.small.push(el.tagName + ' ' + fs + 'px «' + el.textContent.trim().slice(0, 30) + '»');
        }
      }
      for (const el of document.querySelectorAll('a, button, input, select, textarea')) {
        const b = el.getBoundingClientRect();
        if (!b.width || el.closest('.enciluz-hp,.screen-reader-text,.skip-link') || el.classList.contains('skip-link')) continue;
        const inline = getComputedStyle(el).display === 'inline' && el.closest('p') && el.closest('p').textContent.trim().length > el.textContent.trim().length + 3;
        if (!inline && (b.height < 24 || b.width < 24)) out.taps.push(el.tagName + ' «' + (el.textContent.trim() || el.getAttribute('aria-label') || '').slice(0, 25) + '» ' + Math.round(b.width) + 'x' + Math.round(b.height));
      }
      return out;
    });
    const n = r.overflow.length + r.small.length + r.taps.length;
    problems += n;
    if (n) console.log(`${w}px ${p}:`, JSON.stringify({ overflow: r.overflow.slice(0,4), small: r.small.slice(0,4), taps: r.taps.slice(0,6) }));
  }
  if (w < 700) { // menú móvil
    await page.goto(B + '/', { waitUntil: 'networkidle0' });
    await page.click('.wp-block-navigation__responsive-container-open');
    await new Promise(r => setTimeout(r, 400));
    const open = await page.evaluate(() => { const c = document.querySelector('.wp-block-navigation__responsive-container'); return c.classList.contains('is-menu-open') && [...c.querySelectorAll('a')].filter(a => a.getBoundingClientRect().height > 0).map(a => a.textContent.trim()); });
    console.log(`${w}px menú móvil abre:`, open);
    
    await page.keyboard.press('Escape');
  }
  await page.close();
}
await browser.close();
console.log('problemas:', problems);
process.exit(problems ? 1 : 0);

const fs = require('fs');
const puppeteer = require('puppeteer-core');

function findChromeExecutable() {
    const candidatePaths = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
    ];
    for (const p of candidatePaths) {
        if (fs.existsSync(p)) return p;
    }
    return null;
}

async function captureEditor() {
    const executablePath = findChromeExecutable();
    const browser = await puppeteer.launch({
        executablePath: executablePath,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1400, height: 900 });

        // Let's log in or load the template page or simulate the exact editor blade
        // First let's check if we can access the editor directly or if auth is needed
        await page.goto('http://127.0.0.1:8000/admin/certificates/templates/1/edit?locale=ar', { waitUntil: 'networkidle2' });

        const currentUrl = page.url();
        console.log('Navigated URL:', currentUrl);

        if (currentUrl.includes('login')) {
            console.log('Filling login form...');
            await page.type('input[name="email"]', 'admin@demo.com');
            await page.type('input[name="password"]', '123456');
            await page.click('button[type="submit"]');
            await page.waitForNavigation({ waitUntil: 'networkidle2' });
            console.log('Logged in, navigated to:', page.url());
            await page.goto('http://127.0.0.1:8000/admin/certificates/templates/8/edit?locale=ar', { waitUntil: 'networkidle2' });
        } else {
            await page.goto('http://127.0.0.1:8000/admin/certificates/templates/8/edit?locale=ar', { waitUntil: 'networkidle2' });
        }

        // Wait for certificate container in editor
        await page.waitForSelector('#certificateTemplateContainer', { timeout: 5000 });

        // Take full screenshot of editor
        await page.screenshot({ path: 'storage/app/debug/editor-full-page.png' });

        // Take screenshot of just the certificate preview in the editor
        const container = await page.$('#certificateTemplateContainer');
        if (container) {
            await container.screenshot({ path: 'storage/app/debug/editor-preview-container.png' });
        }

        // Get metrics of editor draggable elements
        const metrics = await page.evaluate(() => {
            const res = {};
            const elements = document.querySelectorAll('#certificateTemplateContainer .draggable-element');
            elements.forEach(el => {
                const name = el.getAttribute('data-name');
                const r = el.getBoundingClientRect();
                const s = window.getComputedStyle(el);
                res[name] = {
                    x: r.x,
                    y: r.y,
                    width: r.width,
                    height: r.height,
                    text: el.innerText.trim(),
                    left: s.left,
                    top: s.top,
                    direction: s.direction,
                    textAlign: s.textAlign,
                    fontSize: s.fontSize
                };
            });
            return res;
        });

        console.log('=== EDITOR DOM METRICS ===');
        console.log(JSON.stringify(metrics, null, 2));
    } finally {
        await browser.close();
    }
}

captureEditor().catch(console.error);

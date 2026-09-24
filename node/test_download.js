const puppeteer = require('puppeteer-core');
const fs = require('fs');

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

async function testDownload() {
    const executablePath = findChromeExecutable();
    const browser = await puppeteer.launch({
        executablePath: executablePath,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.goto('http://127.0.0.1:8000/admin/login', { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'admin@demo.com');
        await page.type('input[name="password"]', '123456');
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' });

        console.log('Logged in successfully');

        // Fetch binary through page session
        const base64Data = await page.evaluate(async () => {
            const res = await fetch('/admin/certificates/21/download');
            const blob = await res.blob();
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.readAsDataURL(blob);
            });
        });

        if (base64Data && base64Data.startsWith('data:')) {
            const rawBase64 = base64Data.split(',')[1];
            const buffer = Buffer.from(rawBase64, 'base64');
            const outPath = 'D:\\projecs\\LightWay\\storage\\app\\debug\\downloaded_cert_21.pdf';
            fs.writeFileSync(outPath, buffer);
            console.log('Successfully downloaded cert 21 to:', outPath, 'Size:', buffer.length, 'bytes');
        } else {
            console.error('Failed to download: returned data invalid:', base64Data);
        }
    } finally {
        await browser.close();
    }
}

testDownload().catch(console.error);

const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

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

async function renderPdfToImage(pdfPath, outPngPath) {
    const executablePath = findChromeExecutable();
    const browser = await puppeteer.launch({
        executablePath: executablePath,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 930, height: 600 });
        
        // Open PDF using chrome's built-in PDF viewer or file URL
        const fileUrl = 'file:///' + pdfPath.replace(/\\/g, '/');
        await page.goto(fileUrl, { waitUntil: 'networkidle0' });
        await new Promise(r => setTimeout(r, 1000));
        
        await page.screenshot({ path: outPngPath });
        console.log('Saved PDF rendered image to:', outPngPath);
    } finally {
        await browser.close();
    }
}

renderPdfToImage(
    'D:\\projecs\\LightWay\\storage\\app\\debug\\certificate-output.pdf',
    'D:\\projecs\\LightWay\\storage\\app\\debug\\pdf-rendered-view.png'
).catch(console.error);

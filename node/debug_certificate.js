const fs = require('fs');
const path = require('path');
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

async function debugCertificate(inputHtmlPath, outputPngPath, outputPdfPath) {
    const executablePath = findChromeExecutable();
    const browser = await puppeteer.launch({
        executablePath: executablePath,
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-gpu',
            '--allow-file-access-from-files',
            '--enable-local-file-accesses'
        ]
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({
            width: 930,
            height: 600,
            deviceScaleFactor: 1
        });

        const htmlContent = fs.readFileSync(inputHtmlPath, 'utf8');
        await page.setContent(htmlContent, { waitUntil: ['load', 'networkidle0'] });

        await page.evaluate(async () => {
            if (document.fonts) await document.fonts.ready;
            const images = Array.from(document.querySelectorAll('img'));
            await Promise.all(images.map(img => {
                if (img.complete) return Promise.resolve();
                return new Promise(r => { img.addEventListener('load', r); img.addEventListener('error', r); });
            }));
        });

        // Collect DOM metrics and bounding boxes
        const domMetrics = await page.evaluate(() => {
            const results = {};
            const container = document.querySelector('.certificate-template-container') || document.querySelector('#certificateTemplateContainer');
            if (container) {
                const r = container.getBoundingClientRect();
                results['container'] = { x: r.x, y: r.y, width: r.width, height: r.height };
            }

            const elements = document.querySelectorAll('.draggable-element');
            elements.forEach(el => {
                const name = el.getAttribute('data-name') || el.className;
                const r = el.getBoundingClientRect();
                const style = window.getComputedStyle(el);
                results[name] = {
                    x: r.x,
                    y: r.y,
                    width: r.width,
                    height: r.height,
                    text: el.innerText ? el.innerText.trim() : '',
                    inlineStyle: el.getAttribute('style'),
                    computed: {
                        position: style.position,
                        left: style.left,
                        top: style.top,
                        right: style.right,
                        bottom: style.bottom,
                        textAlign: style.textAlign,
                        direction: style.direction,
                        fontSize: style.fontSize,
                        fontWeight: style.fontWeight
                    }
                };
            });
            return results;
        });

        console.log('=== DOM METRICS ===');
        console.log(JSON.stringify(domMetrics, null, 2));

        // Save screenshot
        await page.screenshot({
            path: outputPngPath,
            type: 'png',
            clip: { x: 0, y: 0, width: 930, height: 600 }
        });

        // Save PDF
        await page.pdf({
            path: outputPdfPath,
            width: '930px',
            height: '600px',
            printBackground: true,
            pageRanges: '1',
            margin: { top: '0px', right: '0px', bottom: '0px', left: '0px' }
        });

        console.log('Saved debug PNG to:', outputPngPath);
        console.log('Saved debug PDF to:', outputPdfPath);
    } finally {
        await browser.close();
    }
}

const htmlFile = process.argv[2];
const pngFile = process.argv[3];
const pdfFile = process.argv[4];

debugCertificate(htmlFile, pngFile, pdfFile).catch(console.error);

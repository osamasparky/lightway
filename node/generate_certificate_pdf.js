const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

function findChromeExecutable() {
    const customPath = process.env.PUPPETEER_EXECUTABLE_PATH || process.env.CHROME_PATH;
    if (customPath && fs.existsSync(customPath)) {
        return customPath;
    }

    const candidatePaths = [
        // Windows Chrome
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Google\\Chrome\\Application\\chrome.exe') : null,
        
        // Windows Edge (Chromium)
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Microsoft\\Edge\\Application\\msedge.exe') : null,

        // Linux Chromium / Chrome
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/snap/bin/chromium',

        // macOS
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge'
    ].filter(Boolean);

    for (const p of candidatePaths) {
        if (fs.existsSync(p)) {
            return p;
        }
    }

    return null;
}

async function generatePdf(inputHtmlPath, outputPdfPath, outputPngPath = null) {
    const executablePath = findChromeExecutable();
    if (!executablePath) {
        throw new Error('Chromium/Chrome executable not found. Please install Google Chrome, Chromium, or Microsoft Edge.');
    }

    const htmlContent = fs.readFileSync(inputHtmlPath, 'utf8');

    const browser = await puppeteer.launch({
        executablePath: executablePath,
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--allow-file-access-from-files',
            '--enable-local-file-accesses',
            '--run-all-compositor-stages-before-draw'
        ]
    });

    try {
        const page = await browser.newPage();
        
        await page.setViewport({
            width: 930,
            height: 600,
            deviceScaleFactor: 2
        });

        // Set HTML content
        await page.setContent(htmlContent, {
            waitUntil: ['load', 'networkidle0']
        });

        // Wait for all fonts and images to finish loading completely
        await page.evaluate(async () => {
            if (document.fonts) {
                await document.fonts.ready;
            }

            const images = Array.from(document.querySelectorAll('img'));
            await Promise.all(
                images.map(img => {
                    if (img.complete) return Promise.resolve();
                    return new Promise((resolve) => {
                        img.addEventListener('load', resolve);
                        img.addEventListener('error', resolve);
                    });
                })
            );
        });

        if (outputPngPath) {
            await page.screenshot({
                path: outputPngPath,
                type: 'png',
                clip: { x: 0, y: 0, width: 930, height: 600 }
            });
        }

        // Generate PDF matching exact 930x600 dimensions
        await page.pdf({
            path: outputPdfPath,
            width: '930px',
            height: '600px',
            printBackground: true,
            pageRanges: '1',
            margin: {
                top: '0px',
                right: '0px',
                bottom: '0px',
                left: '0px'
            }
        });

        console.log(`SUCCESS: Rendered Chromium PDF -> ${outputPdfPath}`);
    } finally {
        await browser.close();
    }
}

const args = process.argv.slice(2);
if (args.length < 2) {
    console.error('Usage: node generate_certificate_pdf.js <inputHtmlPath> <outputPdfPath> [outputPngPath]');
    process.exit(1);
}

generatePdf(args[0], args[1], args[2] || null)
    .then(() => process.exit(0))
    .catch((err) => {
        console.error('Chromium PDF Generation Error:', err);
        process.exit(1);
    });

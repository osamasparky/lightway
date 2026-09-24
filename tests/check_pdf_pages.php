<?php

$paths = [
    __DIR__ . '/../public/store/test_chromium_cert.pdf',
    __DIR__ . '/../public/store/perfect_certificate.pdf',
    __DIR__ . '/../public/store/test_page_bg.pdf',
    __DIR__ . '/../public/store/995/certificates/certificate_3.pdf'
];

foreach ($paths as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $pageCount = preg_match_all("/\/Type\s*\/Page\b/", $content, $matches);
        echo "PDF File: " . basename($file) . " | Total Page Count: " . $pageCount . " | File Size: " . filesize($file) . " bytes\n";
    }
}

<?php

$file = __DIR__ . '/../app/Mixins/Certificate/MakeCertificate.php';
$content = file_get_contents($file);

$target = '    private function sendToApi($certificate, $html)
    {
        $userId = getCertificateMainSettings("certificate_api_user_id");
        $APIKey = getCertificateMainSettings("certificate_api_key");

        $data = [
            'html' => $html,
            'viewport_width' => CertificateTemplate::$templateWidth,
            'viewport_height' => CertificateTemplate::$templateHeight,
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://hcti.io/v1/image?width=400");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        curl_setopt($ch, CURLOPT_POST, 1);

        // Retrieve your user_id and api_key from https://htmlcsstoimage.com/dashboard
        curl_setopt($ch, CURLOPT_USERPWD, "{$userId}" . ":" . "{$APIKey}");

        $headers = array();
        $headers[] = "Content-Type: application/x-www-form-urlencoded";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo \'Error:\' . curl_error($ch);
        }
        curl_close($ch);
        $res = json_decode($result, true);

        if (!empty($res[\'url\'])) {
            $url = $res[\'url\'] . ".png";
            $image = file_get_contents($url);
            $storage = Storage::disk(\'public\');
            $path = auth()->id() . \'/certificates\';
            if (!$storage->exists($path)) {
                $storage->makeDirectory($path);
            }
            $fileName = "certificate_{$certificate->id}.png";
            $path = $path . \'/\' . $fileName;
            $storage->put($path, $image);
            $url = public_path($storage->url($path));
            $headers = array(
                \'Content-Type\' => \'image/jpeg\',
            );

            return response()->download($url, "certificate.png", $headers);
        } elseif (!empty($res[\'error\']) and $res[\'error\'] == "Plan limit exceeded") {
            $error = trans(\'update.plan_limit_exceeded\');
        } else {
            $error = trans("update.bad_request");
        }

        $toastData = [
            \'title\' => trans(\'public.request_failed\'),
            \'msg\' => $error,
            \'status\' => \'error\'
        ];
        return redirect()->back()->with([\'toast\' => $toastData]);
    }';

$replacement = '    private function sendToApi($certificate, $html)
    {
        $userId = getCertificateMainSettings("certificate_api_user_id");
        $APIKey = getCertificateMainSettings("certificate_api_key");

        if (!empty($userId) and !empty($APIKey)) {
            $data = [
                \'html\' => $html,
                \'viewport_width\' => CertificateTemplate::$templateWidth,
                \'viewport_height\' => CertificateTemplate::$templateHeight,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://hcti.io/v1/image?width=400");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_USERPWD, "{$userId}" . ":" . "{$APIKey}");

            $headers = array();
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            curl_close($ch);
            $res = json_decode($result, true);

            if (!empty($res[\'url\'])) {
                $url = $res[\'url\'] . ".png";
                $image = @file_get_contents($url);
                if (!empty($image)) {
                    $storage = Storage::disk(\'public\');
                    $studentId = !empty($certificate->student_id) ? $certificate->student_id : (auth()->check() ? auth()->id() : \'certificates\');
                    $path = $studentId . \'/certificates\';
                    if (!$storage->exists($path)) {
                        $storage->makeDirectory($path);
                    }
                    $fileName = "certificate_{$certificate->id}.png";
                    $path = $path . \'/\' . $fileName;
                    $storage->put($path, $image);
                    $url = public_path($storage->url($path));
                    $headers = array(
                        \'Content-Type\' => \'image/png\',
                    );

                    return response()->download($url, "certificate.png", $headers);
                }
            }
        }

        return $this->generateLocalCertificate($certificate, $html);
    }

    private function generateLocalCertificate($certificate, $html)
    {
        try {
            $widthMm = CertificateTemplate::$templateWidth * 0.264583;
            $heightMm = CertificateTemplate::$templateHeight * 0.264583;

            $pdf = \niklasravnsborg\LaravelPdf\Facades\Pdf::loadHTML($html, [
                \'format\' => [$widthMm, $heightMm],
                \'margin_left\' => 0,
                \'margin_right\' => 0,
                \'margin_top\' => 0,
                \'margin_bottom\' => 0,
            ]);

            $pdfContent = $pdf->output();

            $storage = Storage::disk(\'public\');
            $studentId = !empty($certificate->student_id) ? $certificate->student_id : (auth()->check() ? auth()->id() : \'certificates\');
            $path = $studentId . \'/certificates\';
            if (!$storage->exists($path)) {
                $storage->makeDirectory($path);
            }
            $fileName = "certificate_{$certificate->id}.pdf";
            $filePath = $path . \'/\' . $fileName;
            $storage->put($filePath, $pdfContent);

            $downloadPath = public_path($storage->url($filePath));
            $headers = [
                \'Content-Type\' => \'application/pdf\',
                \'Content-Disposition\' => \'attachment; filename="certificate.pdf"\',
            ];

            return response()->download($downloadPath, "certificate.pdf", $headers);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(\'Local Certificate PDF Generation Error: \' . $e->getMessage());

            $toastData = [
                \'title\' => trans(\'public.request_failed\'),
                \'msg\' => trans("update.bad_request"),
                \'status\' => \'error\'
            ];
            return redirect()->back()->with([\'toast\' => $toastData]);
        }
    }';

// Normalize CRLF for matching
$normContent = str_replace("\r\n", "\n", $content);
$normTarget = str_replace("\r\n", "\n", $target);
$normReplacement = str_replace("\r\n", "\n", $replacement);

if (strpos($normContent, $normTarget) !== false) {
    $newContent = str_replace($normTarget, $normReplacement, $normContent);
    file_put_contents($file, $newContent);
    echo "SUCCESS: Updated MakeCertificate.php\n";
} else {
    echo "ERROR: Target content not found in MakeCertificate.php\n";
}

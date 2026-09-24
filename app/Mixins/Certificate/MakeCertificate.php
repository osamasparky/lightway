<?php

namespace App\Mixins\Certificate;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\UserMeta;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use niklasravnsborg\LaravelPdf\Facades\Pdf;

class MakeCertificate
{
    public function makeQuizCertificate($quizResult)
    {
        $template = CertificateTemplate::where('status', 'publish')
            ->where('type', 'quiz')
            ->first();

        if (!empty($template)) {
            $quiz = $quizResult->quiz;
            $user = $quizResult->user;

            $userCertificate = $this->saveQuizCertificate($user, $quiz, $quizResult);

            $locale = app()->getLocale();
            $body = (!empty($template->translate($locale)) and !empty($template->translate($locale)->body)) ? $template->translate($locale)->body : $template->body;

            list($body, $backgroundImage) = $this->makeBody(
                $template,
                $userCertificate,
                $user,
                $body,
                $quiz->webinar ? $quiz->webinar->title : '-',
                $quizResult->user_grade,
                $quiz->webinar ? $quiz->webinar->teacher->id : null,
                $quiz->webinar ? $quiz->webinar->teacher->full_name : null,
                $quiz->webinar ? $quiz->webinar->duration : null);

            $data = [
                'body' => $body,
                'backgroundImage' => $backgroundImage,
            ];

            $html = (string)view()->make('admin.certificates.create_template.show_certificate', $data);
            return $this->sendToApi($userCertificate, $html);
        }

        abort(404);
    }

    public function saveQuizCertificate($user, $quiz, $quizResult)
    {
        $certificate = Certificate::where('quiz_id', $quiz->id)
            ->where('student_id', $user->id)
            ->where('quiz_result_id', $quizResult->id)
            ->first();

        $data = [
            'quiz_id' => $quiz->id,
            'student_id' => $user->id,
            'quiz_result_id' => $quizResult->id,
            'user_grade' => $quizResult->user_grade,
            'type' => 'quiz',
            'created_at' => $quizResult->created_at,
        ];

        if (!empty($certificate)) {
            $certificate->update($data);
        } else {
            $certificate = Certificate::create($data);

            $notifyOptions = [
                '[c.title]' => $quiz->webinar ? $quiz->webinar->title : '-',
            ];
            sendNotification('new_certificate', $notifyOptions, $user->id);
        }

        return $certificate;
    }

    private function makeBody($template, $userCertificate, $user, $body, $courseTitle = null, $userGrade = null, $teacherId = null, $teacherFullName = null, $duration = null)
    {
        $platformName = getGeneralSettings("site_name");

        $body = str_replace('[student]', $user->full_name, $body);
        $body = str_replace('[student_name]', $user->full_name, $body);
        $body = str_replace('[platform_name]', $platformName, $body);
        $body = str_replace('[course]', $courseTitle, $body);
        $body = str_replace('[course_name]', $courseTitle, $body);
        $body = str_replace('[grade]', $userGrade, $body);
        $body = str_replace('[certificate_id]', $userCertificate->id, $body);
        $body = str_replace('[date]', dateTimeFormat($userCertificate->created_at, 'j M Y | H:i'), $body);
        $body = str_replace('[instructor_name]', $teacherFullName, $body);
        $body = str_replace('[duration]', $duration, $body);

        $qrCode = $this->makeQrCode($template);

        if (!empty($qrCode)) {
            $body = str_replace('[qr_code]', $qrCode, $body);
        }

        $instructorSignatureImg = null;
        if (!empty($teacherId)) {
            $instructorSignature = UserMeta::query()->where('user_id', $teacherId)
                ->where('name', 'signature')
                ->first();
            $instructorSignatureImg = (!empty($instructorSignature) and !empty($instructorSignature->value)) ? url($instructorSignature->value) : null;

            if (!empty($instructorSignatureImg)) {
                $instructorSignatureImg = "<img src='{$instructorSignatureImg}' style='max-width: 100%; max-height: 100%'/>";
            }
        }

        $body = str_replace('[instructor_signature]', $instructorSignatureImg, $body);

        $userCertificateAdditional = $user->userMetas->where('name', 'certificate_additional')->first();
        $userCertificateAdditionalValue = !empty($userCertificateAdditional) ? $userCertificateAdditional->value : null;
        $body = str_replace('[user_certificate_additional]', $userCertificateAdditionalValue, $body);

        // Normalize and convert all <img> tags to local Base64 Data URIs if local file exists
        $body = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function ($imgMatch) {
            $fullTag = $imgMatch[0];
            $src = $imgMatch[1];
            $cleanSrc = trim(html_entity_decode($src));
            $cleanSrc = rtrim($cleanSrc, '/');

            $localFile = null;
            if (str_contains($cleanSrc, '/store/')) {
                $subPath = substr($cleanSrc, strpos($cleanSrc, '/store/'));
                if (file_exists(public_path($subPath))) {
                    $localFile = public_path($subPath);
                }
            }
            if (!$localFile && file_exists(public_path($cleanSrc))) {
                $localFile = public_path($cleanSrc);
            }

            if ($localFile && file_exists($localFile)) {
                $mime = mime_content_type($localFile) ?: 'image/png';
                $base64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($localFile));
                return str_replace($src, $base64, $fullTag);
            }
            return $fullTag;
        }, $body);

        // Resolve background image to local base64 Data URI
        $backgroundImage = null;
        if (preg_match('/background-image:\s*url\((?:&quot;|&#039;|["\']?)([^"\'&]+)(?:&quot;|&#039;|["\']?)\)/i', $body, $bgMatch)) {
            $rawUrl = $bgMatch[1];
            $cleanUrl = trim(html_entity_decode($rawUrl));
            $cleanUrl = rtrim($cleanUrl, '/');
            $localCandidate = null;

            if (str_contains($cleanUrl, '/store/')) {
                $subPath = substr($cleanUrl, strpos($cleanUrl, '/store/'));
                if (file_exists(public_path($subPath))) {
                    $localCandidate = public_path($subPath);
                }
            }
            if (!$localCandidate && file_exists(public_path($cleanUrl))) {
                $localCandidate = public_path($cleanUrl);
            }
            if (!$localCandidate && !empty($template->image) && file_exists(public_path($template->image))) {
                $localCandidate = public_path($template->image);
            }

            if ($localCandidate && file_exists($localCandidate)) {
                $mime = mime_content_type($localCandidate) ?: 'image/jpeg';
                $backgroundImage = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($localCandidate));
            }
        }

        if (!$backgroundImage && !empty($template->image) && file_exists(public_path($template->image))) {
            $mime = mime_content_type(public_path($template->image)) ?: 'image/jpeg';
            $backgroundImage = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(public_path($template->image)));
        }

        // Clean container background-image attribute so mPDF uses @page background
        $body = preg_replace('/background-image:\s*url\([^)]+\);?/i', '', $body);

        return [$body, $backgroundImage];
    }

    private function makeQrCode($template)
    {
        $size = 128;
        $elements = $template->elements;

        if (!empty($elements) and !empty($elements['qr_code']) and !empty($elements['qr_code']['image_size'])) {
            $size = (int)$elements['qr_code']['image_size'];
        }

        $url = url("/certificate_validation");
        try {
            $qrSvg = QrCode::size($size)->generate($url);
            $qrSvgClean = preg_replace('/<\?xml[^\>]*\?>/i', '', (string)$qrSvg);
            return '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvgClean) . '" width="' . $size . '" height="' . $size . '" style="display:block;" />';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function makeImage($certificateTemplate, $body)
    {
        $img = Image::make(public_path($certificateTemplate->image));

        if ($certificateTemplate->rtl) {
            $Arabic = new \I18N_Arabic('Glyphs');
            $body = $Arabic->utf8Glyphs($body);
        }

        $img->text($body, $certificateTemplate->position_x, $certificateTemplate->position_y, function ($font) use ($certificateTemplate) {
            $font->file($certificateTemplate->rtl ? public_path('assets/default/fonts/vazir/Vazir-Medium.ttf') : public_path('assets/default/fonts/Montserrat-Medium.ttf'));
            $font->size($certificateTemplate->font_size);
            $font->color($certificateTemplate->text_color);
            $font->align($certificateTemplate->rtl ? 'right' : 'left');
        });

        return $img;
    }

    public function makeCourseCertificate($certificate)
    {
        $template = CertificateTemplate::where('status', 'publish')
            ->where('type', 'course')
            ->first();

        $course = $certificate->webinar;

        if (!empty($template) and !empty($course)) {
            $user = $certificate->student;

            $userCertificate = $this->saveCourseCertificate($user, $course);
            $locale = app()->getLocale();
            $body = (!empty($template->translate($locale)) and !empty($template->translate($locale)->body)) ? $template->translate($locale)->body : $template->body;

            list($body, $backgroundImage) = $this->makeBody(
                $template,
                $userCertificate,
                $user,
                $body,
                $course->title,
                null,
                $course->teacher->id,
                $course->teacher->full_name,
                $course->duration);

            $data = [
                'body' => $body,
                'backgroundImage' => $backgroundImage,
            ];

            $html = (string)view()->make('admin.certificates.create_template.show_certificate', $data);
            return $this->sendToApi($userCertificate, $html);
        }

        $toastData = [
            'title' => trans('public.request_failed'),
            'msg' => trans('update.no_certificate_template_is_defined_for_courses'),
            'status' => 'error'
        ];

        return redirect()->back()->with(['toast' => $toastData]);
    }

    public function makeBundleCertificate($certificate)
    {
        $template = CertificateTemplate::where('status', 'publish')
            ->where('type', 'bundle')
            ->first();

        $bundle = $certificate->bundle;

        if (!empty($template) and !empty($bundle)) {
            $user = $certificate->student;

            $userCertificate = $this->saveBundleCertificate($user, $bundle);
            $locale = app()->getLocale();
            $body = (!empty($template->translate($locale)) and !empty($template->translate($locale)->body)) ? $template->translate($locale)->body : $template->body;

            list($body, $backgroundImage) = $this->makeBody(
                $template,
                $userCertificate,
                $user,
                $body,
                $bundle->title,
                null,
                $bundle->teacher->id,
                $bundle->teacher->full_name,
                $bundle->duration);

            $data = [
                'body' => $body,
                'backgroundImage' => $backgroundImage,
            ];

            $html = (string)view()->make('admin.certificates.create_template.show_certificate', $data);
            return $this->sendToApi($userCertificate, $html);
        }

        $toastData = [
            'title' => trans('public.request_failed'),
            'msg' => trans('update.no_certificate_template_is_defined_for_bundles'),
            'status' => 'error'
        ];

        return redirect()->back()->with(['toast' => $toastData]);
    }

    private function sendToApi($certificate, $html)
    {
        $userId = getCertificateMainSettings("certificate_api_user_id");
        $APIKey = getCertificateMainSettings("certificate_api_key");

        // If external API credentials are provided, attempt external service
        if (!empty($userId) and !empty($APIKey)) {
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
            curl_setopt($ch, CURLOPT_USERPWD, "{$userId}" . ":" . "{$APIKey}");

            $headers = array();
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            curl_close($ch);
            $res = json_decode($result, true);

            if (!empty($res['url'])) {
                $url = $res['url'] . ".png";
                $image = @file_get_contents($url);
                if (!empty($image)) {
                    $storage = Storage::disk('public');
                    $studentId = !empty($certificate->student_id) ? $certificate->student_id : (auth()->check() ? auth()->id() : 'certificates');
                    $path = $studentId . '/certificates';
                    if (!$storage->exists($path)) {
                        $storage->makeDirectory($path);
                    }
                    $fileName = "certificate_{$certificate->id}.png";
                    $path = $path . '/' . $fileName;
                    $storage->put($path, $image);
                    $url = public_path($storage->url($path));
                    $headers = array(
                        'Content-Type' => 'image/png',
                    );

                    return response()->download($url, "certificate.png", $headers);
                }
            }
        }

        // Default & Autonomous: On-System Local Certificate Generation
        return $this->generateLocalCertificate($certificate, $html);
    }

    private function generateLocalCertificate($certificate, $html)
    {
        try {
            $storage = Storage::disk('public');
            $studentId = !empty($certificate->student_id) ? $certificate->student_id : (auth()->check() ? auth()->id() : 'certificates');
            $path = $studentId . '/certificates';
            if (!$storage->exists($path)) {
                $storage->makeDirectory($path);
            }
            $fileName = "certificate_{$certificate->id}.pdf";
            $filePath = $path . '/' . $fileName;
            $fullPdfPath = public_path($storage->url($filePath));

            // 1. Primary High-Fidelity Engine: Chromium Browser-Based PDF
            $nodeScript = base_path('node/generate_certificate_pdf.js');
            if (file_exists($nodeScript)) {
                $tempHtmlFile = storage_path('app/temp_cert_' . $certificate->id . '_' . time() . '.html');
                file_put_contents($tempHtmlFile, $html);

                $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($tempHtmlFile) . " " . escapeshellarg($fullPdfPath);
                @exec($cmd, $output, $returnCode);

                if (file_exists($tempHtmlFile)) {
                    @unlink($tempHtmlFile);
                }

                if ($returnCode === 0 && file_exists($fullPdfPath) && filesize($fullPdfPath) > 1000) {
                    $headers = [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="certificate.pdf"',
                    ];
                    return response()->download($fullPdfPath, "certificate.pdf", $headers);
                }
            }

            // 2. Secondary Engine: Built-in mPDF
            $widthMm = CertificateTemplate::$templateWidth * 0.264583;
            $heightMm = CertificateTemplate::$templateHeight * 0.264583;

            $pdf = Pdf::loadHTML($html, [
                'format' => [$widthMm, $heightMm],
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);

            $pdfContent = $pdf->output();
            $storage->put($filePath, $pdfContent);

            $downloadPath = public_path($storage->url($filePath));
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="certificate.pdf"',
            ];

            return response()->download($downloadPath, "certificate.pdf", $headers);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Local Certificate PDF Generation Error: ' . $e->getMessage());

            $toastData = [
                'title' => trans('public.request_failed'),
                'msg' => trans("update.bad_request"),
                'status' => 'error'
            ];
            return redirect()->back()->with(['toast' => $toastData]);
        }
    }

    public function saveCourseCertificate($user, $course)
    {
        $certificate = Certificate::where('webinar_id', $course->id)
            ->where('student_id', $user->id)
            ->first();

        $data = [
            'webinar_id' => $course->id,
            'student_id' => $user->id,
            'type' => 'course',
            'created_at' => time()
        ];

        if (!empty($certificate)) {
            $certificate->update($data);
        } else {
            $certificate = Certificate::create($data);

            $notifyOptions = [
                '[c.title]' => $course->title,
            ];
            sendNotification('new_certificate', $notifyOptions, $user->id);
        }

        return $certificate;
    }

    public function saveBundleCertificate($user, $bundle)
    {
        $certificate = Certificate::where('bundle_id', $bundle->id)
            ->where('student_id', $user->id)
            ->first();

        $data = [
            'bundle_id' => $bundle->id,
            'student_id' => $user->id,
            'type' => 'bundle',
            'created_at' => time()
        ];

        if (!empty($certificate)) {
            $certificate->update($data);
        } else {
            $certificate = Certificate::create($data);

            $notifyOptions = [
                '[c.title]' => $bundle->title,
            ];
            sendNotification('new_certificate', $notifyOptions, $user->id);
        }

        return $certificate;
    }
}

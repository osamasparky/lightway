@php
    $rtlLanguages = !empty($generalSettings['rtl_languages']) ? $generalSettings['rtl_languages'] : [];
    $isRtl = ((in_array(mb_strtoupper(app()->getLocale()), $rtlLanguages)) or (!empty($generalSettings['rtl_layout']) and $generalSettings['rtl_layout'] == 1));

    $certificateLtrFont = getCertificateMainSettings('ltr_font');
    $certificateRtlFont = getCertificateMainSettings('rtl_font');
@endphp

<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate</title>

    <style>
        @page {
            margin: 0;
            padding: 0;
            size: {{ \App\Models\CertificateTemplate::$templateWidth }}px {{ \App\Models\CertificateTemplate::$templateHeight }}px;
            @if(!empty($backgroundImage))
                background-image: url("{{ $backgroundImage }}");
                background-image-resize: 6;
            @endif
        }

@php
    $vazirRegPath = public_path('assets/default/fonts/vazir/Vazir-Regular.woff2');
    $vazirBoldPath = public_path('assets/default/fonts/vazir/Vazir-Bold.woff2');
    $montserratPath = public_path('assets/default/fonts/Montserrat-Medium.ttf');

    $vazirRegBase64 = file_exists($vazirRegPath) ? base64_encode(file_get_contents($vazirRegPath)) : '';
    $vazirBoldBase64 = file_exists($vazirBoldPath) ? base64_encode(file_get_contents($vazirBoldPath)) : '';
    $montserratBase64 = file_exists($montserratPath) ? base64_encode(file_get_contents($montserratPath)) : '';
@endphp

        @font-face {
            font-family: 'vazir';
            src: url('data:font/woff2;base64,{{ $vazirRegBase64 }}') format('woff2');
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: 'vazir';
            src: url('data:font/woff2;base64,{{ $vazirBoldBase64 }}') format('woff2');
            font-weight: 700;
            font-style: normal;
        }

        @font-face {
            font-family: 'montserrat';
            src: url('data:font/truetype;base64,{{ $montserratBase64 }}') format('truetype');
            font-weight: 500;
            font-style: normal;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: {{ \App\Models\CertificateTemplate::$templateWidth }}px;
            height: {{ \App\Models\CertificateTemplate::$templateHeight }}px;
            overflow: hidden;
            font-family: 'vazir', 'montserrat', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #ffffff;
            direction: ltr !important;
            text-align: left !important;
        }

        body.rtl {
            font-family: 'vazir', 'montserrat', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .certificate-template-container {
            width: {{ \App\Models\CertificateTemplate::$templateWidth }}px;
            height: {{ \App\Models\CertificateTemplate::$templateHeight }}px;
            position: relative;
            margin: 0 auto;
            padding: 0;
            border: none;
            overflow: hidden;
            direction: ltr !important;
            text-align: left !important;
            page-break-inside: avoid;
            page-break-after: avoid;
            background-repeat: no-repeat;
            background-size: 100% 100%;
            background-position: center center;
            @if(!empty($backgroundImage))
                background-image: url("{{ $backgroundImage }}");
            @endif
        }

        .certificate-template-container .draggable-element,
        .draggable-element {
            position: absolute !important;
            display: inline-block;
            white-space: pre-wrap;
            margin: 0;
            padding: 0;
            line-height: 1.2;
            box-sizing: border-box;
        }

        .draggable-element img {
            display: block;
        }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : '' }}">

    {!! $body !!}

</body>
</html>

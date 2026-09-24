require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vazirRegPath = public_path('assets/default/fonts/vazir/Vazir-Regular.woff2');
$vazirBoldPath = public_path('assets/default/fonts/vazir/Vazir-Bold.woff2');
$montserratPath = public_path('assets/default/fonts/Montserrat-Medium.ttf');

$vazirRegBase64 = base64_encode(file_get_contents($vazirRegPath));
$vazirBoldBase64 = base64_encode(file_get_contents($vazirBoldPath));
$montserratBase64 = base64_encode(file_get_contents($montserratPath));

$fontCss = "
        @font-face {
            font-family: 'vazir';
            src: url('data:font/woff2;base64,{$vazirRegBase64}') format('woff2');
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: 'vazir';
            src: url('data:font/woff2;base64,{$vazirBoldBase64}') format('woff2');
            font-weight: 700;
            font-style: normal;
        }

        @font-face {
            font-family: 'montserrat';
            src: url('data:font/truetype;base64,{$montserratBase64}') format('truetype');
            font-weight: 500;
            font-style: normal;
        }
";

file_put_contents(storage_path('app/fonts_inline.css'), $fontCss);
echo "Inlined font CSS generated successfully. Size: " . strlen($fontCss) . " bytes\n";

<?php

return [
	'mode'                  => 'utf-8',
	'format'                => [930 * 0.264583, 600 * 0.264583],
	'author'                => 'Meem LMS',
	'subject'               => 'Certificate',
	'keywords'              => 'Certificate, LMS',
	'creator'               => 'Meem LMS',
	'display_mode'          => 'fullpage',
	'tempDir'               => public_path('/store/temp/'),
	'pdf_a'                 => false,
	'pdf_a_auto'            => false,
	'icc_profile_path'      => '',
	'margin_left'           => 0,
	'margin_right'          => 0,
	'margin_top'            => 0,
	'margin_bottom'         => 0,
	'margin_header'         => 0,
	'margin_footer'         => 0,
	'font_path'             => public_path('assets/default/fonts/'),
	'font_data'             => [
		'vazir' => [
			'R'          => 'vazir/Vazir-Regular.ttf',
			'B'          => 'vazir/Vazir-Bold.ttf',
			'M'          => 'vazir/Vazir-Medium.ttf',
			'useOTL'     => 0xFF,
			'useKashida' => 75,
		],
		'montserrat' => [
			'R' => 'Montserrat-Medium.ttf',
			'M' => 'Montserrat-Medium.ttf',
		]
	],
	'auto_language_detection'  => true,
	'tempDir'               => storage_path('app/temp'),
];

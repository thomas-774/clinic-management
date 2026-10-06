<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | The PDF uses the app's font, Cairo (OFL), bundled as static TTFs so mPDF
    | can shape Arabic (VR-4). The Word file uses Arial instead, as Cairo is
    | not usually installed on the doctor's computer (T10-04).
    |
    */

    'pdf_font_dir' => resource_path('fonts/cairo'),

    'pdf_font' => 'cairo',

    'pdf_fonts' => [
        'cairo' => [
            'R' => 'Cairo-Regular.ttf',
            'B' => 'Cairo-Bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ],
    ],

    'word_font' => 'Arial',

    /*
    |--------------------------------------------------------------------------
    | Paper and temp files
    |--------------------------------------------------------------------------
    |
    | A4 portrait (FR-K.5). mPDF caches font data in its temp dir, which is
    | created on first use and git-ignored with the rest of storage/app.
    |
    */

    'paper' => 'A4',

    'temp_dir' => storage_path('app/mpdf'),

];

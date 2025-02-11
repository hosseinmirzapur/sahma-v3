<?php

return [
  'ocr' => [
    'url' => env('OCR_URL', ''),
    'downloadSearchablePdfLink' => env('OCR_DOWNLOAD_PDF_LINK', ''),
  ],
  'stt' => [
    'url' => env('STT_URL', ''),
    'voice-splitter-url' => env('STT_SPLITTER_URL', ''),
  ]
];

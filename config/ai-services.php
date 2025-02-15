<?php

return [
    'ocr' => [
        'url' => env('OCR_URL', ''),
        'downloadSearchablePdfLink' => env('OCR_DOWNLOAD_PDF_LINK', ''),
    ],
    'stt' => [
        'url' => env('STT_URL', ''),
        'voice-splitter-url' => env('STT_SPLITTER_URL', ''),
    ],
    /**
     * AI_SERVICE_MODE can be manual or auto, meaning that when an entity is uploaded
     * what happens next, whether to process the file via AI services automatically or manually do it
     */
    'mode' => env('AI_SERVICE_MODE', 'auto'),
];

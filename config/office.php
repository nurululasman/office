<?php

return [
    'quotation_document_type_code' => env('OFFICE_QUOTATION_DOCUMENT_TYPE_CODE', 'QUOTATION'),
    'business_timezone' => env('OFFICE_BUSINESS_TIMEZONE', 'Asia/Jakarta'),

    'documents' => [
        'disk' => env('OFFICE_DOCUMENT_DISK', 'documents'),
    ],

    'pdf' => [
        'chrome_binary' => env('OFFICE_CHROME_BINARY'),
        'timeout_seconds' => (int) env('OFFICE_PDF_TIMEOUT', 120),
    ],

    'queues' => [
        'default' => env('DB_QUEUE', 'default'),
        'pdf_connection' => env('PDF_QUEUE_CONNECTION', 'database'),
        'pdf' => env('PDF_QUEUE', 'pdf'),
    ],
];

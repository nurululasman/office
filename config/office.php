<?php

return [
    'business_timezone' => env('OFFICE_BUSINESS_TIMEZONE', 'Asia/Jakarta'),

    'documents' => [
        'disk' => env('OFFICE_DOCUMENT_DISK', 'documents'),
    ],

    'queues' => [
        'default' => env('DB_QUEUE', 'default'),
        'pdf' => env('PDF_QUEUE', 'pdf'),
    ],
];

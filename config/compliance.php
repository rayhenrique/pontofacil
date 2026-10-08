<?php

return [
    'developer' => [
        'name' => env('PONTOFACIL_DEVELOPER_NAME'),
        'document' => env('PONTOFACIL_DEVELOPER_DOCUMENT'),
        'document_type' => env('PONTOFACIL_DEVELOPER_DOCUMENT_TYPE'),
        'email' => env('PONTOFACIL_DEVELOPER_EMAIL'),
    ],
    'software' => [
        'name' => env('PONTOFACIL_SOFTWARE_NAME', 'PontoFacil'),
        'version' => env('PONTOFACIL_SOFTWARE_VERSION', '2.5.0'),
    ],
    'inpi' => [
        'status' => env('PONTOFACIL_INPI_STATUS', 'pending_registration'),
        'number' => env('PONTOFACIL_INPI_NUMBER', null),
    ],
    'icp_brasil' => [
        'status' => env('PONTOFACIL_ICP_STATUS', 'pending_certificate'),
    ],
];

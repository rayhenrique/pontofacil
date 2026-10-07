<?php

return [
    'developer' => [
        'name' => env('PONTOFACIL_DEVELOPER_NAME', 'PontoFacil Tecnologia Ltda'),
        'document' => env('PONTOFACIL_DEVELOPER_DOCUMENT', '12345678000199'),
        'document_type' => env('PONTOFACIL_DEVELOPER_DOCUMENT_TYPE', '1'), // 1 = CNPJ, 2 = CPF
        'email' => env('PONTOFACIL_DEVELOPER_EMAIL', 'compliance@pontofacil.local'),
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

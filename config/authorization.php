<?php

$permissions = [
    'administration' => [
        'users.read', 'users.manage', 'roles.read', 'roles.manage',
        'document-types.read', 'document-types.manage', 'templates.read', 'templates.manage',
        'company-profiles.read', 'company-profiles.manage', 'audit-logs.read',
    ],
    'documents' => ['documents.read', 'documents.issue', 'documents.void'],
    'quotations' => [
        'quotations.read', 'quotations.create', 'quotations.update-own', 'quotations.update-any',
        'quotations.submit', 'quotations.complete-direct', 'quotations.approve', 'quotations.reject',
        'quotations.void', 'quotations.pdf.read',
    ],
    'quotation_templates' => [
        'quotation-template.view', 'quotation-template.create', 'quotation-template.update',
        'quotation-template.activate', 'quotation-template.archive',
    ],
    'contracts' => [
        'contracts.read', 'contracts.create', 'contracts.update-own', 'contracts.update-any',
        'contracts.submit', 'contracts.complete-direct', 'contracts.approve', 'contracts.reject',
        'contracts.void', 'contracts.pdf.read',
    ],
];

$all = array_merge(...array_values($permissions));

return [
    'permissions' => $permissions,
    'roles' => [
        'office-user' => [],
        'system-admin' => $all,
        'document-admin' => [
            'document-types.read', 'document-types.manage', 'templates.read', 'templates.manage',
            'company-profiles.read', 'company-profiles.manage',
            'quotation-template.view', 'quotation-template.create', 'quotation-template.update',
            'quotation-template.activate', 'quotation-template.archive',
        ],
        'document-officer' => ['documents.read', 'documents.issue'],
        'quotation-maker' => [
            'quotations.read', 'quotations.create', 'quotations.update-own', 'quotations.submit',
            'quotations.complete-direct', 'quotations.pdf.read',
        ],
        'quotation-approver' => [
            'quotations.read', 'quotations.approve', 'quotations.reject', 'quotations.void', 'quotations.pdf.read',
        ],
        'contract-maker' => [
            'contracts.read', 'contracts.create', 'contracts.update-own', 'contracts.submit',
            'contracts.complete-direct', 'contracts.pdf.read',
        ],
        'contract-approver' => [
            'contracts.read', 'contracts.approve', 'contracts.reject', 'contracts.void', 'contracts.pdf.read',
        ],
        'auditor' => [
            'document-types.read', 'templates.read', 'documents.read', 'quotations.read',
            'quotations.pdf.read', 'contracts.read', 'contracts.pdf.read', 'audit-logs.read',
            'quotation-template.view',
            'company-profiles.read',
        ],
    ],
    'bootstrap_admin' => [
        'issuer' => rtrim((string) env('OFFICE_BOOTSTRAP_ADMIN_SSO_ISSUER', ''), '/'),
        'subject' => env('OFFICE_BOOTSTRAP_ADMIN_SSO_SUBJECT'),
    ],
];

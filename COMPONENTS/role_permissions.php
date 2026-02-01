<?php
// USM/role_permissions.php
return [
    'admin' => [
        'budget_management',
        'budget_preparation',
        'budget_monitoring',
        'budget_reporting',
        'general_ledger',
        'accounts_payable',
        'accounts_receivable',
        'disbursement',
        'collection',
        'analytics',
        'user_management',
        'profile',
        'settings'
    ],
  
    
    'budget officer' => [
        'budget_preparation',
        'budget_monitoring',
        'budget_reporting',
        'profile'
    ],
    
    'collection officer' => [
        'collection',
        'profile'
    ],
    
    'disbursement officer' => [
        'disbursement',
        'profile'
    ],
    
    'general ledger accountant' => [
        'general_ledger',
        'profile'
    ],
    
    'receivable/payable officer' => [
        'accounts_payable',
        'accounts_receivable',
        'profile'
    ],
    
    'manager' => [
        'budget_management',
        'budget_monitoring',
        'budget_reporting',
        'analytics',
        'profile'
    ]
];
?>
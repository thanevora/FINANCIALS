<?php
// USM/role_permissions.php
return [
    'superviser' => [
        'table_reservation',
        'kitchen_orders',
        'inventory',
        'menu_management',
        'event_management',
        'table_turnover',
        'pos_system',
        'billing',
        'staff_management',
        'customer_feedback',
        'analytics',
        'user_management',
    
    ],
    
    'admin' => [
      
        'analytics',
        'user_management',
       
    ],
    
    'manager' => [
        'table_reservation',
        'kitchen_orders',
        'inventory',
        'menu_management',
        'event_management',
        'table_turnover',
        'pos_system',
        'billing',
        'staff_management',
        'customer_feedback',
        'analytics',
        'user_management'
    ],
    
    'inventory' => [
        'inventory',
        'user_management'

    ],
    
    'waiter' => [
        'staff_management',
        'user_management'
    ],
    
    'cashier' => [
        'user_management',
        'pos_system',
        'billing',

    ],
    
    'security' => [
        'user_management',
       

    ],
    
    'staff' => [
        'user_management',
        'customer_feedback'

    ],

    'chef' => [
        'user_management',
        'kitchen_orders',
        'menu_management',
        'inventory',

    ],

     'reservation' => [
        'user_management',
        'table_reservation',
        'event_management',

    ],
   
];
?>
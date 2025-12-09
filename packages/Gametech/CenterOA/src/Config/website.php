<?php

return [
    'register' => [
        'mode' => 'username', // 'username' | 'phone' | 'both',
        'phone' => [
            'length' => 15,
            'min_length' => 8,
            'max_length' => 15,
        ],
    ],
];

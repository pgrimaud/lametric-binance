<?php

use LaMetric\Field;

return [
    [
        'key'  => 'api-key',
        'type' => Field::TEXT_TYPE,
    ],
    [
        'key'  => 'secret-key',
        'type' => Field::TEXT_TYPE,
    ],
    [
        'key'     => 'currency',
        'type'    => Field::TEXT_TYPE,
        'default' => 'USD',
    ],
    [
        'key'     => 'position',
        'type'    => Field::CHOICES_TYPE,
        'choices' => [
            'before',
            'after',
            'hide',
        ],
        'default' => 'before',
    ],
    [
        'key'     => 'separate-assets',
        'type'    => Field::SWITCH_TYPE,
        'default' => 'false',
    ],
];

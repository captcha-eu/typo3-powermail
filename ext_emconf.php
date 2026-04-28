<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'captchaeu',
    'description' => 'Captcha.eu TYPO3 extension for powermail',
    'category' => 'plugin',
    'version' => '1.1.0',
    'state' => 'stable',
    'author' => 'Captcha.eu',
    'author_email' => 'h.januschka@captcha.eu',
    'author_company' => 'captcha.eu',
    'constraints' => [
        'depends' => [
            'powermail' => '8.5.0-13.99.99',
            'typo3' => '10.4.0-13.4.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ]
];

<?php

return [
    'backend' => [
        'middleware-identifier' => [
            'target' => \Hn\Video\Middleware\CrossOriginHeaderMiddleware::class,
        ],
    ],
];
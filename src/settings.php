<?php

use Monolog\Logger;

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'tourism-control-panel',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => Logger::DEBUG,
        ],

        'database' => [
            'dsn' => 'mysql:host=database;dbname=tourism',
            'username' => 'tourism_user',
            'password' => 'u&e6uaAz-b#^Lj7m'
        ],
        
        'csvGeneration' => [
            'destination' => '/var/www/html/public/downloads',
            'httpPath' => '/downloads'
        ]
    ],
];

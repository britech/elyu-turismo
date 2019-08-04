<?php

use Monolog\Logger;

return [
    'settings' => [
        'displayErrorDetails' => intval(getenv('DISPLAY_ERROR_DETAILS')) == 1, // set to false in production
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

        'csvGeneration' => [
            'destination' => getenv('CSV_GENERATION_PATH'),
            'httpPath' => getenv('CSV_HTTP_PATH')
        ],

        'uploadPath' => getenv('UPLOAD_PATH')
    ],
];

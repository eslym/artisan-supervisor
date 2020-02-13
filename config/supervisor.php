<?php

return [
    'services' => [
        'queue' => [

            /*
             * Supported macro:
             * @php - php executable
             * @artisan - php artisan
             */
            'command' => '@artisan queue:work --no-ansi --no-interaction',

            /*
             * Number of workers
             * default: 1
             */
            'replicas' => 4,

            /*
             * Restart delay (seconds)
             */
            'delay' => 1,
        ]
    ]
];
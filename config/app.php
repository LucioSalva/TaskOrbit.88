<?php
return [
    'name'     => getenv('APP_NAME') ?: 'TaskOrbit',
    'url'      => getenv('APP_URL')  ?: 'http://localhost/taskorbit/public',
    'debug'    => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Mexico_City',
];

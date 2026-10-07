<?php
// Popcorn Silo configuration
//
// Fill in the MySQL credentials below to use a MySQL database.
// If they are blank, the driver is missing, or the connection fails,
// the app automatically falls back to local JSON files in 'data_dir'.

return [
    'mysql' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'popcorn_silo',
        'username' => '',
        'password' => '',
    ],

    // New-account registration.
    //   open:         set to false to disable signups entirely.
    //   invite_codes: if non-empty, signups require one of these codes.
    'registration' => [
        'open'         => true,
        'invite_codes' => [],
    ],

    // Where uploaded file contents are stored on disk.
    'upload_dir' => __DIR__ . '/uploads',

    // Where the local JSON data store lives (fallback when MySQL is unavailable).
    'data_dir' => __DIR__ . '/data',
];

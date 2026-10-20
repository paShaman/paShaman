<?php

return [

    'disks' => [

        'projects' => [
            'driver' => 'local',
            'root' => public_path('images/projects'),
            'url' => '/images/projects',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

];

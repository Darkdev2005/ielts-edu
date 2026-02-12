<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mock Audio Storage
    |--------------------------------------------------------------------------
    |
    | audio_disk: filesystem disk used to store uploaded mock listening audio.
    | audio_max_mb: max upload size in MB for one audio file.
    | audio_prefix: folder/prefix inside selected disk.
    |
    */
    'audio_disk' => env('MOCK_AUDIO_DISK', 'public'),
    'audio_max_mb' => (int) env('MOCK_AUDIO_MAX_MB', 15),
    'audio_prefix' => env('MOCK_AUDIO_PREFIX', 'mock-audio'),
];

<?php

return [
    'max_wait_time' => env('ORDER_POOLING_MAX_WAIT_TIME', 30000), // 15 секунд
    'poll_interval' => env('ORDER_POOLING_POLL_INTERVAL', 100), // 100 мс
    //'task_ttl' => env('ORDER_POOLING_TASK_TTL', 300), // 5 минут
    //'job_timeout' => env('ORDER_POOLING_JOB_TIMEOUT', 5), // 5 секунд
];

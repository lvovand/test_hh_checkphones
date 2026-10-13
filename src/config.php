<?php
// Секретов нет: источник публичный. Если появится токен — брать из переменной окружения
// (SetEnv в .htaccess вне git / env на хостинге), не коммитить.
return [
    'source_url' => getenv('ORDERS_URL') ?: 'https://test.ooobs.ru/api/orders',
    'source_token' => getenv('ORDERS_TOKEN') ?: '',
    'timeout' => 10,
    'snapshot' => __DIR__ . '/../data/result.json',
];

<?php

return [
    'smtp_host' => getenv('SMTP_HOST') ?: '',
    'smtp_port' => (int) (getenv('SMTP_PORT') ?: 587),
    'smtp_username' => getenv('SMTP_USERNAME') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_from_email' => getenv('SMTP_FROM_EMAIL') ?: '',
    'smtp_from_name' => getenv('SMTP_FROM_NAME') ?: 'Calisthenics Trentino Academy',
    'smtp_to_email' => getenv('SMTP_TO_EMAIL') ?: '',
];

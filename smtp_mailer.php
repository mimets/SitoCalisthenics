<?php

function cta_load_mail_config(): array
{
    $configPath = __DIR__ . '/mail-config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('Config SMTP mancante');
    }

    $config = require $configPath;
    if (!is_array($config)) {
        throw new RuntimeException('Config SMTP non valida');
    }

    foreach ($config as $key => $value) {
        if (is_string($value)) {
            $config[$key] = trim($value);
        }
    }

    if (isset($config['smtp_password']) && is_string($config['smtp_password'])) {
        // Gmail mostra spesso le app password a gruppi di 4 caratteri separati da spazi.
        $config['smtp_password'] = preg_replace('/\\s+/', '', $config['smtp_password']);
    }

    $requiredKeys = [
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_from_email',
        'smtp_from_name',
        'smtp_to_email',
    ];

    foreach ($requiredKeys as $key) {
        if (!isset($config[$key]) || trim((string) $config[$key]) === '') {
            throw new RuntimeException('Config SMTP incompleta');
        }
    }

    if (strpos((string) $config['smtp_password'], 'INSERISCI_QUI_') === 0) {
        throw new RuntimeException('Config SMTP incompleta');
    }

    return $config;
}

function cta_send_contact_email(array $payload): void
{
    $config = cta_load_mail_config();

    $subjectText = '[CTA] ' . $payload['oggetto'] . ' da ' . $payload['nome'];
    $subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';

    $messageLines = [
        'Hai ricevuto un nuovo messaggio dal sito.',
        '',
        'Data: ' . date('Y-m-d H:i:s'),
        'Nome: ' . $payload['nome'],
        'Email: ' . $payload['email'],
        'Oggetto: ' . $payload['oggetto'],
        '',
        'Messaggio:',
        $payload['messaggio'],
    ];

    $body = implode("\r\n", $messageLines);

    $headers = [
        'From: ' . cta_format_address($config['smtp_from_email'], $config['smtp_from_name']),
        'To: ' . cta_format_address($config['smtp_to_email'], $config['smtp_from_name']),
        'Reply-To: ' . $payload['email'],
        'Subject: ' . $subject,
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $data = implode("\r\n", $headers) . "\r\n\r\n" . cta_normalize_smtp_body($body) . "\r\n";

    cta_smtp_send(
        $config['smtp_host'],
        (int) $config['smtp_port'],
        $config['smtp_username'],
        $config['smtp_password'],
        $config['smtp_from_email'],
        $config['smtp_to_email'],
        $data
    );
}

function cta_format_address(string $email, string $name): string
{
    $encodedName = '=?UTF-8?B?' . base64_encode($name) . '?=';
    return $encodedName . ' <' . $email . '>';
}

function cta_normalize_smtp_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);

    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }

    return implode("\r\n", $lines);
}

function cta_smtp_send(
    string $host,
    int $port,
    string $username,
    string $password,
    string $fromEmail,
    string $toEmail,
    string $message
): void {
    $socket = @stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException('Connessione SMTP fallita: ' . $errorMessage);
    }

    stream_set_timeout($socket, 20);

    try {
        cta_expect_smtp($socket, [220]);
        cta_send_smtp($socket, 'EHLO localhost');
        cta_expect_smtp($socket, [250]);

        cta_send_smtp($socket, 'STARTTLS');
        cta_expect_smtp($socket, [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Impossibile avviare TLS');
        }

        cta_send_smtp($socket, 'EHLO localhost');
        cta_expect_smtp($socket, [250]);

        cta_send_smtp($socket, 'AUTH LOGIN');
        cta_expect_smtp($socket, [334]);

        cta_send_smtp($socket, base64_encode($username));
        cta_expect_smtp($socket, [334]);

        cta_send_smtp($socket, base64_encode($password));
        cta_expect_smtp($socket, [235]);

        cta_send_smtp($socket, 'MAIL FROM:<' . $fromEmail . '>');
        cta_expect_smtp($socket, [250]);

        cta_send_smtp($socket, 'RCPT TO:<' . $toEmail . '>');
        cta_expect_smtp($socket, [250, 251]);

        cta_send_smtp($socket, 'DATA');
        cta_expect_smtp($socket, [354]);

        cta_send_smtp($socket, $message . "\r\n.");
        cta_expect_smtp($socket, [250]);

        cta_send_smtp($socket, 'QUIT');
    } finally {
        fclose($socket);
    }
}

function cta_send_smtp($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function cta_expect_smtp($socket, array $expectedCodes): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('Nessuna risposta dal server SMTP');
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP errore ' . $code . ': ' . trim($response));
    }

    return $response;
}

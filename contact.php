<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/smtp_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non permesso']);
    exit;
}

$nome     = trim(strip_tags($_POST['nome']     ?? ''));
$email    = trim(strip_tags($_POST['email']    ?? ''));
$oggetto  = trim(strip_tags($_POST['oggetto']  ?? 'Contatto dal sito'));
$messaggio = trim(strip_tags($_POST['messaggio'] ?? ''));

// Validazione
if (empty($nome) || empty($email) || empty($messaggio)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Campi obbligatori mancanti']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email non valida']);
    exit;
}

// Crea la cartella per i messaggi se non esiste
$messagesDir = __DIR__ . '/messaggi';
if (!is_dir($messagesDir)) {
    mkdir($messagesDir, 0755, true);
}

// Salva il messaggio con timestamp
$timestamp = date('Y-m-d H:i:s');
$fileName = $messagesDir . '/msg-' . date('YmdHis') . '-' . uniqid() . '.txt';

$fileContent = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$fileContent .= "NUOVO MESSAGGIO DAL SITO\n";
$fileContent .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$fileContent .= "Data: $timestamp\n";
$fileContent .= "Nome: $nome\n";
$fileContent .= "Email: $email\n";
$fileContent .= "Oggetto: $oggetto\n\n";
$fileContent .= "Messaggio:\n";
$fileContent .= "─────────────────────────────────────────\n";
$fileContent .= $messaggio . "\n";
$fileContent .= "─────────────────────────────────────────\n\n";

// Salva il file
if (file_put_contents($fileName, $fileContent)) {
    try {
        cta_send_contact_email([
            'nome' => $nome,
            'email' => $email,
            'oggetto' => $oggetto,
            'messaggio' => $messaggio,
        ]);

        echo json_encode([
            'ok' => true,
            'saved' => true,
            'email_sent' => true,
        ]);
    } catch (Throwable $exception) {
        error_log(
            '[CTA contact] SMTP send failed: ' . $exception->getMessage() .
            ' | file=' . basename($fileName)
        );

        echo json_encode([
            'ok' => true,
            'saved' => true,
            'email_sent' => false,
            'message' => 'Messaggio salvato correttamente. Se necessario ti contatteremo manualmente.',
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore salvataggio messaggio']);
}
?>

<?php
// Prosty backend statystyk SCYTHED.
// Wymaga PHP z prawem zapisu do katalogu, w którym znajduje się ten plik.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$statsFile = __DIR__ . DIRECTORY_SEPARATOR . 'stats.json';
$allowed = ['visit', 'demo', 'download'];
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

$stats = [
    'visits' => 0,
    'clicks' => 0,
    'downloads' => 0,
];

if (is_file($statsFile)) {
    $raw = @file_get_contents($statsFile);
    $saved = json_decode($raw ?: '', true);
    if (is_array($saved)) {
        foreach ($stats as $key => $value) {
            if (isset($saved[$key]) && is_numeric($saved[$key])) {
                $stats[$key] = max(0, (int)$saved[$key]);
            }
        }
    }
}

if ($action !== 'get') {
    if (!in_array($action, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowa akcja'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $key = $action === 'visit' ? 'visits' : ($action === 'demo' ? 'clicks' : 'downloads');
    $stats[$key]++;

    $fp = @fopen($statsFile, 'c+');
    if (!$fp) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Brak możliwości zapisu statystyk'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Nie udało się zablokować pliku statystyk'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Odczytaj ponownie po uzyskaniu blokady, żeby nie nadpisywać równoległych żądań.
    rewind($fp);
    $raw = stream_get_contents($fp);
    $current = json_decode($raw ?: '', true);
    if (is_array($current)) {
        foreach ($stats as $keyName => $value) {
            if (isset($current[$keyName]) && is_numeric($current[$keyName])) {
                $stats[$keyName] = max(0, (int)$current[$keyName]);
            }
        }
    }
    $stats[$key]++;

    $payload = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $payload);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

echo json_encode([
    'ok' => true,
    'visits' => $stats['visits'],
    'clicks' => $stats['clicks'],
    'downloads' => $stats['downloads'],
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * Serveur de signalisation WebRTC pour Live Practice
 * Échange les offres/réponses SDP et candidats ICE entre pairs
 */

require_once '../helpers/cors.php';
require_once '../config/config.php';
require_once '../helpers/jwt.php';

// Auth optionnelle (pour les rooms protégées)
function getAuthenticatedUserId() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        if (JWT::verify($matches[1])) {
            return JWT::decode($matches[1])['user_id'];
        }
    }
    return null;
}

$userId = getAuthenticatedUserId();

// Dossier de stockage des signaux
$signalDir = __DIR__ . '/signals';
if (!is_dir($signalDir)) {
    mkdir($signalDir, 0755, true);
}

function getSignalFile($room) {
    global $signalDir;
    // Sanitizer le nom de room
    $room = preg_replace('/[^a-zA-Z0-9_-]/', '_', $room);
    return $signalDir . '/room_' . $room . '.json';
}

function readSignals($room) {
    $file = getSignalFile($room);
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return $data ?: [];
}

function writeSignals($room, $data) {
    $file = getSignalFile($room);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

// POST : envoyer un signal (offer, answer, candidate)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $room = $input['room'] ?? '';
    $type = $input['type'] ?? '';
    $payload = $input['payload'] ?? null;
    $senderId = $input['sender_id'] ?? $userId;

    if (!$room || !$type || $payload === null) {
        http_response_code(400);
        echo json_encode(['error' => 'room, type et payload requis']);
        exit;
    }

    $signals = readSignals($room);

    // Pour les candidats ICE, on utilise un tableau
    if ($type === 'candidate') {
        if (!isset($signals['candidates'])) {
            $signals['candidates'] = [];
        }
        $signals['candidates'][] = [
            'candidate' => $payload,
            'sender_id' => $senderId,
            'timestamp' => time()
        ];
    } elseif ($type === 'offer') {
        // Stocker les offres par sender_id (plusieurs pairs possibles)
        if (!isset($signals['offers'])) {
            $signals['offers'] = [];
        }
        $signals['offers'][$senderId] = [
            'payload' => $payload,
            'sender_id' => $senderId,
            'timestamp' => time()
        ];
    } elseif ($type === 'answer') {
        // Stocker les reponses par sender_id
        if (!isset($signals['answers'])) {
            $signals['answers'] = [];
        }
        $signals['answers'][$senderId] = [
            'payload' => $payload,
            'sender_id' => $senderId,
            'timestamp' => time()
        ];
    }

    // Nettoyage des anciens candidats (> 5 min)
    if (isset($signals['candidates'])) {
        $now = time();
        $signals['candidates'] = array_filter($signals['candidates'], function($c) use ($now) {
            return ($now - $c['timestamp']) < 300;
        });
        $signals['candidates'] = array_values($signals['candidates']);
    }

    writeSignals($room, $signals);

    echo json_encode(['status' => 'ok']);
    exit;
}

// GET : récupérer un signal
if ($method === 'GET') {
    $room = $_GET['room'] ?? '';
    $type = $_GET['type'] ?? '';
    $since = $_GET['since'] ?? 0;

    if (!$room || !$type) {
        http_response_code(400);
        echo json_encode(['error' => 'room et type requis']);
        exit;
    }

    $signals = readSignals($room);

    if ($type === 'candidates') {
        $candidates = $signals['candidates'] ?? [];
        $candidates = array_filter($candidates, function($c) use ($since) {
            return $c['timestamp'] > $since;
        });
        echo json_encode(['payload' => array_values($candidates)]);
    } elseif ($type === 'offers') {
        $offers = $signals['offers'] ?? [];
        echo json_encode(['payload' => array_values($offers)]);
    } elseif ($type === 'answers') {
        $answers = $signals['answers'] ?? [];
        echo json_encode(['payload' => array_values($answers)]);
    } else {
        echo json_encode(['payload' => null]);
    }
    exit;
}

// DELETE : nettoyer les signaux d'une room (quand la session se termine)
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $room = $input['room'] ?? $_GET['room'] ?? '';

    if (!$room) {
        http_response_code(400);
        echo json_encode(['error' => 'room requis']);
        exit;
    }

    $file = getSignalFile($room);
    if (file_exists($file)) {
        unlink($file);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

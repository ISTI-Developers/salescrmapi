<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once __DIR__ . "/config/env.php";
require_once "./config/deck.controller.php";
require_once "./functions.php";
// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$deck_controller = new DeckController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['deck_id'])) {
                $decks = $deck_controller->get_deck($_GET['deck_id']);
                if ($decks) {
                    $decks->sites = json_decode($decks->sites);
                    $decks->filters = json_decode($decks->filters);
                    $decks->options = json_decode($decks->options);
                }
            } else {
                if (!isset($_GET['user_id'])) {
                    throw new Exception("User ID not found.");
                }
                $decks = $deck_controller->get_decks($_GET['user_id']);

            }
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            sendJsonResponse($decks);
            break;
        case "PUT":
            $data = json_decode(file_get_contents('php://input'), true);

            if ($data === null) {
                throw new Exception('Data not found.');
            }

            $results = $deck_controller->update_deck($data);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode([
                "acknowledged" => $results
            ]);
            break;
        case "DELETE":

    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
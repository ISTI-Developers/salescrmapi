<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/meeting.controller.php";
require_once "functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}
$meeting_controller = new MeetingController();
try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            $minutes = $meeting_controller->get_minutes($_GET['year'], json_decode($_GET['week']));
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            sendJsonResponse($minutes);
            break;
        case "POST":
            $result = $meeting_controller->insert_minute($_POST['activity'], $_POST['week'], $_POST['date']);
            http_response_code(201); // Created
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode([
                "acknowledged" => (bool) $result,
                "id" => $result
            ]);
            break;
        case "PUT":
            $data = json_decode(file_get_contents('php://input'), true);
            if (is_null($data)) {
                throw new Exception('Data not found.');
            }
            extract($data);

            $result = $meeting_controller->update_activity($activity, $week, $date, $ID);
            http_response_code(200); // Created
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode([
                "acknowledged" => (bool) $result,
                "id" => $ID
            ]);
            break;
        case "DELETE":
            if (!isset($_GET['id'])) {
                throw new Exception("Activity ID not found.");
            }
            $result = $meeting_controller->delete_activity($_GET['id']);
            http_response_code(200); // OK
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode([
                "acknowledged" => (bool) $result,
                "id" => $_GET['id']
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
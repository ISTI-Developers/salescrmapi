<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/settings.controller.php";
require_once "functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$settings_controller = new SettingsController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['access'])) {
                $settings = $settings_controller->get_week_accesses();
            }
            if (isset($_GET['report_access'])) {
                if (isset($_GET['id'])) {
                    $settings = $settings_controller->get_view_accesses($_GET['id']);
                } else {
                    $settings = $settings_controller->get_view_accesses();
                }
            }
            if (isset($_GET['advisory'])) {
                $settings = $settings_controller->get_advisory();
            }
            if (isset($_GET['unis_url'])) {
                $settings = isset($_GET['active']) ? $settings_controller->get_active_unis_path() : $settings_controller->get_unis_paths();
            }
            if (isset($_GET['trend'])) {
                $settings = $settings_controller->get_dashboard_trends();
            }
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode($settings);
            break;
        case "POST":
            if (isset($_POST['data']) && isset($_GET['unis_url'])) {
                $data = json_decode($_POST['data'], true);

                foreach ($data as $item) {
                    $settings_controller->update_unis_path($item['ID'], $item['status']);
                }
                echo json_encode([
                    "acknowledged" => true
                ]);
                exit();
            } else if (isset($_POST['title'])) {
                $receipient = $_POST['receipient'];
                $title = $_POST['title'];
                $content = $_POST['content'];

                $result = $settings_controller->insert_advisory($receipient, $title, $content);
                echo json_encode([
                    "acknowledged" => $result
                ]);
            } else {

                if (!isset($_POST['week'])) {
                    throw new Exception("Week not found.");
                }
                $result = $settings_controller->unlock_week($_POST['week']);
                if ($result) {
                    http_response_code(201); // Created
                    echo json_encode([
                        "acknowledged" => (bool) $result,
                        "id" => 0
                    ]);
                } else {
                    throw new Exception("Failed to unlock week.");
                }

            }
            break;
        case "PUT":
            $contents = json_decode(file_get_contents('php://input'), true);

            if (is_null($contents)) {
                throw new Exception('Data not found.');
            }
            extract($contents);

            $result = $settings_controller->update_view_access($access, $user_id);

            echo json_encode([
                "acknowledged" => (bool) $result,
                "id" => $user_id
            ]);
            break;
        case "DELETE":
            if (!isset($_GET['id'])) {
                throw new Exception("ID not found.");
            }
            $result = $settings_controller->lock_week($_GET['id']);
            if ($result) {
                http_response_code(200); // OK
                echo json_encode([
                    "acknowledged" => (bool) $result
                ]);
            } else {
                throw new Exception("Failed to unlock week.");
            }
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

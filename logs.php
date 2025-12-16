<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/log.controller.php";
require_once "functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$log_controller = new LogController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['t_id'])) {
                $logs = $log_controller->get_template($_GET['t_id']);
            } else if (isset($_GET['module'])) {
                if (!isset($_GET['ids']) || !isset($_GET['modules'])) {
                    throw new Exception("IDs or modules not found.");
                }
                $ids = json_decode($_GET['ids']);
                $modules = json_decode($_GET['modules']);
                
                $logs = $log_controller->get_module_logs($_GET['module'], $ids, $modules);
            } else {
                $logs = $log_controller->get_logs();
            }
            echo json_encode($logs);
            break;
        case "POST":
            if (!isset($_POST['data'])) {
                throw new Exception("Client not found.");
            }

            $data = json_decode($_POST['data'], 1);
            extract($data);

            $result = $log_controller->insert_log($action, $module, $logger, $record_id);

            echo json_encode([
                "acknowledged" => $result,
            ]);
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
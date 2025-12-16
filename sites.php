<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once __DIR__ . "/config/env.php";
require_once "./config/site.controller.php";
require_once "./functions.php";
// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$site_controller = new SiteController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['type'])) {
                if ($_GET['type'] === "landmarks") {
                    $landmarks = $site_controller->get_landmarks();
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode($landmarks);
                    break;
                }
                if ($_GET['type'] === "last_date") {
                    $sites = $site_controller->get_last_date_inserted();
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode($sites);
                    break;
                }
                if ($_GET['type'] === "override") {
                    $sites = $site_controller->get_overriden_contracts();
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode($sites);
                    break;
                }
                if ($_GET['type'] === "latest") {
                    $unis_site_controller = new SiteController(server: UNIS_SERVER, dbname: UNIS_DB, username: UNIS_USERNAME, password: UNIS_PASSWORD);
                    $sites = $unis_site_controller->get_latest_sites();
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode($sites);
                    break;
                }

            }
            $sites = $site_controller->get_sites();
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            sendJsonResponse($sites);
            break;
        case "POST":
            if (!isset($_POST['data'])) {
                throw new Exception("Data not found.");
            }

            $data = json_decode($_POST['data'], true);

            if (isset($_POST['type'])) {
                $result = $site_controller->insert_site($data['columns'], $data['values']);
            } else {
                $result = $site_controller->override_contract_end_date($data);
            }
            echo json_encode([
                "acknowledged" => $result
            ]);
            break;
        case "PUT":
            $data = json_decode(file_get_contents('php://input'), true);

            if ($data === null) {
                throw new Exception('Data not found.');
            }
            extract($data);
            switch ($type) {
                case "site":
                    if (!isset($site_id)) {
                        throw new Exception("Site code missing.");
                    }
                    $result = $site_controller->update_site($columns, $values, "ID = ?", [$site_id]);
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => $results
                    ]);
                    break;
                case "remarks":
                    if (!isset($site_code)) {
                        throw new Exception("Site code missing.");
                    }
                    $result = $site_controller->update_remarks($remarks, $site_code);
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => $results
                    ]);
                    break;
                case "price":
                    if (!isset($site_code)) {
                        throw new Exception("Site code missing.");
                    }
                    $result = $site_controller->update_price($price, $site_code);
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => $results
                    ]);
                    break;
                case "manage":
                    if (!isset($site_code)) {
                        throw new Exception("Site code missing.");
                    }
                    $result = $site_controller->update_status($newStatus, $site_code);
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => $results
                    ]);
                    break;
                default:
                    throw new Exception("Type not found.");
            }
        case "PATCH":
        case "DELETE":
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
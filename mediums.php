<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/medium.controller.php";
require_once "./config/log.controller.php";
require_once "./functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$medium_controller = new MediumController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['with'])) {
                $with = $_GET['with'];
                if ($with === "companies") {
                    $result = $medium_controller->get_mediums_with_companies();

                    $mediums = [];
                    foreach ($result as $medium) {
                        $medium_id = $medium->medium_id;

                        if (!isset($mediums[$medium_id])) {
                            $mediums[$medium_id] = [
                                "company_medium_id" => $medium->company_medium_id,
                                "ID" => $medium->medium_id,
                                "name" => $medium->medium_name,
                                "companies" => []
                            ];
                        }
                        // Add the company to the 'companies' list for this medium
                        if (isset($medium->company_id)) {
                            $mediums[$medium_id]["companies"][] = [
                                "ID" => $medium->company_id,
                                "code" => $medium->code,
                                "name" => $medium->name
                            ];
                        }
                    }

                    $mediums = array_values($mediums);
                } elseif ($with === "company") {
                    if (!isset($_GET['id'])) {
                        throw new Exception("ID not found.");
                    }
                    $mediums = $medium_controller->get_company_mediums($_GET['id']);
                }
            } else {
                $mediums = $medium_controller->get_mediums();
            }

            echo json_encode($mediums);
            break;
        case "POST":
            if (!isset($_POST['data'])) {
                throw new Exception("Data not found.");
            }
            $data = json_decode($_POST['data']);

            switch ($_GET['type']) {
                case "insert":
                    $result = [];
                    foreach ($data as $medium) {
                        array_push($result, intval($medium_controller->insert_medium($medium->name, $medium->company, $_POST['id'])));
                    }
                    break;
            }

            echo json_encode(["acknowledged" => !in_array(0, $result)]);
            break;
        case "PUT":
            $contents = json_decode(file_get_contents('php://input'), true);

            if (is_null($contents)) {
                throw new Exception('Data not found.');
            }
            extract($contents);

            switch ($_GET['type']) {
                case "update":
                    extract(json_decode($data, 1));

                    $old_name = $name['old'] === $name['new'] ? null : $name['new'];
                    $company_changes = [
                        "removed" => [],
                        "added" => []
                    ];

                    foreach ($companies[0] as $old) {
                        if (!in_array($old, $companies[1])) {
                            array_push($company_changes['removed'], $old);
                        }
                    }
                    foreach ($companies[1] as $new) {
                        if (!in_array($new, $companies[0])) {
                            array_push($company_changes['added'], $new);
                        }
                    }

                    $result = $medium_controller->update_medium($ID, $name['new'], $id, $company_changes['removed'], $company_changes['added'], $old_name);
                    echo json_encode(["acknowledged" => $result]);
                    break;

            }
            break;
        case "DELETE":
            if (!isset($_GET['id'])) {
                throw new Exception('ID not found.');
            }
            $result = $medium_controller->delete_medium($_GET['id']);
            echo json_encode(["acknowledged" => $result]);
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
<?php

use function PHPSTORM_META\exitPoint;

header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/client.controller.php";
require_once "./config/log.controller.php";
require_once "./functions.php";
require_once "./misc.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$client_controller = new ClientController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET["key"])) {
                $clients = filter_clients($clients, $_GET["key"], $_GET['user_id']);
            } elseif (isset($_GET['misc'])) {
                $clients = $client_controller->get_client_miscellaneous();
            } elseif (isset($_GET['user'])) {
                $clients = $client_controller->get_client_by_user($_GET['user']);
            } elseif (isset($_GET['by_unit'])) {
                $clients = $client_controller->get_clients_by_unit();
            } elseif (isset($_GET['count'])) {
                $user_scope = get_user_client_scope();
                $clients = $client_controller->get_monthly_client_count($user_scope);
            } else {
                $clients = isset($_GET["id"]) ? generate_clients($_GET["id"]) : generate_client_preview();
            }
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            if (!ob_start("ob_gzhandler"))
                ob_start();
            header('Content-Encoding: gzip');

            // Output compressed JSON
            echo json_encode($clients);
            break;
        case "POST":
            if (isset($_GET["type"])) {
                switch ($_GET['type']) {
                    case 'insert':
                        if (!isset($_POST['data'])) {
                            throw new Exception("Client not found.");
                        }

                        $data = json_decode($_POST['data'], 1);

                        extract($data);

                        // var_dump($data);

                        [$result, $id] = $client_controller->insert_client($name, $company, $sales_unit, $account_executive, $status, $type, $source, $mediums, $contact_person, $designation, $contact_number, $email_address, $address, $industry, $brand);
                        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                        header("Pragma: no-cache");
                        header("Expires: 0");
                        echo json_encode([
                            "acknowledged" => (bool) $result,
                            "id" => $id
                        ]);
                        break;
                    case 'batch':
                        if (!isset($_POST['data'])) {
                            throw new Exception("Client not found.");
                        }


                        $data = json_decode($_POST['data'], 1);
                        $resultArray = [];

                        foreach ($data as $value) {
                            extract(array: $value);
                            $result = $client_controller->insert_client($client, $company, $sales_unit, $account_executive, $status, $type, $source, $mediums, $contact_person, $designation, $contact_number, $email_address, $address, $industry, $brand);
                            array_push($resultArray, (int) $result);
                        }
                        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                        header("Pragma: no-cache");
                        header("Expires: 0");
                        echo json_encode([
                            "acknowledged" => (bool) !in_array(0, $resultArray),
                            "id" => 0
                        ]);
                        break;
                    default:
                        throw new Exception("Type not found.");
                }
            }

            break;
        case "PUT":
            $contents = json_decode(file_get_contents('php://input'), true);

            if (is_null($contents)) {
                throw new Exception('Data not found.');
            }
            extract($contents);


            switch ($action) {
                case "update": {
                    $client = json_decode($data, 1);

                    extract($client);
                    $log_result = true;

                    foreach ($client_map as $item) {
                        $is_parent = $item['parent_id'] === "ID";
                        $parent_id = $is_parent ? null : $item['parent_id'];
                        $key = $item['label'] ?? $item['column'];

                        if ($item['value_type'] !== "IDs") {

                            $new_value = $client[$key];
                            $old_record = $client_controller->get_old_value($item['table'], $item['column'], $id, $is_parent, $parent_id);
                            $old_value = $old_record[$item['column']];
                            $column_id = $old_record['ID'];

                            if ($item['value_type'] === "ID") {
                                $old_value = $client_controller->get_client_information_value($item['column_table'], $old_value);
                                $new_value = $client_controller->get_client_information_value($item['column_table'], $client[$key]);
                            }
                            if ($old_value !== $new_value) {
                                $row_id = $column_id ?? $id;
                                $column_label = $item['label'] ?? $item['column'];
                                $column_label = str_replace("_", " ", $column_label);
                                $log_result = $client_controller->check_column_changes($item['table'], $row_id, $new_value, $old_value, $column_label, $logger);
                            }
                        }

                        if ($item['value_type'] === "IDs") {
                            $old_records = $client_controller->get_old_value($item['table'], $item['column'], $id, false, $parent_id, true);
                            /**
                             * returns [
                             * ID => 1,
                             * medium_id = 2
                             * ]
                             */
                            $new_records = $client[$item['label']]; // [1,2,3,4] medium ids

                            $old_record_values = array_map(function ($record) use ($client_controller, $item) {
                                return $client_controller->get_client_information_value($item['column_table'], $record[$item['column']]);
                            }, $old_records);

                            $new_record_values = array_map(function ($new_record) use ($client_controller, $item) {
                                return $client_controller->get_client_information_value($item['column_table'], $new_record);

                            }, $new_records);


                            $changes = array_diff($new_record_values, $old_record_values);
                            if (count($changes) !== 0) {
                                $before = implode(", ", $old_record_values);
                                $after = join(", ", $new_record_values);

                                $log_result = $client_controller->check_column_changes($item['table'], $id, $after, $before, $item['label'], $logger);
                            }
                        }
                    }
                    $result = $client_controller->update_client($id, $name, $company, $sales_unit, $account_executive, $status, $type, $source, $mediums, $contact_person, $designation, $contact_number, $email_address, $address, $industry, $brand);
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => (bool) $result && $log_result,
                    ]);
                    break;
                }
                case "status": {

                    $response = $client_controller->update_status($id, $status, $logger);
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => (bool) $response,
                    ]);
                    break;
                }
                default:
                    throw new Exception("Type not found.");
            }
            break;
        case "DELETE":
            if (!isset($_GET['id'])) {
                throw new Exception('ID not found.');
            }
            $result = $client_controller->delete_client($_GET['id']);
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode(["acknowledged" => $result]);
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}


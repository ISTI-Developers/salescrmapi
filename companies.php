<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/company.controller.php";
require_once "./config/user.controller.php";
require_once "./functions.php";

$company_controller = new CompanyController();
$user_controller = new UserController();

validate_bearer_token();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            // Check if an ID is provided
            if (isset($_GET['id'])) {
                $company = $company_controller->get_companies($_GET['id']);
                echo json_encode($company);
                break;
            }

            $companies = isset($_GET['sales_units_summary']) ? generate_sales_units() : $company_controller->get_companies();
            // Check if sales units summary is requested

            if (empty($companies)) {
                throw new Exception('Companies not found.');
            }

            echo json_encode($companies);
            break;

        case "POST":
            if (!isset($_POST['name'])) {
                throw new Exception('Company name is empty.');
            }
            $status = [];

            $company_id = $company_controller->insert_company($_POST['code'], $_POST['name']);


            if (isset($_POST['sales_units'])) {
                $sales_units = json_decode($_POST['sales_units'], 1);

                foreach ($sales_units as $unit) {
                    extract($unit); //unit_name, unit_head amd unit_members are extracted;
                    //run insert sales unit and its head
                    $sales_unit_id = $company_controller->insert_sales_unit($company_id, $unit_name, $unit_head['id']);
                    //run insertion of sales members
                    foreach ($unit_members as $member) {
                        $result = $user_controller->update_users_sales_unit($company_id, $sales_unit_id, $member['id']);
                        array_push($status, $result ? 1 : 0);
                    }
                }

                echo json_encode([
                    "acknowledged" => !in_array(0, $status),
                    "id" => $company_id
                ]);

                break;
            } else {
                echo json_encode([
                    "acknowledged" => $company_id
                ]);

                break;
            }
        case "PUT":
            //throw an error if user_id is missing
            if (!isset($_GET['id'])) {
                throw new Exception('User ID not found.');
            }

            //extract the data from the request body
            $data = json_decode(file_get_contents('php://input'), true);
            if (is_null($data)) {
                throw new Exception('Data not found.');
            }
            extract($data);

            $updateStatus = [];

            if ($company_controller->update_company($code, $name, $_GET['id'])) {
                if (isset($sales_units)) {
                    foreach ($sales_units as $unit) {
                        extract($unit);
                        $result = $company_controller->update_sales_unit($_GET['id'], $unit_name, $unit_head['id'], $temp_id);
                        if ($result === "true") {
                            array_push($updateStatus, 1);
                            foreach ($unit_members as $member) {
                                $res = $user_controller->update_users_sales_unit($_GET['id'], $temp_id, $member['id']);
                                array_push($updateStatus, $res ? 1 : 0);
                            }
                        } else {
                            foreach ($unit_members as $member) {
                                $response = $user_controller->update_users_sales_unit($_GET['id'], $result, $member['id']);
                                array_push($updateStatus, $response ? 1 : 0);
                            }
                        }
                    }
                }
            }
            echo json_encode([
                "acknowledged" => !in_array(0, $updateStatus),
                "id" => $_GET['id']
            ]);

            break;
        case "DELETE":

            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

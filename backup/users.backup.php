<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/table.controller.php";
require_once "./config/company.controller.php";
require_once "./config/authController.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}


$table_controller = new TableController();
$company_controller = new CompanyController();
$auth_controller = new AuthController();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            //fetch the users

            $users = $table_controller->getAllUsers();

            foreach ($users as $key => $user) {
                $company = $company_controller->fetchCompany($user->company);
                $sales_group = $table_controller->fetchSalesUnit($user->sales_group);
                $access = $auth_controller->fetchUserAccess($user->role);

                // Update the current user entry in the users array
                $users[$key]->company = $company ?? null;
                $users[$key]->sales_group = $sales_group ?? null;
                $users[$key]->role = [
                    "id" => $access->user_level_id,
                    "name" => $access->user_level_access_description,
                    "access_type" => $access->access_type
                ];
            }


            if (isset($_GET['type'])) {
                switch ($_GET['type']) {
                    case "sales-members-in-company":
                        if (!isset($_GET['company_id'])) {
                            throw new Exception('Company ID is empty.');
                        }
                        $users = $table_controller->fetchAllSalesMembersInCompany($_GET['company_id']);
                        break;
                    case "sales-members-all":
                        $users = $table_controller->fetchAllMembers();
                        break;
                    case "sales-members-available":
                        $users = $table_controller->fetchAllAvailableSalesMember();
                        break;
                    case "sales-group-members":
                        if (!isset($_GET['sales_group_id'])) {
                            throw new Exception('Sales Group ID is empty.');
                        }
                        $users = $table_controller->fetchSalesGroupMembers($_GET['sales_group_id']);
                        break;
                    case "sales-groups":
                        $users = $table_controller->fetchAllSalesGroup();
                        break;
                    case "sales-group-heads":
                        if (!isset($_GET['company_id'])) {
                            throw new Exception('Company ID is empty.');
                        }
                        $users = $table_controller->fetchSalesGroupHeadForSelection($_GET['company_id']);
                        break;
                    case "roles":
                        $users = $table_controller->fetchUserRoles();
                        break;
                    case "user":
                        if (!isset($_GET['user_id'])) {
                            throw new Exception('User ID is empty.');
                        }
                        $users = $table_controller->fetchUserInfo($_GET['user_id']);
                        break;
                    // case "account-executives":
                    //     if (!isset($_GET['sales_group_id'])) {
                    //         throw new Exception('Sales Group ID is empty.');
                    //     }
                    //     $users = $table_controller->fetchAccountExecutives($_GET['sales_group_id']);
                    //     break;
                }
            }
            echo json_encode($users);
            break;
        case "POST":
            if (!isset($_POST['reg_id'])) {
                throw new Exception('Registration token not found. Please contact the developer.');
            }

            $user = json_decode($_POST['user'], 1);
            extract($user);

            $hashed_password = md5($password);

            $access_level = $role['id'];


            if ($login_id = $table_controller->insertUserLoginDetails($username, $hashed_password, $email_address, $status, $access_level)) {
                if ($table_controller->insertUserInfo($first_name, $middle_name, $last_name, $login_id)) {
                    $message = file_get_contents('./email_templates/registration.php');
                    $message = str_replace("[name]", $first_name, $message);
                    $message = str_replace("[username]", $username, $message);
                    $message = str_replace("[temporary_password]", $password, $message);
                    if ($response = $table_controller->sendMail("Your Sales CRM Dashboard Account Has Been Created", $message, $email_address, $first_name . " " . $last_name)) {
                        echo json_encode([
                            "acknowledged" => true
                        ]);
                    } else {
                        throw new Exception("Error in sending the user's temporary password. Please contact the developer.");

                    }
                } else {
                    throw new Exception("Error in saving the user's personal information. Please contact the developer.");

                }
            } else {
                throw new Exception("Error in creating the user's account. Please contact the developer.");
            }



            break;
        case "PUT":

            //throw an error if user_id is missing
            if (!isset($_GET['user_id'])) {
                throw new Exception('User ID not found.');
            }
            //extract the data from the request body
            $data = json_decode(file_get_contents('php://input'), true);
            extract($data);

            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case "password_update":
                        $hashed_password = md5($password);
                        $response = $table_controller->updatePassword($hashed_password, $_GET['user_id']);
                        echo json_encode([
                            "acknowledged" => $response
                        ]);
                        break;
                    case "role_update":
                        $response = $table_controller->updateUserRole($_GET['user_id'], $role);
                        echo json_encode([
                            "acknowledged" => $response
                        ]);
                        break;
                    case "information_update":
                        extract($user);

                        $response = $table_controller->updateUser($user_id, $first_name, $middle_name, $last_name, $email_address, $username, $role['id']);
                        echo json_encode([
                            "acknowledged" => $response
                        ]);
                        break;
                    case "status_update":
                        $login_id = $_GET['user_id'];

                        if ($response = $table_controller->updateUserStatus($login_id, $status)) {
                            echo json_encode([
                                "acknowledged" => $response
                            ]);
                        }
                        break;
                }
            }

            break;
        case "DELETE":
            //throw an error if id is missing
            if (!isset($_GET['id'])) {
                throw new Exception('ID not found.');
            }

            if ($response = $table_controller->deleteUser($_GET['id'])) {
                echo json_encode([
                    "acknowledged" => $response
                ]);
            }
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

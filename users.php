<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/user.controller.php";
require_once "./config/company.controller.php";
require_once "./config/auth.controller.php";
require_once "functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$user_controller = new UserController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            // Fetch based on 'type' if set
            if (isset($_GET['type'])) {
                switch ($_GET['type']) {
                    case 'user':
                        if (empty($_GET['user_id'])) {
                            throw new Exception('User ID is empty.');
                        }
                        $user = $user_controller->get_users($_GET['user_id']);
                        echo json_encode(generate_user_information($user));
                        break;

                    case 'sales_units':
                        echo json_encode($user_controller->get_sales_units());
                        break;

                    case 'roles':
                        echo json_encode(generate_role_object(with_description: true));
                        break;

                    case 'available_sales_members':
                        echo json_encode($user_controller->get_available_sales_members());
                        break;

                    default:
                        throw new Exception('Invalid type specified.');
                }
                break;
            }

            // Fallback: Fetch one user by ID or all users
            $users = [];

            if (isset($_GET['id'])) {
                $user = $user_controller->get_users($_GET['id']);
                $users = generate_user_information($user);
            } else {
                $raw_users = $user_controller->get_users();
                foreach ($raw_users as $user) {
                    $users[] = generate_user_information($user);
                }
            }

            echo json_encode($users);
            break;
        case "POST":
            if (!isset($_POST['reg_id'])) {
                throw new Exception('Registration token not found. Please contact the developer.');
            }

            $user = json_decode($_POST['user']);

            $user->role_id = $user->role->role_id;

            if ($new_id = $user_controller->create_user((array) $user)) {
                extract((array) $user);

                $message = file_get_contents('./email_templates/registration.php');
                $message = str_replace("[name]", $first_name, $message);
                $message = str_replace("[username]", $username, $message);
                $message = str_replace("[temporary_password]", $password, $message);
                if ($response = $user_controller->sendMail("Your Sales CRM Dashboard Account Has Been Created", $message, $email_address, $first_name . " " . $last_name)) {
                    unset($user);
                    echo json_encode([
                        "acknowledged" => true,
                        "id" => $new_id
                    ]);
                } else {
                    throw new Exception("Error in sending the user's temporary password. Please contact the developer.");

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
            if (is_null($data)) {
                throw new Exception('Data not found.');
            }
            extract($data);

            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case "password_update":
                        $hashed_password = md5($password);
                        $response = $user_controller->update_password($hashed_password, $_GET['user_id']);
                        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                        header("Pragma: no-cache");
                        header("Expires: 0");
                        echo json_encode([
                            "acknowledged" => $response,
                            "id" => $response
                        ]);
                        break;
                    case "role_update":
                        $response = $user_controller->update_role($_GET['user_id'], $role);
                        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                        header("Pragma: no-cache");
                        header("Expires: 0");
                        echo json_encode([
                            "acknowledged" => $response,
                            "id" => $response
                        ]);
                        break;
                    case "information_update":
                        if (!isset($user)) {
                            throw new Exception("User data not found. Please contact the developer.");
                        }
                        $user = $data["user"];

                        extract($user);

                        $response = $user_controller->update_user($ID, $first_name, $middle_name, $last_name, $email_address, $username, $role['role_id'], $company['ID']);
                        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                        header("Pragma: no-cache");
                        header("Expires: 0");
                        echo json_encode([
                            "acknowledged" => $response,
                            "id" => $ID,
                        ]);
                        break;
                    case "status_update":
                        $user_id = $_GET['user_id'];

                        if ($response = $user_controller->update_status($user_id, $role_id)) {
                            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                            header("Pragma: no-cache");
                            header("Expires: 0");
                            echo json_encode([
                                "acknowledged" => $response,
                                "id" => $user_id
                            ]);
                        }
                        break;
                }
            }
            break;
        case "DELETE":
            $user_id = $_GET['id'];

            if ($response = $user_controller->update_status($user_id, 5)) {
                echo json_encode([
                    "acknowledged" => $response,
                    "id" => $user_id
                ]);
            }
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

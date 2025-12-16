<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once __DIR__ . "/config/auth.controller.php";
require_once __DIR__ . "/config/table.controller.php";
require_once __DIR__ . "/config/company.controller.php";
require_once __DIR__ . "/config/settings.controller.php";
require_once __DIR__ . "/functions.php";

$auth_controller = new AuthController();
$settings_controller = new SettingsController();
$company_controller = new CompanyController();

try {
    switch ($_REQUEST['type']) {
        case "login":
            if ($_POST['username'] === "" || $_POST['password'] === "") {
                throw new Exception("Username or password must not be empty.");
            }
            $username = $_POST['username'];
            $password = $_POST['password'];

            $password = md5($password);

            $user = $auth_controller->login_account($username, $password);

            if (!$user) {
                throw new Exception("Invalid username or password. Please try again.");
            }

            $token = create_JWT();
            if ($auth_controller->insert_auth_token($user->ID, $token)) {
                $current_user = generate_user_information($user, true, $token);
                echo json_encode($current_user);
            }


            break;
        case "verify":
            try {
                if (!isset($_REQUEST['token']) || !isset($_REQUEST['ID'])) {
                    throw new Exception("Token or ID not found.");
                }

                $result = $auth_controller->verify_password($_REQUEST['ID'], $_REQUEST['token']);
                $advisory = $settings_controller->get_advisory();

                if ($result) {
                    $auth_controller->update_last_login($_REQUEST['ID']);
                }

                echo json_encode([
                    "acknowledged" => boolval($result),
                    "item" => $advisory
                ]);
            } catch (Exception $e) {
                http_response_code(400); // Bad Request or change to 500 if internal error
                echo json_encode([
                    "acknowledged" => false,
                    "error" => $e->getMessage()
                ]);
            }
            break;


        case "validate_email":
            if (!isset($_REQUEST['email_address'])) {
                throw new Exception("Email address not found.");
            }

            if ($result = $auth_controller->validate_email($_REQUEST['email_address'])) {
                $code = get_code();
                $message = file_get_contents('./email_templates/verification_code.php');
                if ($message) {
                    $message = str_replace("[name]", $result->first_name, $message);
                    $message = str_replace("[code]", $code, $message);
                    if ($response = $auth_controller->sendMail("Email Verification Code", $message, $result->email_address, $result->first_name)) {
                        if ($auth_controller->insert_code($code, $result->ID)) {
                            echo json_encode($result);
                        }
                        break;
                    } else {
                        throw new Exception("Error in sending code. Please contact the developer.");

                    }
                } else {
                    throw new Exception("Template not found.");

                }
            } else {
                throw new Exception("Email validation error. Please contact the IT department");
            }

        case "validate_code":
            if (!isset($_REQUEST["code"])) {
                throw new Exception("Code not found.");
            }

            if ($result = $auth_controller->verify_code($_REQUEST["code"], $_REQUEST['ID'], $_REQUEST['password'])) {
                $message = file_get_contents('./email_templates/password_reset.php');
                if ($message) {
                    $message = str_replace("[name]", $result->first_name, $message);
                    $message = str_replace("[temporary_password]", $_REQUEST['password'], $message);

                    if ($response = $auth_controller->sendMail("Your Password Has Been Reset", $message, $result->email_address, $result->first_name)) {
                        echo json_encode([
                            "acknowledged" => true
                        ]);
                        break;
                    } else {
                        throw new Exception("Error in sending the user's temporary password. Please contact the developer.");

                    }
                } else {
                    throw new Exception("Template not found.");
                }
            } else {
                throw new Exception("Invalid code. Please check your inbox or spam for the code.");
            }
        case "online":
            $result = $auth_controller->get_online_users();
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo json_encode($result);
            break;
        case "logout":
            if (!isset($_REQUEST['ID'])) {
                throw new Exception("ID not found");
            }
            $result = $auth_controller->logout_user($_REQUEST['ID']);
        case "reset":
            if (!isset($_REQUEST['password']) || !isset($_REQUEST['ID'])) {
                throw new Exception("ID not found.");

            }
            $password = md5($_REQUEST['password']);
            if ($user = $auth_controller->reset_password($_REQUEST['ID'], $password)) {
                $message = file_get_contents('./email_templates/password_reset.php');
                if ($message) {
                    $message = str_replace("[name]", $user->first_name, $message);
                    $message = str_replace("[temporary_password]", $_REQUEST['password'], $message);

                    if ($response = $auth_controller->sendMail("Your Password Has Been Reset", $message, $user->email_address, $user->first_name . " " . $user->last_name)) {
                        echo json_encode([
                            "acknowledged" => true,
                            "id" => $user->ID,
                        ]);
                        break;
                    } else {
                        throw new Exception("Error in sending the user's temporary password. Please contact the developer.");

                    }
                } else {
                    throw new Exception("Template not found.");

                }
            } else {
                throw new Exception("Database query error. Please contact the developer.");

            }
        default:
            throw new Exception("Request type not found!" . $_REQUEST['type']);
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

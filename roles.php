<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/role.controller.php";
require_once "./config/user.controller.php";
require_once "functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$role_controller = new RoleController();
$user_controller = new UserController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['modules'])) {
                $roles = $role_controller->get_modules();
            } else if (isset($_GET['company'])) {
                $roles = isset($_GET['id']) ? $role_controller->get_company_roles() : $role_controller->check_company_roles($_GET['id']);
            } else {
                if (isset($_GET["user_id"])) {
                    $user = $user_controller->get_users($_GET['user_id']);
                    $results = $role_controller->get_role_permissions($user->role_id);
                } else {
                    $results = $role_controller->get_role_permissions($_GET['id'] ?? null);
                }

                $roles = get_role_permissions($results);

                // If fetching a single role, return just that object
                if (isset($_GET["user_id"]) || isset($_GET['id'])) {
                    $roles = !empty($roles) ? $roles[0] : null;
                }
            }

            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");

            sendJsonResponse($roles);
            break;
        case "POST":
            if (isset($_GET["type"])) {
                switch ($_GET['type']) {
                    case "module":
                        $result = $role_controller->insert_module($_POST['name']);
                        echo json_encode([
                            "acknowledged" => $result,
                            "id" => $result
                        ]);
                        break;
                    case "role":
                        if (is_null($_POST['role'])) {
                            throw new Exception('Data not found.');
                        }
                        $result_status = [];
                        $role = json_decode($_POST['role'], 1);
                        extract($role);

                        if ($role_id = $role_controller->insert_role($name, $description)) {
                            foreach ($permissions as $permission) {
                                $result = $role_controller->insert_role_permission($role_id, $permission);

                                array_push($result_status, $result ? 1 : 0);

                            }
                        }

                        echo json_encode([
                            "acknowledged" => !in_array(0, $result_status),
                            "id" => $role_id
                        ]);
                        break;
                    default:
                        throw new Exception("Type not found.");
                }
            }

            break;
        case "PUT":
            $data = json_decode(file_get_contents('php://input'), true);

            if (is_null($data)) {
                throw new Exception('Data not found.');
            }
            extract($data);

            // var_dump($data);

            switch ($type) {
                case "toggle":
                    $result = $role_controller->toggle_module($id, $status);
                    echo json_encode([
                        "acknowledged" => (bool) $result,
                        "id" => $id
                    ]);
                    break;
                case "update_company":
                    $result = $role_controller->update_company_role($company_id, $permission);
                    echo json_encode([
                        "acknowledged" => (bool) $result,
                        "id" => 0
                    ]);
                    break;
                case "update":
                    $result_status = [];
                    // var_dump($data);
                    if ($role_controller->update_role($role_id, $name, $description)) {
                        $role_permissions = $role_controller->get_role_permissions($role_id);
                        $existing_permissions = array_map(fn($item) => $item->permission, $role_permissions);

                        // Normalize to arrays for comparison
                        $existing = is_array($existing_permissions) ? $existing_permissions : [];
                        $new = is_array($permissions) ? $permissions : [];

                        // Determine what to add and what to remove
                        $to_add = array_diff($new, $existing);
                        $to_remove = array_diff($existing, $new);

                        // var_dump($existing);
                        // var_dump($new);

                        // var_dump($to_add);
                        // var_dump($to_remove);
                        // Add new permissions
                        foreach ($to_add as $permission) {
                            $result = $role_controller->insert_role_permission($role_id, $permission);
                            array_push($result_status, $result ? 1 : 0);
                        }

                        // Remove old permissions
                        foreach ($to_remove as $permission) {
                            $result = $role_controller->update_role_permission($role_id, $permission);
                            array_push($result_status, $result ? 1 : 0);
                        }
                    }

                    echo json_encode([
                        "acknowledged" => !in_array(0, $result_status),
                        "id" => $role_id
                    ]);


                    break;
                case "manage":
                    $result = $role_controller->manage_role($id, $status);
                    echo json_encode([
                        "acknowledged" => (bool) $result,
                        "id" => $id
                    ]);
                    break;
                default:
                    throw new Exception("Type not found.");
            }
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

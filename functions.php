<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once __DIR__ . "/config/role.controller.php";
require_once __DIR__ . "/config/user.controller.php";
require_once __DIR__ . "/config/company.controller.php";
require_once __DIR__ . "/config/client.controller.php";
require_once __DIR__ . "/config/env.php";

function generate_user_information($data, $with_password = false, $token = null)
{
    $user = new UserController();
    $company = new CompanyController();
    $role = new RoleController();

    $user_data = $user->get_users($data->ID, $with_password);
    $results = $role->get_role_permissions($user_data->role_id);
    $roles = get_role_permissions($results);

    $company_info = $company->get_companies($user_data->company_id);
    $sales_unit_info = $user_data->sales_unit_id ? $user->get_sales_units($user_data->sales_unit_id) : null;

    $current_user = [
        ...(array) $user_data,
        "company" => $company_info ?? null,
        "sales_unit" => $sales_unit_info ?? null,
        "role" => $roles[0],
    ];

    if ($token !== null) {
        $current_user['token'] = $token;
    }

    unset($current_user['company_id']);
    unset($current_user['role_id']);
    unset($current_user['sales_unit_id']);
    return $current_user;
}

function get_role_permissions($results)
{
    if (!is_object($results) && !is_array($results)) {
        return [];
    }

    // Convert to array if it's an object
    $results = (array) $results;

    $roles = [];

    foreach ($results as $perm) {
        $perm = (array) $perm;
        // Try to find existing role
        $key = array_search($perm['role_id'], array_column($roles, 'role_id'));

        if ($key === false) {
            // Role not found, create new

            $roles[] = [
                'role_id' => $perm['role_id'],
                'name' => $perm['name'], // adjust if your result has role name
                'description' => $perm['description'],
                'status' => $perm['status'],
                'permissions' => $perm['permission'] !== null ? [$perm['permission']] : []
            ];
        } else {
            // Role exists, just append permission
            $roles[$key]['permissions'][] = $perm['permission'];
        }
    }

    return $roles;
}

function generate_role_object($role_id = null, $with_description = false)
{
    $role = new RoleController();
    $rows = $role->get_roles($role_id, $with_description);

    $roles = [];

    foreach ($rows as $row) {
        $module = [
            "m_id" => $row->module_id,
            "name" => $row->name,
            "permissions" => [
                $row->can_view,
                $row->can_add,
                $row->can_edit,
                $row->can_delete
            ],
        ];

        if (!isset($roles[$row->role_id])) {
            $roles[$row->role_id] = [
                "role_id" => $row->role_id,
                "name" => $row->role,
                'status_id' => $row->status_id,
                'status' => $row->status,
                "access" => []
            ];
            if ($with_description) {
                $roles[$row->role_id]["description"] = $row->description;
            }
        }

        // Add this module to the role's modules list
        $roles[$row->role_id]['access'][] = $module;
    }

    return array_values($roles);

}

function get_user_permissions($permission)
{
    $role_controller = new RoleController();
    $headers = getallheaders();

    if (!isset($headers["X-User-Id"])) {
        return false;
    }

    return $role_controller->get_user_permissions($headers["X-User-Id"], $permission);
}

function get_user_client_scope(): string
{
    $user_controller = new UserController();
    $headers = getallheaders();

    if (!isset($headers["X-User-Id"])) {
        return false;
    }

    $user = $user_controller->get_users($headers["X-User-Id"]);

    if ($user->role_id === 1) {
        return 'all';
    } else {
        return $user->company_id;
    }
}

function generate_clients($id = null)
{
    $client_controller = new ClientController();

    $clients = $client_controller->get_clients($id);
    $clients = is_array($clients) ? $clients : [$clients];

    $processed_clients = [];

    foreach ($clients as $client) {
        $client = (array) $client;

        $industry = $client_controller->get_client_miscellaneous("industry", $client['industry']);
        $type = $client_controller->get_client_miscellaneous("type", $client['type']);
        $source = $client_controller->get_client_miscellaneous("source", $client['source']);
        $status = $client_controller->get_client_miscellaneous("status", $client['status']);
        $mediums = $client_controller->get_client_mediums($client['client_id']);

        $processed_clients[] = [
            ...$client,
            "industry_name" => $industry->name ?? "---",
            "type_name" => $type->name ?? "---",
            "source_name" => $source->name ?? "---",
            "status_name" => $status->name ?? "---",
            "mediums" => $mediums
        ];
    }

    $client_statuses = ["ACTIVE", "HOT", "ON/OFF", "FOR ELECTIONS", "POOL"];

    // group clients by client_id
    $grouped_clients = [];
    foreach ($processed_clients as $client) {
        $client_id = $client['client_id'];

        if (!isset($grouped_clients[$client_id])) {
            // initialize the client info (only once per client_id)
            $grouped_clients[$client_id] = [
                ...$client,
                "account_executives" => [] // where we’ll push account executives
            ];
        }

        // add account executive details to accounts table
        $grouped_clients[$client_id]['account_executives'][] = [
            "account_id" => $client['account_id'] ?? null,
            "account_executive" => $client['account_executive'] ?? null,
            "alias" => $client['account_code'] ?? null,
            "sales_unit" => $client['account_su'] ?? null,
            "sales_unit_id" => $client['account_su_id'] ?? null
        ];
    }


    // flatten back to indexed array
    $final_clients = array_values($grouped_clients);
    foreach ($final_clients as &$client) {
        unset($client['account_executive']);
        unset($client['account_id']);
        unset($client['account_code']);
        unset($client['account_su']);
        unset($client['account_su_id']);
    }
    unset($client);

    // sort by statuses + name as before
    usort($final_clients, function ($item_a, $item_b) use ($client_statuses) {
        $a = array_search($item_a['status_name'], $client_statuses);
        $b = array_search($item_b['status_name'], $client_statuses);

        if ($a !== $b) {
            return $a - $b;
        }
        return strcmp($item_a['name'], $item_b['name']);
    });

    return $final_clients[0];
}

function generate_client_preview()
{

    $client_controller = new ClientController();
    $user_scope = get_user_client_scope();
    $clients = $client_controller->get_client_preview($user_scope);

    $processed_clients = [];

    foreach ($clients as $client) {
        $client = (array) $client;

        $industry = $client_controller->get_client_miscellaneous("industry", $client['industry']);
        $status = $client_controller->get_client_miscellaneous("status", $client['status']);
        $mediums = $client_controller->get_client_mediums($client['client_id']);

        $processed_clients[] = [
            ...$client,
            "industry_name" => $industry->name ?? "---",
            "status_name" => $status->name ?? "---",
            "mediums" => $mediums
        ];
    }

    $client_statuses = ["ACTIVE", "HOT", "ON/OFF", "FOR ELECTIONS", "POOL"];

    usort($processed_clients, function ($item_a, $item_b) use ($client_statuses) {
        $a = array_search($item_a['status_name'], $client_statuses);
        $b = array_search($item_b['status_name'], $client_statuses);

        //sort initially by status name
        if ($a !== $b) {
            return $a - $b;
        }

        // Secondary sort by name alphabetically if status_name is the same
        return strcmp($item_a['name'], $item_b['name']);

    });
    return $processed_clients;
}


function generate_sales_units()
{
    $company_controller = new CompanyController();
    $user_controller = new UserController();

    $sales_units = $user_controller->get_sales_units();
    $sales_unit_members = $company_controller->get_company_sales_unit_summary();

    $processed_sales_units = [];

    if (count($sales_units) > 0) {
        foreach ($sales_units as $su) {
            $members = [];

            // Filter team members for the current sales unit
            $su_team = array_filter($sales_unit_members, function ($member) use ($su) {
                return isset($member->unit_id) && $member->unit_id === $su->sales_unit_id;
            });

            // Identify the sales unit head
            $su_head = array_filter($su_team, function ($member) {
                return $member->user_id === $member->unit_head_id;
            });

            $su_head = reset($su_head); // Get the first item if exists or null

            // Filter sales unit members excluding the head
            $su_members = array_filter($su_team, function ($member) use ($su) {
                return $member->user_id !== $member->unit_head_id && $su->sales_unit_id === $member->unit_id;
            });

            $company = "";

            // Populate member data
            if (count($su_members) === 0) {
                $company = $su_head->company_name;
            } else {
                foreach ($su_members as $member) {
                    $company = $member->company_name;
                    $members[] = [
                        "user_id" => $member->user_id,
                        "full_name" => $member->full_name
                    ];
                }
            }

            array_push($processed_sales_units, [
                "sales_unit_id" => $su->sales_unit_id,
                "company_id" => $su->company_id,
                "company_name" => $company,
                "sales_unit_name" => $su->unit_name,
                "sales_unit_head" => $su_head ? [
                    "user_id" => $su_head->user_id,
                    "full_name" => $su_head->full_name
                ] : null,
                "sales_unit_members" => $members
            ]);
        }
    }

    return $processed_sales_units;

}
function filter_clients($clients, $filter_type, $filter_value)
{
    return array_filter($clients, function ($client) use ($filter_type, $filter_value) {
        return $client[$filter_type] == $filter_value;
    });
}

function filter_clients_by_sales_unit($clients, $sales_unit_id)
{
    return array_filter($clients, function ($client) use ($sales_unit_id) {
        return $client->sales_unit_id === $sales_unit_id;
    });
}
function filter_clients_by_account_executive($clients, $account_id)
{
    return array_filter($clients, function ($client) use ($account_id) {
        return $client->account_id === $account_id;
    });
}


// FOR AUTHENTICATION AND SECURITY

function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function create_JWT()
{
    $PAYLOAD = [
        'iat' => time(), // Issued at
        'exp' => time() + (60 * 60 * 24) // Expiration time 60mins
    ];
    // Encode Header
    $headerEncoded = base64UrlEncode(json_encode(HEADER));

    // Encode Payload
    $payloadEncoded = base64UrlEncode(json_encode($PAYLOAD));

    // Create Signature
    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", SECRET, true);
    $signatureEncoded = base64UrlEncode($signature);

    // Create JWT
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

function validate_JWT($jwt)
{

    if (empty($jwt) || count(explode('.', $jwt)) < 3)
        return false;


    // Split the JWT into its parts
    [$headerEncoded, $payloadEncoded, $signatureEncoded] = explode('.', $jwt);

    // Decode the header and payload
    $payload = json_decode(base64_decode($payloadEncoded), true);

    // Verify the signature
    $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", SECRET, true);
    $expectedSignatureEncoded = base64UrlEncode($expectedSignature);

    if ($signatureEncoded !== $expectedSignatureEncoded) {
        return false; // Signature verification failed
    }

    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false; // Token has expired
    }

    return $payload['exp'] > time(); // Return the payload if valid
}


function get_bearer_token()
{
    $headers = getallheaders();

    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    return null;
}

function get_code()
{
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function validate_bearer_token()
{
    $bearer_token = get_bearer_token();

    if ($bearer_token === null || $bearer_token === "") {
        throw new Exception('ACCESS FORBIDDEN!');
    }

    if (!validate_JWT($bearer_token)) {
        throw new Exception('Sessions Expired. Please login again.');
    }

    return true;
}


//HELPER FUNCTIONS

function array_diff_assoc_recursive($array1, $array2)
{
    $difference = [];

    foreach ($array1 as $key => $value) {
        if (is_array($value)) {
            if (!isset($array2[$key]) || !is_array($array2[$key])) {
                $difference[$key] = $value;
            } else {
                $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                if (!empty($new_diff)) {
                    $difference[$key] = $new_diff;
                }
            }

        } else if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
            $difference[$key] = $value;
        }
    }

    return $difference;
}


function utf8ize($data)
{
    if (is_array($data)) {
        return array_map('utf8ize', $data);
    } elseif (is_object($data)) {
        foreach ($data as $key => $value) {
            $data->$key = utf8ize($value);
        }
        return $data;
    } elseif (is_string($data)) {
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }
    return $data;
}

function sendJsonResponse($data, $gzipThreshold = 1024)
{
    $json = json_encode($data);

    // Only compress if client supports it AND content is large enough
    if (
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
        strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false &&
        strlen($json) >= $gzipThreshold
    ) {

        // Start gzip buffer
        if (ob_get_level())
            ob_end_clean(); // Clear any previous output
        ob_start();

        header('Content-Encoding: gzip');
        header('Content-Type: application/json');
        $gzipOutput = gzencode($json, 6); // 6 is a good compression level
        header('Content-Length: ' . strlen($gzipOutput));
        echo $gzipOutput;
    } else {
        // Send plain response
        header('Content-Type: application/json');
        echo $json;
    }
}
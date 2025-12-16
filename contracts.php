<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}
require_once __DIR__ . "/config/env.php";
require_once __DIR__ . "/config/contract.controller.php";
require_once __DIR__ . "/config/company.controller.php";
require_once __DIR__ . "/functions.php";

validate_bearer_token();

$qne_contracts = new ContractController("sqlsrv", QNE_SERVER, QNE_DEFAULT_DB, QNE_USERNAME, QNE_PASSWORD);
$unis_contracts = new ContractController(server: UNIS_SERVER, dbname: UNIS_DB, username: UNIS_USERNAME, password: UNIS_PASSWORD);
$company_controller = new CompanyController();

$unis_results = (array) $unis_contracts->fetch_contracts_from_unis();

$qne_results = [];
foreach (COMPANY_DATABASES as $company_db => $data) {
    array_push($qne_results, (array) $qne_contracts->fetch_contracts_from_qne($company_db));
}

$results = array_merge($unis_results, ...$qne_results);

$results = array_reduce($results, function ($carry, $item) use ($company_controller) {
    // Split contract_no by "-"
    $contract = $item->contract_no;

    $contract_parts = explode("-", $contract);
    $contract_prefix = $contract_parts[0] === "SO" ? $contract_parts[0] . "-" . $contract_parts[1] : $contract_parts[0];

    if (str_contains($contract_prefix, "SO")) {
        if (count($contract_parts) > 2) {
            if (is_numeric(substr($contract_parts[2], 0, 7))) {
                $contract_prefix = $contract_prefix . "-" . substr($contract_parts[2], 0, 7);
            } else {
                if (strlen($contract_parts[2]) > 1) {
                    $contract_prefix = $contract_prefix . "-" . $contract_parts[2];
                }
            }
        }
    }
    // Initialize the client key if it does not exist
    if (!isset($carry[$contract_prefix])) {
        $carry[$contract_prefix] = [];
    }

    unset($item->has_addendum);
    $company_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $item->company));
    $item->company_id = $company_controller->get_company_by_name($company_name);
    // $item->company_id = $company_name;

    // Add the current item to the array under the correct client name
    $carry[$contract_prefix][] = $item;

    return $carry;
}, []);

foreach ($results as &$contracts) {
    // Deep copy each contract object in the list
    $contract_list = [];
    foreach ($contracts as $contract) {
        $contract_list[] = clone $contract;  // Assuming $contracts contains objects
    }

    // Set the main contract object to the first contract in the list
    $contracts = $contracts[0];

    // If there are multiple contracts, assign them as items
    $contract_size = count($contract_list);
    if ($contract_size > 1) {
        $contracts->items = $contract_list;
        $contracts->date_to = $contracts->items[$contract_size - 1]->date_to;
        $contracts->contract_no = $contracts->items[$contract_size - 1]->contract_no;
        $contracts->grand_total = $contracts->items[$contract_size - 1]->grand_total;

    }
    $contracts->contract_term = date("M d, Y", strtotime($contracts->date_from)) . " - " . date("M d, Y", strtotime($contracts->date_to));
}

usort($results, function ($item_a, $item_b) {
    return strcmp($item_a->contract_status, $item_b->contract_status);
});


// echo json_encode($results);
echo json_encode(array_values($results));
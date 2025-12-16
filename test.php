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
require_once __DIR__ . "/config/booking.controller.php";

$unis_contracts = new ContractController(server: UNIS_SERVER, dbname: UNIS_DB, username: UNIS_USERNAME, password: UNIS_PASSWORD);

echo $unis_contracts->isConnectionSuccess ? 'Connection success' : 'Connection Error';

$booking_controller = new BookingController();
$recipients = $booking_controller->get_emails("individual");

$emails = array_column($recipients, 'name', 'email');


var_dump($emails);
<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once __DIR__ . "/config/env.php";
require_once "./functions.php";
require_once __DIR__ . "/config/booking.controller.php";
// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}
$booking_controller = new BookingController();

try {
    validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            $bookings = isset($_GET['site_bookings']) ? $booking_controller->get_all_bookings() : (isset($_GET['special']) ? $booking_controller->get_pre_bookings() :
                $booking_controller->get_bookings($_GET['id'] ?? null, $_GET['site_code'] ?? null))
            ;
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");

            sendJsonResponse($bookings);
            break;
        case "POST":
            if (!isset($_POST['data'])) {
                throw new Exception("Data not found!");
            }

            $data = (array) json_decode($_POST['data']);

            if (!isset($data['site'])) {
                $result = (array) $booking_controller->insert_booking_without_site($data);
                extract($result);

                $message = file_get_contents("./email_templates/booking_update_no_site.php");
                $message = str_replace("[today]", date("M d, Y"), $message);
                $message = str_replace("[address]", $address, $message);
                $message = str_replace("[size]", $size, $message);
                $message = str_replace("[facing]", $facing, $message);
                $message = str_replace("[status]", $booking_status, $message);
                $message = str_replace("[client]", $client, $message);
                $message = str_replace("[ae]", $account_executive, $message);
                $message = str_replace("[term_duration]", $term_duration, $message);
                $message = str_replace("[site_rental]", $site_rental, $message);
                $message = str_replace("[srp]", $srp, $message);
                $message = str_replace("[monthly_rate]", $monthly_rate, $message);
                $message = str_replace("[remarks]", $remarks, $message);
            } else {
                $booking_id = $booking_controller->insert_booking($data);
                if ($booking_id) {
                    $data = (array) $booking_controller->get_booking_for_notification($booking_id);
                    extract($data);

                    if ($old_client === $client) {
                        $message = file_get_contents(filename: './email_templates/cancel_booking.php');
                    } else {
                        $message = file_get_contents(filename: './email_templates/booking_update.php');
                    }

                    $message = str_replace("[today]", date("M d, Y"), $message);
                    $message = str_replace("[site_code]", $site_code, $message);
                    $message = str_replace("[address]", $address, $message);
                    $message = str_replace("[size]", $size, $message);
                    $message = str_replace("[facing]", $board_facing, $message);
                    $message = str_replace("[owner]", $site_owner, $message);
                    $message = str_replace("[status]", $booking_status, $message);
                    $message = str_replace("[client]", $client, $message);
                    $message = str_replace("[old_client]", $old_client, $message);
                    $message = str_replace("[ae]", $account_executive, $message);
                    $message = str_replace("[term_duration]", $term_duration, $message);
                    $message = str_replace("[site_rental]", $site_rental, $message);
                    $message = str_replace("[srp]", $srp, $message);
                    $message = str_replace("[monthly_rate]", $monthly_rate, $message);
                    $message = str_replace("[remarks]", $remarks, $message);
                } else {
                    throw new Exception("Database error");
                }
            }

            if (ENV_MODE === "DEV") {
                $emails = [
                    "vrinoza@unmg.com.ph" => "Vincent Kyle Rinoza",
                    // "fdolar@unitedneon.com" => "Ferlie Dolar"
                ];
            } else {
                $recipients = $booking_controller->get_emails("'individual'");
                $emails = array_column($recipients, 'name', index_key: 'email');
            }
            if ($response = $booking_controller->sendMail("BILLBOARD BOOKINGS, Etc.", $message, $emails, 'Sales Team')) {
                header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                header("Pragma: no-cache");
                header("Expires: 0");
                echo json_encode([
                    "acknowledged" => true,
                    "id" => $booking_id
                ]);
            } else {
                throw new Exception("Error in sending the notification. Please contact the developer.");
            }

            break;
        case "PUT":
            $data = json_decode(file_get_contents('php://input'), true);

            if ($data === null) {
                throw new Exception('Data not found.');
            }
            extract($data);
            if (isset($action) && $action === "update") {
                $result = $booking_controller->update_booking($booking_id, $monthly_rate, $booking_status, $date_from, $date_to, $remarks, $modified_at);
                $booking = (array) $booking_controller->get_booking_for_notification($booking_id);
                extract($booking);

                $message = file_get_contents(filename: './email_templates/cancel_booking.php');
                $message = str_replace("[today]", date("M d, Y"), $message);
                $message = str_replace("[site_code]", $site_code, $message);
                $message = str_replace("[address]", $address, $message);
                $message = str_replace("[size]", $size, $message);
                $message = str_replace("[facing]", $board_facing, $message);
                $message = str_replace("[owner]", $site_owner, $message);
                $message = str_replace("[status]", $booking_status, $message);
                $message = str_replace("[client]", $client, $message);
                $message = str_replace("[old_client]", $old_client, $message);
                $message = str_replace("[ae]", $account_executive, $message);
                $message = str_replace("[term_duration]", $term_duration, $message);
                $message = str_replace("[site_rental]", $site_rental, $message);
                $message = str_replace("[srp]", $srp, $message);
                $message = str_replace("[monthly_rate]", $monthly_rate, $message);
                $message = str_replace("[remarks]", $remarks, $message);

                if (ENV_MODE === "DEV") {
                    $emails = [
                        "vrinoza@unmg.com.ph" => "Vincent Kyle Rinoza",
                        // "fdolar@unitedneon.com" => "Ferlie Dolar"
                    ];
                } else {
                    $recipients = $booking_controller->get_emails("'individual'");
                    // $emails = array_column($recipients, 'name', index_key: 'email');
                }
                if ($response = $booking_controller->sendMail("BILLBOARD BOOKINGS, Etc.", $message, $emails, 'Sales Team')) {
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => true,
                        "id" => $booking_id
                    ]);
                    exit;
                } else {
                    throw new Exception("Error in sending the notification. Please contact the developer.");
                }
            } else {
                $result = $booking_controller->update_pre_site_booking($area, $address, $facing, $size, $id);
                if ($result) {
                    $result = $booking_controller->update_booking($booking_id, $monthly_rate, $booking_status, $start, $end, $remarks, $modified_at);

                    if ($result) {
                        $result = (array) $booking_controller->get_pre_site_booking($booking_id);
                        extract($result);

                        $message = file_get_contents("./email_templates/booking_update_no_site.php");
                        $message = str_replace("[today]", date("M d, Y"), $message);
                        $message = str_replace("[address]", $address, $message);
                        $message = str_replace("[size]", $size, $message);
                        $message = str_replace("[facing]", $facing, $message);
                        $message = str_replace("[status]", $booking_status, $message);
                        $message = str_replace("[client]", $client, $message);
                        $message = str_replace("[ae]", $account_executive, $message);
                        $message = str_replace("[term_duration]", $term_duration, $message);
                        $message = str_replace("[site_rental]", $site_rental, $message);
                        $message = str_replace("[srp]", $srp, $message);
                        $message = str_replace("[monthly_rate]", $monthly_rate, $message);
                        $message = str_replace("[remarks]", $remarks, $message);

                        if (ENV_MODE === "DEV") {
                            $emails = [
                                "vrinoza@unmg.com.ph" => "Vincent Kyle Rinoza",
                                // "fdolar@unitedneon.com" => "Ferlie Dolar"
                            ];
                        } else {
                            $recipients = $booking_controller->get_emails("'individual'");
                            $emails = array_column($recipients, 'name', index_key: 'email');
                        }
                        if ($response = $booking_controller->sendMail("BILLBOARD BOOKINGS, Etc.", $message, $emails, 'Sales Team')) {
                            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                            header("Pragma: no-cache");
                            header("Expires: 0");
                            echo json_encode([
                                "acknowledged" => true,
                                "id" => $booking_id
                            ]);
                            exit;
                        } else {
                            throw new Exception("Error in sending the notification. Please contact the developer.");
                        }
                    }
                }
            }
            echo json_encode([
                "acknowledged" => $result
            ]);
            break;
        case "DELETE":
            if (!isset($_REQUEST['id'])) {
                throw new Exception("ID not found.");
            }


            $result = $booking_controller->cancel_booking($_REQUEST['id'], $_REQUEST['date'], $_REQUEST['reason']);
            $booking = $booking_controller->get_bookings($_REQUEST['id']);
            if (count($booking) > 0) {
                if (date("Y-m-d", strtotime($booking[0]->created_at)) === date("Y-m-d", strtotime($_REQUEST['date']))) {
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => true,
                    ]);
                } else {
                    if ($result) {
                        $data = $booking_controller->get_booking_for_notification($_REQUEST['id']);

                        if ($data === false) {
                            $data = $booking_controller->get_pre_booking($_REQUEST['id']);
                        }
                        $data = (array) $data;
                        extract($data);

                        $message = file_get_contents(filename: './email_templates/cancel_booking.php');
                        $message = str_replace("[today]", date("M d, Y"), $message);
                        $message = str_replace("[site_code]", $site_code, $message);
                        $message = str_replace("[address]", $address, $message);
                        $message = str_replace("[size]", $size, $message);
                        $message = str_replace("[facing]", $board_facing, $message);
                        $message = str_replace("[owner]", $site_owner ?? "Sub-lease", $message);
                        $message = str_replace("[status]", $booking_status, $message);
                        $message = str_replace("[client]", $old_client, $message);
                        $message = str_replace("[ae]", $account_executive, $message);
                        $message = str_replace("[term_duration]", $term_duration, $message);
                        $message = str_replace("[site_rental]", $site_rental, $message);
                        $message = str_replace("[srp]", $srp, $message);
                        $message = str_replace("[monthly_rate]", $monthly_rate, $message);
                        $message = str_replace("[remarks]", $remarks, $message);


                        if (ENV_MODE === "DEV") {
                            $emails = [
                                "vrinoza@unmg.com.ph" => "Vincent Kyle Rinoza",
                                // "fdolar@unitedneon.com" => "Ferlie Dolar"
                            ];
                        } else {
                            $recipients = $booking_controller->get_emails("'individual'");
                            $emails = array_column($recipients, 'name', index_key: 'email');
                        }

                        if ($response = $booking_controller->sendMail("BILLBOARD BOOKINGS, Etc.", $message, $emails, 'Sales Team', true)) {
                            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                            header("Pragma: no-cache");
                            header("Expires: 0");
                            echo json_encode([
                                "acknowledged" => true,
                            ]);
                            break;
                        } else {
                            throw new Exception("Error in sending the notification. Please contact the developer.");

                        }
                    }
                }
            }
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
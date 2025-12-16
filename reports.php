<?php
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Content-Length, Authorization, X-User-Id');
header('Content-Type: application/json');

require_once "./config/reports.controller.php";
require_once "./config/user.controller.php";
require_once "functions.php";

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send the appropriate headers for the preflight response
    header('HTTP/1.1 200 OK');
    exit();
}

$reports_controller = new ReportsController();

try {
    // validate_bearer_token();

    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            if (isset($_GET['user'])) {
                $user_controller = new UserController();
                $user_id = $_GET['user'];
                $year = $_GET['year'];
                $week = isset($_GET['week']) ? json_decode($_GET['week']) : null;

                $user = $user_controller->get_user_access($user_id);
                $account_id = $user->account_id;

                $method = isset($_GET['summary']) ? 'get_report_summary' : 'get_reports_by_week';
                if ($user->access !== null) {
                    switch ($user->access) {
                        case "own":
                            $reports = $reports_controller->$method($user_id, null, $year, $week);
                            break;
                        case "team":
                            $reports = $reports_controller->$method(null, $user->unit_id, $year, $week);
                            break;
                        case "all":
                        default:
                            $reports = $reports_controller->$method(null, null, $year, $week);
                            break;
                    }
                } else {
                    if ($user->unit_id === null || $user->account_id === $user->head_id) {
                        // Head or top-level user
                        $reports = $reports_controller->$method(null, $user->unit_id ?? null, $year, $week);
                    } else {
                        // Regular AE
                        $reports = $reports_controller->$method($user_id, null, $year, $week);
                    }
                }
            }

            if (isset($_GET['this_week'])) {
                $user_scope = get_user_client_scope();
                $reports = $reports_controller->get_weekly_report($user_scope);

            }
            if (isset($_GET['count'])) {
                $reports = $reports_controller->get_client_count($_GET['count'] == 0 ? null : $_GET['count'], $_GET['status'] ?? null);
            }
            if (isset($_GET['access'])) {
                $reports = $reports_controller->get_report_accesses();
            }
            if (isset($_GET['client_id'])) {
                $reports = $reports_controller->get_client_reports($_GET['client_id']);
            }
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            sendJsonResponse($reports);
            break;
        case "POST":
            if (isset($_POST['PUT'])) {
                if (!isset($_POST['report'])) {
                    throw new Exception("Report not found.");
                }
                $uploadFile = null;
                if (isset($_FILES['file'])) {
                    $originalName = $_FILES['file']['name'];
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);

                    // Use current Unix timestamp in milliseconds
                    $timestamp = round(microtime(true) * 1000);
                    $newFileName = "$timestamp.$extension";

                    $uploadFile = "attachments/$newFileName";
                }

                $file = $uploadFile ?? $_POST['file_path'];

                $result = $reports_controller->update_report($_POST['report'], $_POST['report_id'], $_POST['user_id'], $_POST['date'], $file, $_POST['file_id']);
                if ($result) {
                    if ($uploadFile !== null) {
                        if (file_exists($_POST['file_path'])) {
                            unlink($_POST['file_path']); // Deletes the file
                        }

                        if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
                            throw new Exception("Failed to move uploaded file.");
                        }
                    }
                    $report = $reports_controller->get_single_report($_POST['report_id']);
                    http_response_code(201); // Created
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    echo json_encode([
                        "acknowledged" => (bool) $result,
                        "item" => $report,
                        "id" => $_POST['user_id']
                    ]);
                } else {
                    throw new Exception("Failed to create report.");
                }
            } else {
                if (!isset($_POST['report'])) {
                    throw new Exception("Report not found.");
                }

                // File handling
                $uploadFile = null;
                if (isset($_FILES['file'])) {
                    // throw new Exception("No file uploaded.");

                    $originalName = $_FILES['file']['name'];
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);

                    // Use current Unix timestamp in milliseconds
                    $timestamp = round(microtime(true) * 1000);
                    $newFileName = "$timestamp.$extension";

                }
                $uploadFile = isset($_FILES['file']) ? "attachments/$newFileName" : null;

                $result = $reports_controller->insert_report($_POST['client_id'], $_POST['report'], $_POST['user_id'], $_POST['sales_unit_id'], $_POST['date'], $uploadFile);
                if ($result) {
                    if (isset($_FILES['file'])) {
                        if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
                            throw new Exception("Failed to move uploaded file.");
                        }
                    }
                    http_response_code(201); // Created
                    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                    header("Pragma: no-cache");
                    header("Expires: 0");

                    if ($result) {
                        $user_id = $_POST['user_id'];
                        $report = $reports_controller->get_single_report($result);
                        echo json_encode([
                            "acknowledged" => (bool) $result,
                            "item" => $report,
                            "id" => $user_id
                        ]);
                    }
                } else {
                    throw new Exception("Failed to create report.");
                }
            }

            break;
        case "DELETE":
            if (!isset($_GET['id'])) {
                throw new Exception("Report ID not found.");
            }
            $report = $reports_controller->get_single_report($_GET['id']);
            $result = $reports_controller->delete_report($_GET['id']);
            if ($result) {
                $report_files = $reports_controller->get_report_file($_GET['id']);

                if ($report_files && is_array($report_files)) {
                    foreach ($report_files as $file) {
                        if (isset($file->file) && file_exists($file->file)) {
                            unlink($file->file);
                        }
                    }
                }
                http_response_code(200); // OK
                header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
                header("Pragma: no-cache");
                header("Expires: 0");
                echo json_encode([
                    "acknowledged" => (bool) $result,
                    "item" => $report
                ]);
            } else {
                throw new Exception("Failed to delete report.");
            }
            break;
    }
} catch (Exception $e) {
    // Output only the error message
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}

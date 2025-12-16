<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

require_once __DIR__ . "/env.php";
class Controller
{
    public $connection;
    public $statement;
    public $isConnectionSuccess;
    public $connectionError;

    public function __construct($server_type = DEFAULT_DB, $server = DB_SERVER, $dbname = DB_NAME, $username = DB_USERNAME, $password = DB_PASSWORD)
    {
        try {
            $dsn = "mysql:host={$server};dbname={$dbname}";

            if ($server_type != DEFAULT_DB) {
                $dsn = "sqlsrv:Server={$server};Database={$dbname};Encrypt=no";
            }

            $this->connection = new PDO($dsn, $username, $password);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->isConnectionSuccess = true;
        } catch (PDOException $e) {
            $this->connectionError = $e->getMessage();
        }
    }

    public function setStatement($query)
    {
        if ($this->isConnectionSuccess) {
            $this->statement = $this->connection->prepare($query);
        } else {
            throw new Exception($this->connectionError);
        }
    }
    public function sendMail($subject, $message, $receipientEmail = MAIL_USERNAME, $receipientName = MAIL_NAME, $withMessageID = false)
    {
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host = 'smtp.gmail.com';                       //Set the SMTP server to send through
            $mail->SMTPAuth = true;                                   //Enable SMTP authentication
            $mail->Username = MAIL_USERNAME;                          //SMTP username
            $mail->Password = MAIL_PASSWORD;                          //SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            $mail->Port = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            // $mail->SMTPDebug = 2;                                    //For checking Mailing errors

            //Recipients
            $mail->setFrom(MAIL_FROM, MAIL_NAME);
            if (is_array($receipientEmail)) {
                foreach ($receipientEmail as $email => $name) {
                    $mail->addAddress($email, $name);
                }
            } else {
                $mail->addAddress($receipientEmail, $receipientName);       //Add a recipient
            }

            // //Content
            $mail->isHTML(true);                                     //Set email format to HTML
            $mail->CharSet = 'UTF-8';          // ✅ This is crucial
            $mail->Encoding = 'base64';        // ✅ Recommended for UTF-8 content
            $mail->Subject = $subject;
            $mail->Body = $message;
            if ($withMessageID) {
                $mail->MessageID = '<noreply@unitedneon.com>';
            }
            // if ($receipientEmail !== MAIL_USERNAME) {
            //     $mail->addCC(MAIL_USERNAME);
            // }

            return $mail->send();
        } catch (Exception $e) {
            return $e->getMessage() . "...Mailer Error: {$mail->ErrorInfo}";
            // return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    public function log_activity($action, $module, $row_id, $user_id)
    {
        $this->setStatement("INSERT INTO system_logs (action, module, row_id, logged_by) VALUES(?,?,?,?)");
        return $this->statement->execute([$action, $module, $row_id, $user_id]);
    }

    public function get_old_value($table_name, $column, $row_id, $parent = false, $id_column = null, $is_array = false)
    {
        $id_column = $parent ? 'ID' : ($id_column ?? 'ID');

        $query = "SELECT ID, {$column} FROM {$table_name} WHERE {$id_column} = ?";
        $this->setStatement($query);
        $this->statement->execute([$row_id]);
        return $is_array ? $this->statement->fetchAll(PDO::FETCH_ASSOC) : $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    public function get_client_information_value($table_name, $row_id)
    {
        $column = "name";
        $id = "ID";
        switch ($table_name) {
            case "user_information":
                $column = "CONCAT(first_name, ' ', last_name) as full_name";
                $id = "account_id";
                break;
            case "sales_units":
                $column = "unit_name";
                break;
        }
        $query = "SELECT {$column} FROM {$table_name} WHERE {$id} = ?";
        $this->setStatement($query);
        $this->statement->execute([$row_id]);
        return $this->statement->fetchColumn();
    }
    public function check_column_changes($table_name, $row_id, $new_value, $old_value, $column, $logger)
    {
        $result = false;

        try {
            // $this->connection->beginTransaction();
            if ($old_value !== $new_value) {
                if (strlen($old_value) !== 0 && strlen($new_value) !== 0) {

                    $this->setStatement("SELECT action FROM log_actions WHERE ID in (29)"); # 29 = updated {column} from {old} to {new}.
                    $this->statement->execute();
                    $action = $this->statement->fetchColumn();

                    $action = str_replace("{column}", $column, $action);
                    $action = str_replace("{old}", $old_value, $action);
                    $action = str_replace("{new}", $new_value, $action);
                } else if (strlen($new_value) === 0) {
                    $this->setStatement("SELECT action FROM log_actions WHERE ID in (31)"); # 29 = updated {column} from {old} to {new}.
                    $this->statement->execute();
                    $action = $this->statement->fetchColumn();

                    $action = str_replace("{column}", $column, $action);
                    $action = str_replace("{old}", $old_value, $action);
                } else {
                    $this->setStatement("SELECT action FROM log_actions WHERE ID in (30)"); # 29 = updated {column} from {old} to {new}.
                    $this->statement->execute();
                    $action = $this->statement->fetchColumn();

                    $action = str_replace("{column}", $column, $action);
                    $action = str_replace("{new}", $new_value, $action);
                }

                $this->setStatement("INSERT INTO system_logs (action, module, row_id, logged_by) VALUES (?,'{$table_name}*',?,'{$logger}')");
                $result = $this->statement->execute([$action, $row_id]);
            } else {
                $result = true;
            }

            // $this->connection->commit();

            return $result;

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    //HELPER FUNCTIONS
    public function getCompanyCode($company_name)
    {
        if ($company_name === "EVER CORPORATION") {
            return "EVER";
        }
        $partition = explode(' ', $company_name);

        $initial_letters = [];

        foreach ($partition as $letter) {
            $initial_letters[] = strtoupper(substr($letter, 0, 1));
        }

        return implode('', $initial_letters);
    }
}

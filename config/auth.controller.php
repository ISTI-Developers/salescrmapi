<?php
require_once __DIR__ . "/controller.php";

class AuthController extends Controller
{
    public function login_account($username, $password)
    {
        $this->setStatement("SELECT * FROM user_accounts 
        WHERE (username = :username 
        OR email_address = :username) 
        AND password = :password");
        $this->statement->execute([':username' => $username, ':password' => $password]);
        return $this->statement->fetch();
    }

    public function verify_password($id, $password)
    {
        if ($this->isConnectionSuccess) {
            $this->setStatement("SELECT * FROM user_accounts WHERE ID = ? AND password = ?");
            $this->statement->execute([$id, $password]);
            return $this->statement->rowCount();
        } else {
            throw new Exception($this->connectionError);
        }
    }

    public function update_last_login($id)
    {
        if ($this->isConnectionSuccess) {
            $this->setStatement("UPDATE user_accounts SET last_login = NOW() WHERE ID = ?");
            return $this->statement->execute([$id]);
        } else {
            throw new Exception($this->connectionError);
        }
    }

    public function get_online_users()
    {
        $this->setStatement("SELECT ua.ID, ui.first_name, ui.last_name, ua.last_login FROM user_accounts ua JOIN user_information ui ON ua.ID = ui.account_id WHERE last_login >= NOW() - INTERVAL 60 SECOND;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    public function validate_email($email_address)
    {
        $this->setStatement("SELECT ua.ID, ui.first_name, ua.email_address FROM user_accounts ua JOIN user_information ui ON ui.account_id = ua.ID WHERE ua.email_address = ?");
        $this->statement->execute([$email_address]);
        return $this->statement->fetch();
    }
    public function insert_code($code, $ID)
    {
        $this->setStatement("UPDATE user_accounts SET code = ? WHERE ID = ?");
        return $this->statement->execute([$code, $ID]);
    }
    public function verify_code($code, $ID, $password)
    {

        try {
            $this->connection->beginTransaction();
            // First, update the password
            $this->setStatement("SELECT ua.ID, ui.first_name, ua.email_address FROM user_accounts ua JOIN user_information ui ON ui.account_id = ua.ID WHERE ua.code = ? AND ua.ID = ?");
            $this->statement->execute([$code, $ID]);
            $user = $this->statement->fetch();
            if (!$user) {
                throw new Exception("Invalid code or user ID.");
            }

            $password = md5($password);
            $this->setStatement("UPDATE user_accounts SET code = NULL, password = ?, status_id = 4 WHERE ID = ?");
            $this->statement->execute([$password, $ID]);

            $this->connection->commit();

            // Return the updated user information
            return $user;

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw $e;
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e; // Rethrow other exceptions, like validation errors
        }
    }

    public function reset_password($id, $password)
    {
        try {
            $this->connection->beginTransaction();
            // First, update the password
            $this->setStatement("UPDATE `user_accounts` SET password = ?, status_id = 4 WHERE ID = ?");
            $this->statement->execute([$password, $id]);

            $this->setStatement("SELECT ua.ID, ui.first_name, ui.last_name, ua.email_address
                        FROM user_accounts ua 
                        JOIN user_information ui ON ui.account_id = ua.ID
                        WHERE ua.ID = ?");
            $this->statement->execute([$id]);

            $this->connection->commit();

            // Return the updated user information
            return $this->statement->fetch();

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    function insert_auth_token($ID, $token)
    {
        $this->setStatement("UPDATE user_accounts SET token = ?, last_login = NOW() WHERE ID = ?");
        return $this->statement->execute([$token, $ID]);
    }
    function get_auth_token($ID)
    {
        $this->setStatement("SELECT token FROM user_accounts WHERE ID = ?");
        return $this->statement->execute([$ID]);
    }

    function logout_user($ID)
    {
        $this->setStatement("UPDATE user_accounts SET token = NULL WHERE ID = ?");
        return $this->statement->execute([$ID]);
    }
}
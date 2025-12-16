<?php
require_once "controller.php";

class UserController extends Controller
{
    function get_users($id = null, $with_password = false)
    {
        $query = "SELECT ua.ID, ui.first_name, ui.middle_name, ui.last_name, ua.email_address, COALESCE(ui.company_id,0) as company_id , COALESCE(ui.sales_unit_id, 0) as sales_unit_id, ua.username, us.name AS status, ui.image, ua.role_id";
        if ($with_password) {
            $query .= ", ua.password ";
        }
        $query .= " FROM user_accounts ua 
            JOIN user_information ui ON ui.account_id = ua.ID
            JOIN user_status us ON ua.status_id = us.ID
            WHERE ua.status_id <> 5";

        if ($id != null) {
            $query .= " AND ua.ID = ?";
        }

        $query .= " ORDER BY us.name ASC, ui.first_name ASC;";
        $this->setStatement($query);
        $this->statement->execute($id ? [$id] : []);
        return $id === null ? $this->statement->fetchAll() : $this->statement->fetch();
    }

    function get_sales_units($sales_unit_id = null)
    {
        if ($sales_unit_id === 0) {
            return null;
        }
        $query = "SELECT ID as sales_unit_id, unit_name, company_id, unit_head_id FROM sales_units";

        if ($sales_unit_id !== null) {
            $query = "SELECT ID as sales_unit_id, unit_name FROM sales_units WHERE ID = ?";
        }
        $this->setStatement($query);
        $this->statement->execute($sales_unit_id ? [$sales_unit_id] : []);
        return $sales_unit_id === null ? $this->statement->fetchAll() : $this->statement->fetch();
    }
    function get_available_sales_members()
    {
        $this->setStatement("SELECT ua.ID as user_id, ui.first_name, ui.last_name, ua.role_id FROM user_information ui JOIN user_accounts ua ON ui.account_id = ua.ID WHERE ui.sales_unit_id IS NULL AND ua.role_id > 2 AND ua.status_id NOT IN (2,5)");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    public function get_user_access($ID)
    {
        $this->setStatement("SELECT ui.account_id, ui.report_access as access, ui.sales_unit_id as unit_id, su.unit_head_id as head_id FROM user_information ui LEFT JOIN sales_units su ON su.ID = ui.sales_unit_id WHERE account_id = ?");
        $this->statement->execute([$ID]);
        if ($this->statement->rowCount() !== 0) {
            return $this->statement->fetch();
        }
        return null;
    }

    function create_user($data)
    {
        extract($data);
        $password = md5($password);

        try {
            $this->connection->beginTransaction();
            $this->setStatement("INSERT INTO user_accounts (username, password, email_address, role_id, status_id) VALUES (?,?,?,?,3)");
            $this->statement->execute([$username, $password, $email_address, $role_id]);

            $account_id = $this->connection->lastInsertId();

            $this->setStatement("INSERT INTO user_information (first_name, middle_name, last_name, company_id, account_id) VALUES(?,?,?,?,?)");
            $this->statement->execute([$first_name, $middle_name, $last_name, $company->ID, $account_id]);

            $this->connection->commit();
            return $account_id;

        } catch (PDOException $e) {
            $this->connection->rollBack();
        }
    }

    function update_users_sales_unit($company_id, $sales_unit_id, $user_id)
    {
        try {
            $this->connection->beginTransaction();

            $this->setStatement("SELECT CONCAT(ui.first_name, ' ', ui.last_name) as full_name, su.ID as sales_unit_id, su.unit_name FROM user_information ui LEFT JOIN sales_units su ON ui.sales_unit_id = su.ID WHERE account_id = ?");
            $this->statement->execute([$user_id]);
            $user = $this->statement->fetch();

            if (!is_null($user->sales_unit_id)) {
                if ($user->sales_unit_id !== $sales_unit_id) {
                    $this->setStatement("SELECT unit_name FROM sales_units WHERE ID = ?");
                    $this->statement->execute([$user->sales_unit_id]);
                    $previous_sales_unit = $this->statement->fetchColumn();

                    $this->setStatement("SELECT unit_name FROM sales_units WHERE ID = ?");
                    $this->statement->execute([$sales_unit_id]);
                    $new_sales_unit = $this->statement->fetchColumn();

                    $this->setStatement("SELECT action FROM log_actions WHERE ID in (19)");
                    $this->statement->execute();
                    $action = $this->statement->fetchColumn();

                    $action = str_replace("{member}", $user->full_name, $action);
                    $action = str_replace("{su1}", $previous_sales_unit, $action);
                    $action = str_replace("{su2}", $new_sales_unit, $action);

                    $this->setStatement("INSERT INTO system_logs (action, module, row_id, logged_by) VALUES (?,'companies*',?,'1')");
                    $this->statement->execute([$action, $user_id]);
                }
            } else {
                $this->setStatement("SELECT su.unit_name, c.name FROM sales_units su LEFT JOIN companies c ON su.company_id = c.ID WHERE su.ID = ?");
                $this->statement->execute([$sales_unit_id]);
                $sales = $this->statement->fetch();

                $this->setStatement("SELECT action FROM log_actions WHERE ID in (28)");
                $this->statement->execute();
                $action = $this->statement->fetchColumn();

                $action = str_replace("{member}", $user->full_name, $action);
                $action = str_replace("{unit}", $sales->unit_name, $action);
                $action = str_replace("{company}", $sales->name, $action);

                $this->setStatement("INSERT INTO system_logs (action, module, row_id, logged_by) VALUES (?,'user_accounts*',?,'1')");
                $this->statement->execute([$action, $user_id]);
            }

            $this->setStatement("UPDATE user_information SET company_id = ?, sales_unit_id = ? WHERE account_id = ?");
            $response = $this->statement->execute([$company_id, $sales_unit_id, $user_id]);

            $this->connection->commit();

            return $response;
        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    function update_password($password, $user_id)
    {
        $this->setStatement("UPDATE user_accounts SET password = ?, status_id = 1 WHERE ID = ?");
        if ($this->statement->execute([$password, $user_id])) {
            return $user_id;
        } else {
            return false;
        }
    }

    function update_role($user_id, $role)
    {
        $this->setStatement("UPDATE user_accounts SET role_id = ? WHERE ID = ?");
        if ($this->statement->execute([$role, $user_id])) {
            return $user_id;
        } else {
            return false;
        }
    }

    function update_user($user_id, $first_name, $middle_name, $last_name, $email_address, $username, $role, $company_id)
    {
        try {
            $this->connection->beginTransaction();
            $this->setStatement("UPDATE user_accounts SET username = ?, email_address = ?, role_id = ? WHERE ID = ?");
            $this->statement->execute([$username, $email_address, $role, $user_id]);

            $this->setStatement("UPDATE user_information SET first_name = ?, middle_name = ?, last_name = ?, company_id = ? WHERE account_id = ?");
            $result = $this->statement->execute([$first_name, $middle_name, $last_name, $company_id, $user_id]);
            $this->connection->commit();

            return $result;

        } catch (PDOException $e) {
            $this->connection->rollBack();
        }

    }
    function update_status($user_id, $status_id)
    {
        $this->setStatement("UPDATE user_accounts SET status_id = ?, modified_at = CURRENT_TIMESTAMP() WHERE ID = ?");
        return $this->statement->execute([$status_id, $user_id]);
    }

}
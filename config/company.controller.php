<?php
require_once "controller.php";

class CompanyController extends Controller
{
    //COMPANY FUNCTIONS

    function get_companies($company_id = null)
    {
        if ($company_id === 0) {
            return null;
        }
        $query = "SELECT * FROM companies";

        if ($company_id !== null) {
            $query .= " WHERE ID = ?";
        }
        $this->setStatement($query);
        $this->statement->execute($company_id ? [$company_id] : []);
        return $company_id ? $this->statement->fetch() : $this->statement->fetchAll();
    }

    function get_company_by_name($company_name)
    {
        $this->setStatement("SELECT ID FROM companies WHERE LOWER(REGEXP_REPLACE(name, '[^a-zA-Z0-9]', '')) LIKE LOWER(REGEXP_REPLACE(?, '[^a-zA-Z0-9]', ''))");
        $this->statement->execute(["%{$company_name}%"]);
        return $this->statement->fetchColumn();
    }

    function get_company_sales_unit_summary()
    {
        $this->setStatement("SELECT c.ID as company_id, c.name as company_name, c.code as company_code, su.ID as unit_id, su.unit_name, su.unit_head_id, ui.account_id as user_id, CONCAT(ui.first_name, ' ', ui.last_name) as full_name FROM user_information ui 
        LEFT JOIN sales_units su ON su.ID = ui.sales_unit_id 
        LEFT JOIN companies c ON c.ID = ui.company_id;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }


    function insert_company($code, $name)
    {
        $this->setStatement("INSERT INTO companies (code, name) VALUES (?,?)");
        $this->statement->execute([$code, $name]);
        return $this->connection->lastInsertId();
    }

    function insert_sales_unit($company_id, $unit_name, $unit_head)
    {
        try {
            $this->connection->beginTransaction();

            $this->setStatement("INSERT INTO sales_units (unit_name, company_id, unit_head_id) VALUES (?,?,?)");
            $this->statement->execute([$unit_name, $company_id, $unit_head]);
            $sales_unit_id = $this->connection->lastInsertId();

            $this->setStatement("UPDATE user_information SET company_id = ?, sales_unit_id = ? WHERE account_id = ?");
            $this->statement->execute([$company_id, $sales_unit_id, $unit_head]);

            $this->connection->commit();

            // Return the updated user information
            return $sales_unit_id;

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    function update_company($code, $name, $id)
    {
        $this->setStatement("UPDATE companies SET code = ?, name = ? WHERE ID = ?");
        return $this->statement->execute([$code, $name, $id]);
    }

    function update_sales_unit($company_id, $unit_name, $unit_head, $unit_id)
    {
        try {
            $this->connection->beginTransaction();

            //CHECK IF SALES UNIT ALREADY EXISTS
            $this->setStatement("SELECT * FROM sales_units WHERE ID = ? OR unit_name = ?");
            $this->statement->execute([$unit_id, $unit_name]);
            if ($this->statement->rowCount() !== 0) {
                //UPDATE EXISTING SALES UNIT
                $sales_unit = $this->statement->fetch();

                if ($sales_unit->unit_head_id !== $unit_head) {
                    $this->setStatement("SELECT account_id, CONCAT(first_name, ' ', last_name) as full_name FROM user_information WHERE account_id IN (?, ?)");
                    $this->statement->execute([$sales_unit->unit_head_id, $unit_head]);

                    $results = $this->statement->fetchAll();

                    // Map results to specific members
                    foreach ($results as $row) {
                        if ($row->account_id === $sales_unit->unit_head_id) {
                            $member1 = $row;
                        } elseif ($row->account_id === $unit_head) {
                            $member2 = $row;
                        }
                    }

                    $this->setStatement("SELECT action FROM log_actions WHERE ID in (20)");
                    $this->statement->execute();
                    $action = $this->statement->fetchColumn();

                    $action = str_replace("{su1}", $unit_name, $action);
                    $action = str_replace("{member1}", $member1->full_name, $action);
                    $action = str_replace("{member2}", $member2->full_name, $action);

                    $this->setStatement("INSERT INTO system_logs (action, module, row_id, logged_by) VALUES (?,'companies*',?,'1')");
                    $this->statement->execute([$action, $unit_id]);

                }
                $this->setStatement("UPDATE sales_units SET unit_name = ?, company_id = ?, unit_head_id = ? WHERE ID = ?");
                $this->statement->execute([$unit_name, $company_id, $unit_head, $unit_id]);

                //UPDATE UNIT HEAD COMPANY AND SALES UNIT
                $this->setStatement("UPDATE user_information SET company_id = ?, sales_unit_id = ? WHERE account_id = ?");
                $this->statement->execute([$company_id, $unit_id, $unit_head]);
            } else {
                //INSERT NEW SALES UNIT
                $this->setStatement("INSERT INTO sales_units (unit_name, company_id, unit_head_id) VALUES (?,?,?)");
                $this->statement->execute([$unit_name, $company_id, $unit_head]);
                $sales_unit_id = $this->connection->lastInsertId();

                //UPDATE UNIT HEAD COMPANY AND SALES UNIT
                $this->setStatement("UPDATE user_information SET company_id = ?, sales_unit_id = ? WHERE account_id = ?");
                $this->statement->execute([$company_id, $sales_unit_id, $unit_head]);
            }
            $this->connection->commit();
            // $this->connection->rollBack();

            return $sales_unit_id ?? "true";

        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
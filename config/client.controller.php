<?php
require_once "controller.php";

class ClientController extends Controller
{
    public function get_clients($id = null)
    {
        $query = "SELECT c.ID as client_id, c.name, c.industry, c.brand, c.company_id, cs.name as company, c.sales_unit_id, su.unit_name as sales_unit, ui.account_id as account_id, ca.ID as client_account_id, CONCAT(ui.first_name,' ', ui.last_name) as account_executive, CONCAT(LEFT(ui.first_name,1),LEFT(ui.middle_name,1),LEFT(ui.last_name,1)) as account_code, sun.ID as account_su_id, sun.unit_name as account_su, cc.ID as contact_id, cc.name as contact_person, cc.designation, cc.contact_number, cc.email_address, cc.address, cc.type, cc.source, c.status, c.created_at 
        FROM clients c JOIN client_contact cc ON cc.client_id = c.ID 
        JOIN client_accounts ca ON c.ID = ca.client_id 
        JOIN sales_units su ON su.ID = sales_unit_id
        JOIN companies cs ON cs.ID = c.company_id
        JOIN user_information ui ON ca.account_id = ui.account_id
        JOIN sales_units sun ON ui.sales_unit_id = sun.ID WHERE c.status <> 0 ";

        if ($id != null) {
            $query .= " AND c.ID = ?";
        }

        $this->setStatement($query);
        $this->statement->execute($id ? [$id] : []);
        return $this->statement->fetchAll();
    }

    public function get_client_preview($scope)
    {
        $query = "
        SELECT 
            c.ID as client_id, 
            c.name, 
            c.industry, 
            c.brand, 
            c.company_id, 
            cs.name as company, 
            c.sales_unit_id, 
            su.unit_name as sales_unit, 
            ui.account_id as account_id, 
            ca.ID as client_account_id, 
            CONCAT(ui.first_name,' ', ui.last_name) as account_executive,
            CONCAT(LEFT(ui.first_name,1),LEFT(ui.middle_name,1),LEFT(ui.last_name,1)) as account_code, 
            sun.ID as account_su_id, 
            sun.unit_name as account_su, 
            c.status, 
            c.created_at 
        FROM clients c
        JOIN client_accounts ca ON c.ID = ca.client_id 
        JOIN sales_units su ON su.ID = c.sales_unit_id
        JOIN companies cs ON cs.ID = c.company_id
        JOIN user_information ui ON ca.account_id = ui.account_id
        JOIN sales_units sun ON ui.sales_unit_id = sun.ID
    ";

        if ($scope !== "all") {
            $query .= "
            LEFT JOIN company_roles cr 
                ON cr.company_id = {$scope} 
               AND cr.target_company_id = c.company_id
            WHERE c.status <> 0
              AND (
                  -- Always allow own company's clients
                  c.company_id = {$scope}

                  -- Other companies: apply rules
                  OR (
                      c.company_id <> {$scope}
                      AND (
                          cr.permission = 'all'
                          OR (cr.permission = 'POOL' AND c.status = 46)
                          OR (cr.permission IS NULL AND c.status = 46) -- default
                      )
                  )
              )
        ";
        } else {
            $query .= " WHERE c.status <> 0";
        }

        $this->setStatement($query);
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    public function get_clients_by_unit($unit_id = null)
    {
        $query = "SELECT su.unit_name as 'salesUnit', cm.name as 'status', COUNT(*) as clients FROM clients c JOIN sales_units su ON c.sales_unit_id = su.ID JOIN client_misc cm ON c.status = cm.ID WHERE cm.name <> 'POOL' AND cm.ID <> 0 ";

        if ($unit_id) {
            $query .= " AND su.ID = ? ";
        }
        $this->setStatement("{$query} GROUP BY su.unit_name, cm.name");
        $this->statement->execute($unit_id ? [$unit_id] : []);
        return $this->statement->fetchAll();
    }
    public function get_client_miscellaneous($category = null, $id = null)
    {
        // Start building the query
        $prefix = $category ?? "misc";
        $query = "SELECT ID as {$prefix}_id, name, category FROM client_misc WHERE 1=1"; // Using 1=1 to simplify conditional appending

        // Array to hold parameters
        $params = [];

        // Add condition for ID if provided
        if ($id !== null) {
            $query .= " AND ID = ?";
            $params[] = $id;
        }

        // Add condition for category if provided
        if ($category !== null) {
            $query .= " AND category = ?";
            $params[] = $category;
        }

        // Prepare the query and execute with parameters
        $this->setStatement($query . ' ORDER BY name');
        $this->statement->execute($params);

        // Return a single result if ID is provided, otherwise fetch all
        return $id ? $this->statement->fetch() : $this->statement->fetchAll();
    }

    function get_client_mediums($id)
    {
        $this->setStatement("SELECT cm.ID as cm_id, c.ID as client_id, m.ID as medium_id, m.name FROM client_mediums cm JOIN mediums m ON m.ID = cm.medium_id JOIN clients c ON c.ID = cm.client_id WHERE c.ID = ?");
        $this->statement->execute([$id]);
        return $this->statement->fetchAll();
    }

    function get_client_by_user($user_id)
    {
        $this->setStatement("SELECT c.ID as client_id, c.name, cm.name as status,
            r.date_submitted AS has_report
            FROM clients c JOIN client_contact cc ON cc.client_id = c.ID 
            JOIN client_accounts ca ON c.ID = ca.client_id 
            JOIN client_misc cm ON c.status = cm.ID
            JOIN user_information ui ON ca.account_id = ui.account_id 
            LEFT JOIN reports r ON r.client_id = c.ID
            WHERE ui.account_id = ?");
        $this->statement->execute([$user_id]);
        return $this->statement->fetchAll();
    }

    function insert_client($name, $company_id, $sales_unit_id, $account_executive, $status, $type, $source, $mediums, $contact_name = null, $designation = null, $contact_number = null, $email_address = null, $address = null, $industry = null, $brand = null)
    {
        try {

            $insert_status = [];

            $this->connection->beginTransaction();
            $this->setStatement("INSERT INTO clients (name, industry, brand, company_id, sales_unit_id, status) VALUES (?,?,?,?,?,?);");
            $this->statement->execute([$name, $industry, $brand, $company_id, $sales_unit_id, $status]);
            $client_id = $this->connection->lastInsertId();

            $this->setStatement("INSERT INTO client_contact (name, designation, contact_number, email_address, address, type, source, client_id)
            VALUES (?,?,?,?,?,?,?,?)");
            $insert_contact = $this->statement->execute([$contact_name, $designation, $contact_number, $email_address, $address, $type, $source, $client_id]);
            array_push($insert_status, $insert_contact);

            foreach ($account_executive as $ae) {
                $this->setStatement("INSERT INTO client_accounts (client_id, account_id) VALUES (?,?)");
                $insert_accounts = $this->statement->execute([$client_id, $ae]);
                array_push($insert_status, $insert_accounts);
            }


            foreach ($mediums as $medium) {
                $this->setStatement("INSERT INTO client_mediums (client_id, medium_id) VALUES (?,?)");
                $insert_medium = $this->statement->execute([$client_id, $medium]);
                array_push($insert_status, $insert_medium);
            }

            $this->connection->commit();

            $insert_status = !in_array(0, $insert_status) ? true : false;

            return [$insert_status, $client_id];

        } catch (PDOException $e) {
            $this->connection->rollBack();
            return $e->getMessage();
        }
    }
    function update_client($client_id, $name, $company_id, $sales_unit_id, $account_executive, $status, $type, $source, $mediums, $contact_name = null, $designation = null, $contact_number = null, $email_address = null, $address = null, $industry = null, $brand = null)
    {
        try {

            $update_status = [];

            $this->connection->beginTransaction();
            $this->setStatement("UPDATE clients SET name = ?, industry = ?, brand = ?, company_id = ?, sales_unit_id = ?, status = ? WHERE ID = ?");
            $this->statement->execute([$name, $industry, $brand, $company_id, $sales_unit_id, $status, $client_id]);

            $this->setStatement("UPDATE client_contact SET name = ?, designation = ?, contact_number = ?, email_address = ?, address = ?, type = ?, source = ? WHERE client_id = ?");
            $update_contact = $this->statement->execute([$contact_name, $designation, $contact_number, $email_address, $address, $type, $source, $client_id]);
            array_push($update_status, $update_contact);

            if (!empty($account_executive)) {
                // Get currently tagged AEs for the client
                $this->setStatement("SELECT account_id FROM client_accounts WHERE client_id = ?");
                $this->statement->execute([$client_id]);
                $existingAEs = $this->statement->fetchAll(PDO::FETCH_COLUMN);

                // Find which to delete and which to insert
                $toDelete = array_diff($existingAEs, $account_executive);
                $toInsert = array_diff($account_executive, $existingAEs);

                // Delete unselected AEs
                if (!empty($toDelete)) {
                    $in = str_repeat('?,', count($toDelete) - 1) . '?';
                    $sql = "DELETE FROM client_accounts WHERE client_id = ? AND account_id IN ($in)";
                    $this->setStatement($sql);
                    $updatedAccount = $this->statement->execute(array_merge([$client_id], $toDelete));
                    array_push($update_status, $updatedAccount);
                }

                // Insert new AEs
                foreach ($toInsert as $ae) {
                    $this->setStatement("INSERT INTO client_accounts (client_id, account_id) VALUES (?, ?)");
                    $updatedAccount = $this->statement->execute([$client_id, $ae]);
                    array_push($update_status, $updatedAccount);
                }

            }

            $this->setStatement("DELETE FROM client_mediums WHERE client_id = ?");
            $this->statement->execute([$client_id]);
            if ($this->statement->execute([$client_id])) {
                foreach ($mediums as $medium) {
                    $this->setStatement("INSERT INTO client_mediums (client_id, medium_id) VALUES (?,?)");
                    $update_medium = $this->statement->execute([$client_id, $medium]);
                    array_push($update_status, $update_medium);
                }
            }

            $this->connection->commit();

            return !in_array(0, $update_status) ? true : false;

        } catch (PDOException $e) {
            $this->connection->rollBack();
            return $e->getMessage();
        }
    }
    function update_status($client_id, $status, $logger)
    {
        $old_record = $this->get_old_value("clients", "status", $client_id, true);
        $this->setStatement("UPDATE clients SET status = ? WHERE ID = ?");
        if ($this->statement->execute([$status, $client_id])) {
            $old_value = $this->get_client_information_value("client_misc", $old_record['status']);
            $new_value = $this->get_client_information_value("client_misc", $status);

            return $this->check_column_changes("clients", $client_id, $new_value, $old_value, "status", $logger);
        } else {
            return false;
        }

    }

    public function delete_client($id)
    {
        try {
            $this->connection->beginTransaction();

            $this->setStatement("UPDATE clients SET status = 0 WHERE ID = ?");
            $result = $this->statement->execute([$id]);
            if (!$result) {
                throw new Exception("Failed to update client status.");
            }
            return $this->connection->commit();
        } catch (PDOException $e) {
            $this->connection->rollBack();
            return $e->getMessage();
        }
    }

    public function get_monthly_client_count($user_scope)
    {
        $params = [];
        $query = "SELECT MONTHNAME(created_at) as month, COUNT(*) as clients FROM clients WHERE MONTHNAME(created_at) = MONTHNAME(NOW())";
        if ($user_scope !== "all") {
            $query .= " AND company_id = ?";
            $params = [$user_scope];
        }
        $this->setStatement($query);
        $this->statement->execute($params);
        return $this->statement->fetch();
    }
}
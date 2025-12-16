<?php

use BcMath\Number;

require_once "controller.php";

class ReportsController extends Controller
{
    public function get_reports($user_id = null, $unit_id = null, $year)
    {
        $params = [];
        $conditions = [];
        $year_filter = $year;

        $query = "SELECT r.ID, ui.account_id, ui.first_name, ui.last_name, ui.middle_name, 
                su.ID as sales_unit_id, su.unit_name as sales_unit, 
                c.ID as client_id, c.name as client, 
                cm.name as status, r.user_id as editor_id, 
                CONCAT(uin.first_name, ' ', uin.last_name) as editor, 
                CONCAT(LEFT(uin.first_name,1),LEFT(uin.middle_name,1), LEFT(uin.last_name,1)) as editor_code, 
                r.activity, rf.ID as file_id, rf.file, r.date_submitted 
            FROM clients c
            LEFT JOIN client_misc cm ON c.status = cm.ID
            LEFT JOIN client_accounts ca ON ca.client_id = c.ID
            LEFT JOIN sales_units su ON c.sales_unit_id = su.ID
            LEFT JOIN reports r ON r.client_id = c.ID AND YEAR(r.date_submitted) = ?
            LEFT JOIN report_files rf ON r.ID = rf.report_id
            LEFT JOIN user_information ui ON ca.account_id = ui.account_id
            LEFT JOIN user_information uin ON r.user_id = uin.account_id
            WHERE c.status <> 0 ";

        // Add the year filter to the params first
        $params[] = $year_filter;

        // Build dynamic conditions
        if ($user_id !== null) {
            $conditions[] = "ca.account_id = ?";
            $params[] = $user_id;
        }

        if ($unit_id !== null) {
            $conditions[] = "su.ID = ?";
            $params[] = intval($unit_id);
        }


        // If there are conditions, add them to the query
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $this->setStatement($query);
        $this->statement->execute($params);
        return $this->statement->fetchAll();
    }
    public function get_reports_by_week($user_id = null, $unit_id = null, $year = null, $week)
    {
        $params = [];
        $conditions = [];
        $year_filter = $year;

        $query = "SELECT r.ID, ui.account_id, ui.first_name, ui.last_name, ui.middle_name, 
                su.ID as sales_unit_id, su.unit_name as sales_unit, 
                c.ID as client_id, c.name as client, 
                cm.name as status, r.user_id as editor_id, 
                CONCAT(uin.first_name, ' ', uin.last_name) as editor, 
                CONCAT(LEFT(uin.first_name,1),LEFT(uin.middle_name,1), LEFT(uin.last_name,1)) as editor_code, 
                r.activity, rf.ID as file_id, rf.file, r.date_submitted 
            FROM clients c
            LEFT JOIN client_misc cm ON c.status = cm.ID
            LEFT JOIN client_accounts ca ON ca.client_id = c.ID
            LEFT JOIN sales_units su ON c.sales_unit_id = su.ID
            LEFT JOIN reports r ON r.client_id = c.ID AND YEAR(r.date_submitted) = ? AND WEEK(r.date_submitted,3) IN (" . implode(",", $week) . ") 
            LEFT JOIN report_files rf ON r.ID = rf.report_id
            LEFT JOIN user_information ui ON ca.account_id = ui.account_id
            LEFT JOIN user_information uin ON r.user_id = uin.account_id
            WHERE c.status <> 0 ";

        // Add the year filter to the params first
        $params[] = $year_filter;

        // Build dynamic conditions
        if ($user_id !== null) {
            $conditions[] = "ca.account_id = ?";
            $params[] = $user_id;
        }

        if ($unit_id !== null) {
            $conditions[] = "su.ID = ?";
            $params[] = intval($unit_id);
        }


        // If there are conditions, add them to the query
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $this->setStatement($query);
        $this->statement->execute($params);
        return $this->statement->fetchAll();
    }
    public function get_client_reports($client_id)
    {
        $this->setStatement("SELECT r.ID, r.activity, CONCAT(ui.first_name,' ', ui.last_name) as account_name, CONCAT(LEFT(ui.first_name,1),LEFT(ui.middle_name,1),LEFT(ui.last_name,1)) as account_code,r.date_submitted, r.date_modified, rf.ID as file_id, rf.file FROM reports r JOIN user_information ui ON ui.account_id = r.user_id LEFT JOIN report_files rf ON r.ID = rf.report_id WHERE r.client_id = ? ORDER BY r.date_modified DESC;");
        $this->statement->execute([$client_id]);
        return $this->statement->fetchAll();
    }
    public function get_single_report($report_id)
    {
        $query = "SELECT r.ID, ui.account_id, ui.first_name, ui.last_name, ui.middle_name, 
                su.ID as sales_unit_id, su.unit_name as sales_unit, 
                c.ID as client_id, c.name as client, 
                cm.name as status, r.user_id as editor_id, 
                CONCAT(uin.first_name, ' ', uin.last_name) as editor, 
                CONCAT(LEFT(uin.first_name,1),LEFT(uin.middle_name,1), LEFT(uin.last_name,1)) as editor_code, 
                r.activity, rf.ID as file_id, rf.file, r.date_submitted 
            FROM clients c
            LEFT JOIN client_misc cm ON c.status = cm.ID
            LEFT JOIN client_accounts ca ON ca.client_id = c.ID
            LEFT JOIN sales_units su ON c.sales_unit_id = su.ID
            LEFT JOIN reports r ON r.client_id = c.ID
            LEFT JOIN report_files rf ON r.ID = rf.report_id
            LEFT JOIN user_information ui ON ca.account_id = ui.account_id
            LEFT JOIN user_information uin ON r.user_id = uin.account_id
            WHERE r.ID = ?";

        $this->setStatement($query);
        $this->statement->execute([$report_id]);
        return $this->statement->fetch();
    }
    public function get_report_summary($user_id, $unit_id, $year = null, $week = null)
    {
        $params = [];
        $conds = [];

        $sqlQuery = "SELECT su.unit_name, LEFT(MONTHNAME(r.date_submitted),3) as 'month', COUNT(*) as reports FROM reports r JOIN sales_units su ON r.sales_unit_id = su.ID JOIN clients c ON c.ID = r.client_id JOIN client_accounts ca ON c.ID = ca.client_id WHERE YEAR(r.date_submitted) = ? ";
        // Add the year filter to the params first
        $params[] = $year;

        // Build dynamic conditions
        if ($user_id !== null) {
            $conds[] = "ca.account_id = ?";
            $params[] = $user_id;
        }

        if ($unit_id !== null) {
            $conds[] = "su.ID = ?";
            $params[] = $unit_id;
        }

        // If there are conditions, add them to the query
        if (!empty($conds)) {
            $sqlQuery .= " AND " . implode(" AND ", $conds);
        }

        // return $query;
        $this->setStatement($sqlQuery . " GROUP BY su.unit_name, MONTHNAME(r.date_submitted);");
        $this->statement->execute($params);
        return $this->statement->fetchAll();
    }

    public function get_weekly_report($scope)
    {
        $dt = new DateTime();
        $dt->setISODate((int) $dt->format('o'), (int) $dt->format('W')); // Set to Monday of current ISO week

        $weekDates = [];

        for ($i = 0; $i < 7; $i++) {
            $weekDates[] = '"' . $dt->format('Y-m-d') . '"';
            $dt->modify('+1 day');
        }

        $joinedDates = implode(', ', $weekDates);
        $dates = "($joinedDates)";

        $query = "SELECT
                    r.ID AS report_id,
                    CONCAT(
                        LEFT(ui.first_name, 1),
                        LEFT(ui.middle_name, 1),
                        LEFT(ui.last_name, 1)
                    ) AS ae,
                    su.unit_name AS sales_unit,
                    c.name AS client,
                    r.activity AS report,
                    r.date_submitted AS 'date'
                FROM
                    reports r
                JOIN clients c ON c.ID = r.client_id
                JOIN user_information ui ON ui.account_id = r.user_id
                JOIN companies cm ON ui.company_id = cm.ID
                JOIN sales_units su ON su.ID = ui.sales_unit_id
                WHERE
                    DATE(r.date_submitted) IN {$dates}";

        $id = (int) $scope;
        $query .= $scope !== "all" ? " AND cm.ID = {$id}" : "";
        $this->setStatement($query);
        $this->statement->execute();
        return $this->statement->fetchAll();

    }

    public function get_client_count($sales_unit_id = null, $status = null)
    {
        $query = "SELECT client_misc.name as status, COUNT(*) as count FROM clients JOIN client_misc ON clients.status = client_misc.ID AND clients.status <> 0 ";
        $params = [];

        if ($sales_unit_id !== null) {
            $query .= " WHERE clients.sales_unit_id = ?";
            array_push($params, $sales_unit_id);
        }
        if ($status !== null) {
            $query .= $sales_unit_id !== null ? " AND clients.status = ?" : " WHERE clients.status = ?";
            array_push($params, $status);
        }

        $query .= " GROUP BY client_misc.name;";
        $this->setStatement($query);
        $this->statement->execute(params: $params);
        return $this->statement->fetchAll();
    }

    public function get_report_accesses()
    {
        $this->setStatement("SELECT * FROM report_week_override");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }
    public function insert_report_accesses($week, $year)
    {
        $this->setStatement("INSERT INTO report_week_override (week, year) VALUES (?,?)");
        $this->statement->execute([$week, $year]);
        return $this->statement->fetchAll();
    }

    public function insert_report($client_id, $activity, $user_id, $sales_unit_id, $date, $file)
    {
        $this->setStatement("INSERT INTO reports (activity, user_id, sales_unit_id, client_id, date_submitted) VALUES (?,?,?,?,?)");
        if ($this->statement->execute([$activity, $user_id, $sales_unit_id, $client_id, $date])) {
            $report_id = $this->connection->lastInsertId();
            if ($file !== null) {
                $this->setStatement("INSERT INTO report_files (report_id, file) VALUES (?,?)");
                if ($this->statement->execute([$report_id, $file])) {
                    return $report_id;
                }
            }
            return $report_id;
        }
        return false;
    }

    public function update_report($activity, $report_id, $user_id, $date, $file, $file_id)
    {
        $this->setStatement("UPDATE reports SET activity = ?, user_id = ?, date_modified = ? WHERE ID = ?");
        if ($this->statement->execute([$activity, $user_id, $date, $report_id])) {
            $this->setStatement("UPDATE report_files SET file = ? WHERE ID = ?");
            return $this->statement->execute([$file, $file_id]);
        }
    }

    public function delete_report($report_id)
    {
        $this->setStatement("DELETE FROM reports WHERE ID = ?");
        return $this->statement->execute([$report_id]);
    }


    public function get_report_file($report_id)
    {
        $this->setStatement("SELECT * FROM report_files WHERE report_id = ?");
        $this->statement->execute([$report_id]);
        return $this->statement->fetchAll();
    }
}
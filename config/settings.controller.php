<?php
require_once "controller.php";

class SettingsController extends Controller
{
    public function get_week_accesses(): array
    {
        $this->setStatement("SELECT * FROM report_week_override");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    public function get_view_accesses($id = null): array
    {
        $query = "SELECT ID, account_id, report_access FROM user_information";

        if ($id !== null) {
            $query .= " WHERE account_id = ?";
        }
        $this->setStatement($query);
        $this->statement->execute($id ? [$id] : []);
        return $this->statement->fetchAll();
    }

    public function unlock_week($week)
    {
        $this->setStatement("INSERT INTO report_week_override (week, year) VALUES (?, YEAR(NOW()))");
        if ($this->statement->execute([$week])) {
            return $this->connection->lastInsertId();
        } else {
            return false;
        }

    }
    public function lock_week($id)
    {
        $this->setStatement("DELETE FROM report_week_override WHERE ID = ?");
        return $this->statement->execute([$id]);
    }
    public function update_view_access($access, $user_id)
    {
        $this->setStatement("UPDATE user_information SET report_access = ? WHERE account_id = ?");
        return $this->statement->execute([$access, $user_id]);

    }

    public function get_advisory()
    {
        $this->setStatement("SELECT * FROM advisory");
        $this->statement->execute();
        return $this->statement->fetch();
    }
    public function insert_advisory($receipient, $title, $content)
    {
        $this->setStatement("INSERT INTO advisory (receipient, title, content) VALUES (?,?,?)");
        return $this->statement->execute([$receipient, $title, $content]);
    }
    public function delete_advisory()
    {
        $this->setStatement("DELETE FROM advisory");
        return $this->statement->execute();
    }

    public function get_dashboard_trends()
    {
        $query = "SELECT w.yearweek, w.week, COUNT(r.date_submitted) AS total_records FROM ( SELECT YEARWEEK(CURDATE(), 3) AS yearweek, WEEK(CURDATE(), 3) AS week UNION SELECT YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 3), WEEK(CURDATE() - INTERVAL 1 WEEK, 3) ) w LEFT JOIN reports r ON YEARWEEK(r.date_submitted, 3) = w.yearweek GROUP BY w.yearweek, w.week ORDER BY w.yearweek DESC;";

        $this->setStatement($query);
        $this->statement->execute([]);
        return $this->statement->fetchAll();
    }

    public function get_unis_paths()
    {
        $this->setStatement("SELECT * FROM unis");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    public function get_active_unis_path()
    {
        $this->setStatement("SELECT * FROM unis WHERE status = 1");
        $this->statement->execute();
        return $this->statement->fetch();
    }

    public function update_unis_path($id, $status)
    {
        $this->setStatement("UPDATE unis SET status = ? WHERE ID = ?");
        return $this->statement->execute([$status, $id]);
    }
}
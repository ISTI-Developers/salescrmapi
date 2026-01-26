<?php
require_once __DIR__ . "/controller.php";

class MeetingController extends Controller
{
    public function get_minutes(int $year, array $week)
    {
        $query = "SELECT * FROM minutes WHERE YEAR(created_at) = ?
        AND week IN (" . implode(", ", $week) . ")";
        $this->setStatement($query);
        $this->statement->execute([$year]);
        return $this->statement->fetchAll();
    }

    public function insert_minute($activity, $week, $date)
    {
        $query = "INSERT INTO minutes (activity, week, modified_at) VALUES (?,?,?)";
        $this->setStatement($query);
        if ($this->statement->execute([$activity, $week, $date])) {
            return $this->connection->lastInsertId();
        }
    }
    public function update_activity($activity, $week, $date, $minute_id)
    {
        $query = "UPDATE minutes SET activity = ?, week = ?, modified_at = ? WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$activity, $week, $date, $minute_id]);
    }

    public function delete_activity($minute_id)
    {
        $this->setStatement("DELETE FROM minutes WHERE ID = ?");
        return $this->statement->execute([$minute_id]);
    }
}
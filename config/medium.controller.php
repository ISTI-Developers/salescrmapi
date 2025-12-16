<?php
require_once "controller.php";

class MediumController extends Controller
{
    function get_mediums()
    {
        $this->setStatement("SELECT * FROM mediums WHERE status = 1");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }
    function get_mediums_with_companies()
    {
        $this->setStatement("SELECT cm.ID as company_medium_id, m.ID as medium_id, m.name as medium_name, cm.company_id, c.code, c.name FROM company_mediums cm RIGHT JOIN mediums m ON m.ID = cm.medium_id LEFT JOIN companies c ON c.ID = cm.company_id WHERE m.status = 1;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function get_company_mediums($company_id)
    {
        $this->setStatement("SELECT m.* from mediums m JOIN company_mediums cm ON cm.medium_id = m.ID WHERE cm.company_id = ? AND m.status = 1 ORDER BY m.ID");
        $this->statement->execute([$company_id]);
        return $this->statement->fetchAll();
    }

    function insert_medium($name, $company_id, $user_id)
    {
        try {
            $this->connection->beginTransaction();
            $this->setStatement("INSERT INTO mediums (name, status) VALUES (?,1)");
            $this->statement->execute([$name]);
            $medium_id = $this->connection->lastInsertId();

            $this->setStatement("INSERT INTO company_mediums (company_id, medium_id) VALUES (?,?)");

            if ($this->statement->execute([$company_id, $medium_id])) {

                $this->setStatement("SELECT action FROM log_actions WHERE ID IN (32)");
                $this->statement->execute();
                $action = $this->statement->fetchColumn();

                $this->setStatement("SELECT code as company FROM companies WHERE ID = ?");
                $this->statement->execute([$company_id]);
                $company = $this->statement->fetchColumn();

                $action = str_replace("{medium}", $name, $action);
                $action = str_replace("{company}", $company, $action);

                $result = $this->log_activity($action, "mediums", $medium_id, $user_id);
            }

            $this->connection->commit();

            return $result;
        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    function update_medium($id, $name, $user_id, $removed_companies = [], $added_companies = [], $old_name = null)
    {
        try {
            $this->connection->beginTransaction();
            $results = [];
            if (!is_null($old_name)) {

                $this->setStatement("UPDATE mediums SET name = ? WHERE ID = ?");
                if ($this->statement->execute([$name, $id])) {
                    $this->setStatement("SELECT action FROM log_actions WHERE ID IN (35)");
                    $this->statement->execute();
                    $action = $this->statement->fetchColumn();

                    $action = str_replace("{old}", $name, $action);
                    $action = str_replace("{new}", $old_name, $action);
                    array_push($results, intval($this->log_activity($action, "mediums", $id, $user_id)));
                } else {
                    array_push($results, 0);
                }
            }

            if (count($removed_companies) > 0) {
                foreach ($removed_companies as $removed) {
                    $this->setStatement("DELETE FROM company_mediums WHERE company_id = ? AND medium_id = ?");
                    if ($this->statement->execute([$removed['id'], $id])) {
                        $this->setStatement("SELECT action FROM log_actions WHERE ID IN (34)");
                        $this->statement->execute();
                        $action = $this->statement->fetchColumn();

                        $action = str_replace("{company}", $removed['code'], $action);
                        $action = str_replace("{medium}", $name, $action);
                        array_push($results, intval($this->log_activity($action, "mediums", $id, $user_id)));
                    } else {
                        array_push($results, 0);
                    }
                }
            }

            if (count($added_companies) > 0) {
                foreach ($added_companies as $added) {
                    $this->setStatement("INSERT INTO company_mediums (company_id, medium_id) VALUES (?,?)");
                    if ($this->statement->execute([$added['id'], $id])) {
                        $this->setStatement("SELECT action FROM log_actions WHERE ID IN (33)");
                        $this->statement->execute();
                        $action = $this->statement->fetchColumn();

                        $action = str_replace("{company}", $added['code'], $action);
                        $action = str_replace("{medium}", $name, $action);
                        array_push($results, intval($this->log_activity($action, "mediums", $id, $user_id)));
                    } else {
                        array_push($results, 0);
                    }
                }
            }

            $this->connection->commit();
            return !in_array(0, $results);
        } catch (PDOException $e) {
            $this->connection->rollBack();
            throw new Exception($e->getMessage());
        }
    }

    function delete_medium($id)
    {
        $this->setStatement("UPDATE mediums SET status = 2 WHERE ID = ?");
        return $this->statement->execute([$id]);
    }
}
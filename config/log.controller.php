<?php
require_once "controller.php";

class LogController extends Controller
{
    function get_logs()
    {
        $this->setStatement("SELECT sl.ID, sl.row_id, CONCAT(UPPER(SUBSTRING(sl.action, 1, 1)),SUBSTRING(sl.action, 2)) as action, sl.module, CONCAT(ui.first_name, ' ', ui.last_name) as author, sl.logged_at as date FROM system_logs sl JOIN user_information ui ON sl.logged_by = ui.account_id ORDER BY sl.logged_at DESC;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function get_template($template_id)
    {
        $this->setStatement("SELECT * FROM log_actions WHERE ID = ?");
        $this->statement->execute([$template_id]);
        return $this->statement->fetch();
    }

    function insert_log($action, $module, $logger, $row_id)
    {
        $this->setStatement("INSERT INTO system_logs (action, module, row_id, logged_by) VALUES (?,?,?,?)");
        return $this->statement->execute([$action, $module, $row_id, $logger]);
    }

    function get_module_logs($module, $ids = [], $modules = [])
    {
        if (count($ids) === 0 || count($modules) === 0) {
            return [];
        }

        $id_query = "(" . implode(",", $ids) . ")";
        $modules = array_map(function ($module) {
            return "module LIKE '%{$module}%'";
        }, $modules);
        $module_query = implode(" OR ", $modules);

        $query = "SELECT sl.ID, sl.row_id, CONCAT(UPPER(SUBSTRING(sl.action, 1, 1)),SUBSTRING(sl.action, 2)) as action, '{$module}' as module, CONCAT(ui.first_name, ' ', ui.last_name) as author, sl.logged_at as date FROM system_logs sl JOIN user_information ui ON sl.logged_by = ui.account_id WHERE row_id IN {$id_query} AND ({$module_query}) ORDER BY sl.logged_at DESC";
        $this->setStatement($query);
        $this->statement->execute();
        return $this->statement->fetchAll();
    }


}
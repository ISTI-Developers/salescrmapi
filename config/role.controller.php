<?php
require_once "controller.php";

class RoleController extends Controller
{
    function get_roles($role_id = null, $with_description = false)
    {
        $query = "SELECT ur.ID as role_id, rp.ID as role_perm_id, ur.name as role,";

        if ($with_description) {
            $query .= " ur.description, ";
        }

        $query .= "ur.status as status_id, us.name as status, m.ID as module_id, m.name, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete 
        FROM user_roles ur LEFT JOIN role_permissions rp ON rp.role_id = ur.ID 
        LEFT JOIN modules m ON m.ID = rp.module_id
        JOIN user_status us ON ur.status = us.ID WHERE ur.status <> 5 ";
        if ($role_id != null) {
            $query .= "AND ur.ID  = ?";
        }
        $this->setStatement($query);

        $this->statement->execute($role_id ? [$role_id] : []);
        return $this->statement->fetchAll();
    }

    public function get_role_permissions($role_id = null)
    {
        $params = [];
        $query = "SELECT ur.ID as role_id, ur.name, ur.description, p.ID as permission_id, p.permission, us.name as status FROM user_roles ur LEFT JOIN permissions p ON ur.ID = p.role_id LEFT JOIN user_status us ON us.ID = ur.status WHERE ur.status <> 5 ";

        if ($role_id) {
            $query .= "AND ur.ID = ? ";
            array_push($params, $role_id);
        }
        $this->setStatement($query);
        $this->statement->execute($params);
        return $this->statement->fetchAll();
    }

    function get_modules()
    {
        $this->setStatement("SELECT m.ID as m_id, m.name, us.ID as status_id, us.name as status FROM modules m JOIN user_status us ON us.ID = status ORDER BY m.name ASC");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function insert_module($module_name)
    {
        $this->setStatement("INSERT INTO modules (name) VALUES (?)");
        if ($this->statement->execute([$module_name])) {
            return $this->connection->lastInsertId();
        } else {
            return false;
        }

    }

    function toggle_module($module_id, $status)
    {
        $this->setStatement("UPDATE modules SET status = ? WHERE ID = ?");
        return $this->statement->execute([$status, $module_id]);
    }

    function get_user_permissions($user_id, $permission)
    {
        $this->setStatement("SELECT permission FROM permissions p JOIN user_roles ur ON ur.ID = p.role_id JOIN user_accounts ua ON ua.role_id = ur.ID WHERE ua.ID = ? AND p.permission = ?");
        $this->statement->execute([$user_id, $permission]);
        return $this->statement->rowCount() > 0;
    }

    function insert_role_permission($role_id, $permission)
    {
        $this->setStatement("INSERT INTO permissions (role_id, permission) VALUES (?,?)");
        return $this->statement->execute([$role_id, $permission]);
    }

    function insert_role($name, $description)
    {
        $this->setStatement("INSERT INTO user_roles (name, description) VALUES (?,?)");
        $this->statement->execute([$name, $description]);
        return $this->connection->lastInsertId();
    }
    function update_role($role_id, $name, $description)
    {
        $this->setStatement("UPDATE user_roles SET name = ?, description = ? WHERE ID = ?");
        return $this->statement->execute([$name, $description, $role_id]);
    }
    function manage_role($role_id, $status_id)
    {
        $this->setStatement("UPDATE user_roles SET status = ? WHERE ID = ?");
        return $this->statement->execute([$status_id, $role_id]);
    }
    function update_role_permission($role_id, $permission)
    {
        $this->setStatement("DELETE from permissions WHERE role_id = ? AND permission = ?");
        return $this->statement->execute([$role_id, $permission]);
    }

    function get_company_roles()
    {
        $this->setStatement("SELECT c.ID, c.name as company, p.permission FROM company_roles p LEFT JOIN companies c ON p.company_id = c.ID");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function check_company_roles($company_id)
    {
        $this->setStatement("SELECT permission FROM company_roles WHERE company_id = ?");
        $this->statement->execute([$company_id]);
        return $this->statement->fetchColumn(0);
    }

    public function update_company_role($company_id, $permission)
    {
        $this->setStatement("UPDATE company_roles SET permission = ? WHERE company_id = ?");
        return $this->statement->execute([$permission, $company_id]);
    }
}
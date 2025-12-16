<?php
require_once __DIR__ . "/controller.php";
class TableController extends Controller
{

    function updateUserSalesGroup($salesGroupId, $userId)
    {
        $this->setStatement("INSERT INTO `us_user_sales_group_assignation` (`user_id`, `sales_group_id`) VALUES (?,?)");
        return $this->statement->execute([$userId, $salesGroupId]);
    }

    //USER FUNCTIONS
    function getAllUsers()
    {
        $this->setStatement("SELECT 
            uui.user_info_id AS user_id,
            uul.user_login_id AS login_id,
            uui.first_name, 
            uui.middle_name, 
            uui.last_name,
            uul.email_address,
            uc.company_id as company, 
            usg.sales_group_id as sales_group,
            uul.username,
            uul.account_status AS status,
            uui.image_source AS image,
            uul.user_level_access AS role
        FROM 
            us_user_info uui
        LEFT JOIN 
            us_user_login uul ON uui.user_login_id = uul.user_login_id
        LEFT JOIN 
            us_user_sales_group_assignation uusa ON uui.user_info_id = uusa.user_id
        LEFT JOIN 
            us_sales_group usg ON usg.sales_group_id = uusa.sales_group_id
        LEFT JOIN 
            us_company uc ON usg.company_id = uc.company_id
        WHERE
            uul.deleted = 0
        ORDER BY 
            uul.account_status ASC, uui.first_name ASC;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetchSalesUnit($sales_group_id)
    {
        $this->setStatement("SELECT sales_group_id, sales_group_name FROM `us_sales_group` WHERE sales_group_id = ?");
        $this->statement->execute([$sales_group_id]);
        return $this->statement->fetch();
    }

    function fetchAllSalesMembersInCompany($company_id)
    {
        $this->setStatement("SELECT a.user_info_id, CONCAT(a.first_name, ' ' , a.last_name) AS full_name,a.image_source,b.email_address FROM us_user_info a
        LEFT JOIN us_user_login b ON a.user_login_id = b.user_login_id 
        LEFT JOIN us_company_user_assignation c ON a.user_info_id = c.user_id
        WHERE b.user_level_access = 4 AND c.company_id = ?;");
        $this->statement->execute([$company_id]);
        return $this->statement->fetchAll();
    }

    function fetchAllMembers()
    {
        $this->setStatement("SELECT us_user_info.user_info_id, CONCAT(us_user_info.first_name, ' ' , us_user_info.last_name) AS full_name,image_source,us_user_login.email_address FROM us_user_info 
        LEFT JOIN us_user_login ON us_user_login.user_login_id = us_user_info.user_login_id 
        WHERE us_user_login.user_level_access >= 2;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetchAllAvailableSalesMember()
    {
        $this->setStatement("SELECT us_user_info.user_info_id as user_id, us_user_login.user_login_id as login_id, us_user_info.first_name, us_user_info.middle_name, us_user_info.last_name, us_user_info.image_source, us_user_login.email_address, us_user_login.user_level_access FROM us_user_info LEFT JOIN us_user_login ON us_user_login.user_login_id = us_user_info.user_login_id LEFT JOIN us_user_sales_group_assignation ON us_user_sales_group_assignation.user_id = us_user_info.user_info_id WHERE us_user_login.user_level_access >= 2 AND us_user_login.account_status = 'active' AND us_user_sales_group_assignation.user_sales_group_assignation_id IS NULL;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetchSalesGroupMembers($sales_group_id)
    {
        $this->setStatement("SELECT us_user_login.user_login_id as login_id, us_user_info.user_info_id as user_id ,concat(first_name, ' ', last_name) as full_name,us_sales_group.sales_group_name, us_user_login.user_level_access
        FROM us_user_info 
        LEFT JOIN us_user_login ON us_user_login.user_login_id = us_user_info.user_login_id
        LEFT JOIN us_user_sales_group_assignation ON us_user_sales_group_assignation.user_id = us_user_info.user_info_id
        LEFT JOIN us_sales_group ON us_user_sales_group_assignation.sales_group_id = us_sales_group.sales_group_id 
        WHERE us_sales_group.sales_group_id = ?;");
        $this->statement->execute([$sales_group_id]);
        return $this->statement->fetchAll();
    }

    function fetchAllSalesGroup()
    {
        $this->setStatement(    "SELECT * FROM us_sales_group");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetchSalesGroupHeadForSelection($company_id)
    {
        $this->setStatement("SELECT DISTINCT a.user_info_id as user_id, c.sales_group_id,c.sales_group_name,
         CONCAT(a.first_name, ' ', a.last_name) AS sales_group_head         
        FROM us_user_info a 
        LEFT JOIN us_user_login b ON a.user_info_id = b.user_login_id 
        LEFT JOIN us_sales_group c ON b.user_login_id = c.sales_group_head 
        LEFT JOIN us_company_user_assignation d ON a.user_info_id = d.user_id 
        WHERE b.user_level_access = 3 AND d.company_id = ?;
        ");
        $this->statement->execute([$company_id]);
        return $this->statement->fetchAll();
    }

    function fetchUserRoles()
    {
        $this->setStatement("SELECT * FROM us_user_level");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetchUserInfo($user_id)
    {
        $this->setStatement("SELECT uui.user_info_id, uul.user_login_id, uui.first_name, uui.middle_name, uui.last_name, GROUP_CONCAT(DISTINCT uul.username) AS username, GROUP_CONCAT(DISTINCT uul.email_address) AS email_address, GROUP_CONCAT(DISTINCT uul.account_status) AS account_status, GROUP_CONCAT(DISTINCT uul.user_level_access) AS user_level_access, GROUP_CONCAT(DISTINCT uca.company_id) AS company_id, GROUP_CONCAT(DISTINCT usg.sales_group_id) AS sales_group_id FROM us_user_info AS uui 
        LEFT JOIN us_user_login AS uul ON uui.user_login_id = uul.user_login_id 
        LEFT JOIN us_company_user_assignation AS uca ON uul.user_login_id = uca.user_id 
        LEFT JOIN us_user_sales_group_assignation AS usg ON uul.user_login_id = usg.user_id 
        WHERE uui.user_info_id = ? GROUP BY uui.user_info_id, uui.first_name, uui.middle_name, uui.last_name;");
        $this->statement->execute([$user_id]);
        return $this->statement->fetch();
    }

    function insertUserLoginDetails($username, $password, $email, $accountStatus, $userLevelAccess)
    {
        $this->setStatement("INSERT INTO us_user_login(username,password,email_address,account_status,user_level_access,deleted) 
        VALUES(:username,:password,:email,:accountStatus,:userLevel, 0);");
        $this->statement->execute([":username" => $username, ":password" => $password, ":email" => $email, ":accountStatus" => $accountStatus, ":userLevel" => $userLevelAccess]);
        return $this->connection->lastInsertId();
    }

    function insertUserInfo($firstname, $middlename, $lastname, $userLoginId)
    {
        $this->setStatement("INSERT INTO us_user_info(first_name,middle_name,last_name,user_login_id) 
        VALUES(:firstName,:middleName,:lastName,:userLoginId);");
        return $this->statement->execute([":firstName" => $firstname, ":middleName" => $middlename, ":lastName" => $lastname, ":userLoginId" => $userLoginId]);
    }

    function updatePassword($password, $user_id)
    {
        $this->setStatement("UPDATE `us_user_login` SET `password` = ?, `account_status` = 'active' WHERE user_login_id = ?");
        return $this->statement->execute([$password, $user_id]);
    }

    function updateUserRole($user_id, $role)
    {
        $this->setStatement("UPDATE us_user_login JOIN us_user_info ON us_user_login.user_login_id = us_user_info.user_login_id SET us_user_login.user_level_access = ? WHERE us_user_info.user_info_id = ?");
        return $this->statement->execute([$role, $user_id]);
    }

    function updateUser($user_id, $first_name, $middle_name, $last_name, $email_address, $username, $role)
    {
        $this->setStatement("UPDATE `us_user_info` AS uui JOIN `us_user_login` AS uul ON uui.user_login_id = uul.user_login_id SET uui.first_name = ?, uui.middle_name = ?, uui.last_name = ?, uul.email_address = ?, uul.username = ?, uul.user_level_access = ? WHERE uui.user_info_id = ?");
        return $this->statement->execute([$first_name, $middle_name, $last_name, $email_address, $username, $role, $user_id]);
    }

    function updateUserStatus($login_id, $status)
    {
        $this->setStatement("UPDATE us_user_login SET account_status = ? WHERE user_login_id = ?");
        return $this->statement->execute([$status, $login_id]);
    }

    function deleteUser($login_id)
    {
        $this->setStatement("UPDATE us_user_login SET deleted = 1 WHERE user_login_id = ?");
        return $this->statement->execute([$login_id]);
    }
}

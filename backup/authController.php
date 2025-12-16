<?php
require_once __DIR__."/controller.php";
class AuthController extends Controller
{
    function loginAccount($uName, $pWord)
    {
        $this->setStatement("SELECT * FROM us_user_login WHERE username = :username AND password = :password");
        $this->statement->execute([':username' => $uName, ':password' => $pWord]);
        return $this->statement->fetch();
    }
    function fetchUserDetails($userId)
    {
        $this->setStatement("SELECT a.user_info_id,first_name,a.middle_name,a.last_name,a.image_source,b.company_id,c.sales_group_id 
        FROM us_user_info a
        LEFT JOIN us_company_user_assignation b ON a.user_info_id = b.user_id
        LEFT JOIN us_user_sales_group_assignation c ON a.user_info_id = c.user_id
        WHERE user_login_id = :userId");
        $this->statement->execute([':userId' => $userId]);
        return $this->statement->fetch();
    }
    function fetchUserAccess($userLevelAccess)
    {
        $this->setStatement("SELECT * FROM `us_user_level` WHERE user_level_id = :userLevelAccess");
        $this->statement->execute([':userLevelAccess' => $userLevelAccess]);
        return $this->statement->fetch();
    }

    function verifyToken($login_id, $token)
    {
        $this->setStatement("SELECT * FROM `us_user_login` WHERE user_login_id = ? AND password = ?");
        $this->statement->execute([$login_id, $token]);
        return $this->statement->rowCount();
    }
    function resetPassword($login_id, $password)
    {
        // First, update the password
        $this->setStatement("UPDATE `us_user_login` SET password = ?, account_status = 'pass_reset' WHERE user_login_id = ?");
        $this->statement->execute([$password, $login_id]);

        // After updating, select the updated user information
        $this->setStatement("SELECT uui.first_name, uui.last_name, uul.email_address FROM `us_user_info` AS uui LEFT JOIN `us_user_login` AS uul ON uul.user_login_id = uui.user_login_id WHERE uul.user_login_id = ?;");
        $this->statement->execute([$login_id]);

        // Fetch and return the updated user data
        return $this->statement->fetch();
    }
}

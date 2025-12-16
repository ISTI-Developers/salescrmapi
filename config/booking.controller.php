<?php
require_once __DIR__ . "/controller.php";

class BookingController extends Controller
{
    public function get_bookings($id = null, $site_code = null): array
    {
        $query = "SELECT * FROM site_bookings";
        $params = [];

        if ($id !== null && $site_code !== null) {
            $query .= " WHERE ID = ? AND site_code = ?";
            $params = [$id, $site_code];
        } else if ($id !== null) {
            $query .= " WHERE ID = ?";
            $params = [$id];
        } else if ($site_code !== null) {
            $query .= " WHERE site_code = ?";
            $params = [$site_code];
        }

        $this->setStatement($query);
        $this->statement->execute($params);
        return $this->statement->fetchAll();
    }

    public function insert_booking($data)
    {
        extract($data);
        $query = "INSERT INTO `site_bookings`(`site_code`, `srp`, `booking_status`, `client`, `account_executive`, `date_from`, `date_to`, `monthly_rate`, `site_rental`, `old_client`, `remarks`, `created_at`,`modified_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = [$site, $srp, $booking_status, $client, $account_executive, $start, $end, $monthly_rate, $site_rental, $old_client ?? "N/A", $remarks, $created_at, $created_at];

        $this->setStatement($query);
        $this->statement->execute($params);
        if ($this->statement->rowCount() > 0) {
            return $this->connection->lastInsertId();
        }
    }

    public function insert_booking_without_site($data)
    {
        extract($data);
        $query = "INSERT INTO `site_bookings`(`site_code`, `srp`, `booking_status`, `client`, `account_executive`, `date_from`, `date_to`, `monthly_rate`, `site_rental`, `old_client`, `remarks`, `created_at`,`modified_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $params = ["---", $srp, $booking_status, $client, $account_executive, $start, $end, $monthly_rate, $site_rental, "---", $remarks, $created_at, $created_at];

        $this->setStatement($query);
        $this->statement->execute($params);
        if ($this->statement->rowCount() > 0) {
            $booking_id = $this->connection->lastInsertId();
            $this->setStatement('INSERT INTO temp_bookings (`booking_id`, `area`, `address`,`facing`, `size` ) VALUES (?,?,?,?,?);');
            if ($this->statement->execute([$booking_id, $area, $address, $facing, $size])) {
                $this->setStatement("SELECT  tb.address, tb.size, tb.facing, sb.booking_status, sb.client, sb.old_client, sb.account_executive, CONCAT( TIMESTAMPDIFF(MONTH, sb.date_from, DATE_ADD(sb.date_to, INTERVAL 1 DAY)), 'mo/s (', DATE_FORMAT(sb.date_from, '%b %d'), ' - ', DATE_FORMAT(sb.date_to, '%b %d, %Y'), ')' ) AS term_duration,FORMAT(sb.site_rental,2) as site_rental, FORMAT(sb.srp,2) as srp,FORMAT(sb.monthly_rate,2) as monthly_rate, sb.remarks FROM site_bookings sb JOIN temp_bookings tb ON tb.booking_id = sb.ID WHERE sb.ID = ?;");
                $this->statement->execute([$booking_id]);
                return $this->statement->fetch();
            }
        }
    }

    public function get_pre_bookings()
    {
        $this->setStatement("SELECT tb.ID, sb.ID as booking_id, tb.area, address, sb.site_rental, facing, sb.date_from, sb.date_to, sb.client, sb.account_executive, sb.srp, sb.monthly_rate, sb.booking_status, sb.remarks FROM temp_bookings tb JOIN site_bookings sb ON tb.booking_id = sb.ID WHERE sb.site_code = '---' OR (sb.booking_status = 'CANCELLED' AND DATE(sb.created_at) <> DATE(sb.modified_at));");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    public function get_pre_booking($ID)
    {
        $this->setStatement("SELECT sb.site_code, tb.address, tb.size, tb.facing as board_facing, sb.booking_status, sb.client, sb.old_client, sb.account_executive, CONCAT( TIMESTAMPDIFF(MONTH, sb.date_from, DATE_ADD(sb.date_to, INTERVAL 1 DAY))), 'mo/s (', DATE_FORMAT(sb.date_from, '%b %d'), ' - ', DATE_FORMAT(sb.date_to, '%b %d, %Y'), ')' ) AS term_duration,FORMAT(sb.site_rental,2) as site_rental, FORMAT(sb.srp,2) as srp,FORMAT(sb.monthly_rate,2) as monthly_rate, sb.remarks FROM temp_bookings tb JOIN site_bookings sb ON tb.booking_id = sb.ID WHERE booking_id = ?;");
        $this->statement->execute([$ID]);
        return $this->statement->fetch();
    }

    public function get_all_bookings()
    {
        $this->setStatement("SELECT sb.ID, s.structure_code, s.site_code,s.city as area, s.address, s.size, s.board_facing as facing, sb.booking_status, sb.client, sb.old_client as 'previous_client', sb.account_executive, CONCAT( TIMESTAMPDIFF(MONTH, sb.date_from, DATE_ADD(sb.date_to, INTERVAL 1 DAY)), 'mo/s (', DATE_FORMAT(sb.date_from, '%b %d'), ' - ', DATE_FORMAT(sb.date_to, '%b %d, %Y'), ')' ) AS term_duration,FORMAT(sb.site_rental,2) as site_rental, FORMAT(sb.srp,2) as srp,FORMAT(sb.monthly_rate,2) as monthly_rate, sb.remarks, sb.modified_at as booking_date FROM site_bookings sb JOIN sites s ON s.site_code = sb.site_code ORDER BY sb.modified_at DESC;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }
    public function get_booking_for_notification($booking_id)
    {
        $this->setStatement("SELECT s.site_code, s.address, s.size, s.board_facing, s.site_owner, sb.booking_status, sb.client, sb.old_client, sb.account_executive, CONCAT( TIMESTAMPDIFF(MONTH, sb.date_from, DATE_ADD(sb.date_to, INTERVAL 1 DAY)), 'mo/s (', DATE_FORMAT(sb.date_from, '%b %d'), ' - ', DATE_FORMAT(sb.date_to, '%b %d, %Y'), ')' ) AS term_duration,FORMAT(sb.site_rental,2) as site_rental, FORMAT(sb.srp,2) as srp,FORMAT(sb.monthly_rate,2) as monthly_rate, sb.remarks FROM site_bookings sb JOIN sites s ON s.site_code = sb.site_code WHERE sb.ID = ?;");
        $this->statement->execute([$booking_id]);
        return $this->statement->fetch();
    }

    public function get_emails($remarks)
    {
        $this->setStatement("SELECT * FROM email_recipients WHERE remarks = ? AND status <> 2");
        $this->statement->execute([$remarks]);
        return $this->statement->fetchAll();
    }

    public function cancel_booking($id, $modified_at, $remarks)
    {
        $query = "UPDATE site_bookings SET booking_status = ?, remarks = ?, modified_at = ? WHERE ID = ?";
        $params = ["CANCELLED", $remarks, $modified_at, $id];
        $this->setStatement($query);
        $this->statement->execute($params);
        return $this->statement->rowCount() > 0;
    }

    public function tag_pre_site_booking($booking_id, $site_code)
    {
        $query = "UPDATE site_bookings SET site_code = ? WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$site_code, $booking_id]);
    }

    public function update_booking($booking_id, $monthly_rate, $booking_status, $date_from, $date_to, $remarks, $modified_at)
    {
        $query = "UPDATE site_bookings SET monthly_rate = ?, booking_status = ?, date_from = ?, date_to = ?, remarks = ?, modified_at = ? WHERE ID = ?";
        $this->setStatement($query);
        return $this->statement->execute([$monthly_rate, $booking_status, $date_from, $date_to, $remarks, $modified_at, $booking_id]);
    }
}

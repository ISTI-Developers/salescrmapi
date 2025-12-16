<?php
require_once __DIR__ . "/controller.php";

class SiteController extends Controller
{

        public function get_sites()
        {
                $query = "SELECT * FROM `sites`  WHERE status <> 5 ORDER BY structure_code, site_code;";
                $this->setStatement($query);
                $this->statement->execute();
                return $this->statement->fetchAll();
        }
        public function get_landmarks()
        {
                $query = "SELECT ID, display_name, latitude, longitude, types FROM landmarks;";
                $this->setStatement($query);
                $this->statement->execute();
                return $this->statement->fetchAll();
        }
        public function get_site_images($structure, $segment)
        {
                $query = "SELECT image FROM (SELECT s.structure_id, s.structure_code,CONCAT(ss.facing_no, ss.transformation, LPAD(ss.segment,2,'0')) as segment_code, ss.image FROM hd_structure s JOIN hd_structure_segment ss ON ss.structure_id = s.structure_id WHERE s.structure_code LIKE ?) A ";

                if ($segment !== null) {
                        $query .= " WHERE segment_code = ?";
                }
                // return $query;

                $this->setStatement($query);
                $this->statement->execute($segment !== null ? ["$structure%", $segment] : ["$structure%"]);
                if ($this->statement->rowCount() > 0) {
                        $images = (array) $this->statement->fetchColumn(0);
                        // return $images;
                        if (count($images) > 0) {

                                $this->setStatement(query: "SELECT * FROM hd_file_upload WHERE upload_id IN (" . implode(",", $images) . ") AND upload_path NOT LIKE ? ORDER BY date_uploaded;");
                                $this->statement->execute(["%$structure%"]);
                                return $this->statement->fetchAll();
                        } else {
                                throw new Exception("No images found for this site.");
                        }
                }
        }

        public function get_overriden_contracts()
        {
                $query = "SELECT * FROM contract_override";
                $this->setStatement($query);
                $this->statement->execute();
                return $this->statement->fetchAll();
        }

        public function get_last_date_inserted()
        {
                $this->setStatement("SELECT MAX(created_at) as created_at FROM sites;");
                $this->statement->execute();
                return $this->statement->fetchColumn();
        }
        public function get_latest_sites()
        {
                $query = "SELECT s.structure_code, CONCAT(s.structure_code, '-', ss.facing_no, ss.transformation, LPAD(ss.segment,2,'0')) as site_code, s.address, ss.date_created, ss.facing FROM hd_structure s JOIN hd_structure_segment ss ON s.structure_id = ss.structure_id WHERE s.product_division_id = 1 AND MONTH(ss.date_created) = MONTH(NOW()) AND YEAR(ss.date_created) = YEAR(NOW()) ORDER BY ss.date_created DESC;";
                $this->setStatement($query);
                $this->statement->execute();
                return $this->statement->fetchAll();
        }

         public function update_status($status, $site_code)
        {
                $query = "UPDATE sites SET status = ? WHERE site_code = ?";
                $this->setStatement($query);
                return $this->statement->execute([$status, $site_code]);
        }

        public function update_remarks($remarks, $site_code)
        {
                $query = "UPDATE sites SET remarks = ? WHERE site_code = ?";
                $this->setStatement($query);
                return $this->statement->execute([$remarks, $site_code]);
        }
        public function update_price($price, $site_code)
        {
                $query = "UPDATE sites SET price = ? WHERE site_code = ?";
                $this->setStatement($query);
                return $this->statement->execute([$price, $site_code]);
        }

        public function insert_site(array $columns, array $values)
        {
                // Create a string of placeholders like ?, ?, ?, ... based on number of values
                $placeholders = implode(', ', array_fill(0, count($values), '?'));

                // Build the SQL statement safely
                $this->setStatement("INSERT INTO sites (" . implode(', ', $columns) . ") VALUES ($placeholders)");

                // Execute the statement with the provided values
                return $this->statement->execute($values);
        }
        public function update_site(array $columns, array $values, string $whereClause = "", array $whereValues = [])
        {
                // Build column placeholders like: column1 = ?, column2 = ?, ...
                $setClause = implode(', ', array_map(fn($col) => "$col = ?", $columns));

                // Combine all values (update + where) for execution
                $params = array_merge($values, $whereValues);

                // Build the SQL statement
                $sql = "UPDATE sites SET $setClause";
                if ($whereClause) {
                        $sql .= " WHERE $whereClause";
                }

                $this->setStatement($sql);
                return $this->statement->execute($params);
        }

        public function override_contract_end_date($data)
        {
                extract($data);
                $query = 'INSERT INTO contract_override (
                        site_code,
                        brand,
                        original_end_date,
                        adjusted_end_date,
                        adjustment_reason
                        ) VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        original_end_date = VALUES(original_end_date),
                        adjusted_end_date = VALUES(adjusted_end_date),
                        adjustment_reason = VALUES(adjustment_reason),
                        modified_at = NOW();
                        ';
                $params = [$site_code, $brand, $end_date, $date, $reason];
                $this->setStatement($query);
                $this->statement->execute($params);
                return $this->statement->rowCount() > 0;
        }
}

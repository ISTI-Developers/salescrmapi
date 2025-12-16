<?php
require_once __DIR__ . "/controller.php";
require_once __DIR__ . "/env.php";

class ContractController extends Controller
{
    function fetch_contracts_from_qne($database = QNE_DEFAULT_DB)
    {
        $this->connection->exec("USE {$database}");
        $this->setStatement("SELECT * FROM (SELECT SO.SalesOrderCode AS contract_no, SO.DebtorName as client, (SELECT CompanyName FROM CompanyProfile) as company, CONVERT(DATE, MIN(SOD.DateRef1)) AS date_from, CONVERT(DATE,MAX(SOD.DateRef2)) AS date_to, 'Approved' AS  contract_status, SO.NetTotalAmountLocal AS grand_total, SO.has_addendum FROM SalesOrderDetails AS SOD
        INNER JOIN (
        SELECT ROW_NUMBER() OVER (PARTITION BY LEFT(SalesOrderCode, 10) ORDER BY ReferenceNo DESC) AS row_number, '2' has_addendum, LEFT(SalesOrderCode, 10) Salesorder, * FROM SalesOrders
        WHERE LEFT(RIGHT(SalesOrderCode,2),1) = '-'
        UNION ALL
        SELECT '1','0',SalesOrderCode Salesorder, * FROM SalesOrders
        WHERE LEFT(RIGHT(SalesOrderCode,2),1) <> '-'
        ) AS SO ON SO.id = SOD.SalesOrderId
        WHERE SO.isCancelled = 0 AND SO.row_number = 1
        GROUP BY SO.SalesOrderCode, SO.DebtorName,SO.NetTotalAmountLocal, SO.has_addendum) A
        WHERE A.date_from IS NOT NULL and A.date_to IS NOT NULL AND GETDATE() BETWEEN A.date_from AND A.date_to");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetch_contracts_from_unis()
    {
        $this->setStatement("SELECT DISTINCT a.contract_no, a.customer_name as client, e.company, a.date_from, a.date_to, d.contract_status, a.grandtotal as grand_total,a.has_addendum
    FROM hd_contract a
    LEFT JOIN hd_contract_structure b ON a.contract_id = b.contract_id
    LEFT JOIN hd_structure c ON b.structure_id = c.structure_id
    LEFT JOIN hd_contract_status d ON d.contract_status_id = a.contract_status_id
    LEFT JOIN hd_users_company e ON a.contract_type_id = e.company_id
    WHERE CURDATE( )
    BETWEEN a.date_from
    AND a.date_to
    AND a.contract_status_id
    IN ( 1, 2, 3 )
    AND a.contract_type_id = 1
    AND has_addendum IN ( 2, 0 ) ");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetch_structures_from_unis()
    {
        // $this->setStatement("SELECT a.structure_code, a.address, b.category, c.product_division, d.status FROM hd_structure a JOIN hd_structure_category b ON a.category_id = b.category_id JOIN hd_structure_product_division c ON a.product_division_id = c.product_division_id JOIN hd_structure_status d ON a.status_id = d.status_id;");
        $this->setStatement("SELECT s.structure_code, s.address, sc.category, spd.product_division, ss.segment, ss.segment_description, ss.facing, ss.height, ss.width, st.status, MIN(cs.date_from) as earliest_contract_date
FROM hd_contract_structure cs 
JOIN hd_structure s ON s.structure_id = cs.structure_id
JOIN hd_structure_segment ss ON s.structure_id = ss.structure_id
JOIN hd_structure_category sc ON s.category_id = sc.category_id
JOIN hd_structure_product_division spd ON s.product_division_id = spd.product_division_id
JOIN hd_structure_status st ON ss.status_id = st.status_id
WHERE cs.date_from BETWEEN '2016-01-01' AND '2023-12-31'
GROUP BY 
    s.structure_code, 
    ss.segment
ORDER BY 
    cs.date_from");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetch_available_sites()
    {
        $this->setStatement("SELECT c.contract_no, s.structure_code, CONCAT(s.structure_code,'-',ss.facing_no,ss.transformation,LPAD(ss.segment,2,'0')) as site_code,cs.product, cs.date_to as end_date, DATEDIFF(DATE(cs.date_to),DATE(NOW())) as remaining_days FROM hd_contract_structure cs
JOIN hd_contract c ON c.contract_id = cs.contract_id
JOIN hd_contract_structure_segment css ON cs.contract_structure_id = css.contract_structure_id
JOIN hd_structure_segment ss ON css.segment_id = ss.segment_id
JOIN hd_structure s ON ss.structure_id = s.structure_id
WHERE DATEDIFF(DATE(cs.date_to),DATE(NOW())) <= 60 && DATEDIFF(DATE(cs.date_to),DATE(NOW())) >= 0 AND s.product_division_id = 1 ORDER BY remaining_days ASC;");
        $this->statement->execute();
        return $this->statement->fetchAll();
    }

    function fetch_electricity_bill()
    {
        // $this->setStatement("SELECT a.structure_code, a.address, b.category, c.product_division, d.status FROM hd_structure a JOIN hd_structure_category b ON a.category_id = b.category_id JOIN hd_structure_product_division c ON a.product_division_id = c.product_division_id JOIN hd_structure_status d ON a.status_id = d.status_id;");
        $this->setStatement('SELECT s.structure_code, s.address, spd.product_division_name, SUM(ieb.amount) as TOTAL_AMOUNT, MIN(ieb.date_from) as START_DATE, MAX(ieb.date_to) as END_DATE
FROM hd_structure s
JOIN hd_structure_product_division spd ON s.product_division_id = spd.product_division_id
JOIN hd_invoice_electrical_bill_detail ieb ON ieb.structure_id = s.structure_id
WHERE ieb.date_from > "2023-12-30" AND ieb.date_to < "2025-01-01"
GROUP BY s.structure_code;
');
        $this->statement->execute();
        return $this->statement->fetchAll();
    }


    function showTables()
    {
        try {
            $pdo = new PDO("sqlsrv:Server=" . QNE_SERVER, QNE_USERNAME, QNE_PASSWORD);
            $query = $pdo->query("SELECT name FROM sys.databases");

            $databases = $query->fetchAll(PDO::FETCH_COLUMN);

            foreach ($databases as $database) {
                echo "{$database} ---\n";
                echo "\n\n\n";
            }
        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
    }
}
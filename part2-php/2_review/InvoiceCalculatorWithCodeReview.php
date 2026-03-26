<?php
class InvoiceCalculatorWithCodeReview
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    // The function calculate has multiples return boolean and float
    // This function does not have a specific return value(s).
    // The parameters do not have a data type like (int $contractId, int $month)
    public function calculate($contractId, $month) // <-- Add the retunr(s)
    {
        // The variable $contractId is concatenated directly into the SQL query, which increases the risk of injection.
        $contract = $this->db->query(
            // Using the c.* is risky, as it get all columns unnecessarily and exposes sensitive data.
            "SELECT c.*, t.code as tariff_code, t.price_per_kwh, t.fixed_monthly
             FROM contracts c JOIN tariffs t ON c.tariff_id = t.id
             WHERE c.id = $contractId"  // <---- Risk of SQL Injection
        )->fetch();
        
        if (!$contract) {
            echo "Contract not found";  // <---- Use the Exception class instead of echo
            return false;               // <---- Not necesarry if use an exception
        }
        
        // The variable $month is concatenated directly into the SQL query, which increases the risk of injection.
        $readings = $this->db->query(
            "SELECT SUM(kwh_consumed) as total
             FROM meter_readings
             WHERE contract_id = $contractId
             AND FORMAT(reading_date, 'yyyy-MM') = '$month'"    // <---- Risk of SQL Injection
        )->fetch();
        
        $totalKwh = $readings['total'] ?? 0;
        if (strpos($contract['tariff_code'], 'FIX') !== false) {    // <---- Do not use strpos(), the name could change.
            $amount = $totalKwh * $contract['price_per_kwh'];   // <---- Put the definition of $amount before the if.
            $amount += $contract['fixed_monthly'];
            if ($contract['tariff_code'] == 'FIX_PROMO') {  // What happen if a new tariff were added?
                $amount = $amount * 0.9;
            }
        } elseif (strpos($contract['tariff_code'], 'INDEX') !== false) { // <---- Do not use strpos(), the name could change.
            $spotPrice = file_get_contents(
                "https://api.energy-market.eu/spot?month=$month"    
                // <--- What happens if the service fails? Add some control
                // <--- and the timeout ? what happends if the service takes 10 seconds? 
            );
            $spotData = json_decode($spotPrice, true);
            $amount = $totalKwh * $spotData['avg_price'];
            $amount += $contract['fixed_monthly'];
            if ($totalKwh > 500) {
                $amount = $amount * 0.95;   // <--- Magics Numbers move to configurations
            }
        } elseif ($contract['tariff_code'] == 'FLAT_RATE') {    // <---- What happen if a new tariff were added?
            $amount = $contract['fixed_monthly'];
        } else {
            echo "Unknown tariff type"; // <---- Use the Exception class instead of echo
            return false;               // <---- Not necesarry if use an exception
        }
        
        if ($contract['country'] == 'PT') { // <---- What happen if a new country were added?
            $tax = $amount * 0.23;  // <--- Magics Numbers move to configurations
        } else {
            $tax = $amount * 0.21;  // <--- Magics Numbers move to configurations
        }
        
        $total = $amount + $tax;
        $this->db->query(
            "INSERT INTO invoices (contract_id, billing_period, total_kwh, total_amount, status)
             VALUES ($contractId, '$month', $totalKwh, $total, 'draft')"
        );  // <--- Before returning, check that the invoice has been inserted.
        
        echo "Invoice created: $total EUR"; // <---- Do not use echo, use logging to report events inside the process.
        return $total;
    }
}
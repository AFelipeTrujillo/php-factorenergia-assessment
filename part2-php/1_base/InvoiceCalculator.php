<?php
class InvoiceCalculator
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function calculate($contractId, $month)
    {
        $contract = $this->db->query(
            "SELECT c.*, t.code as tariff_code, t.price_per_kwh, t.fixed_monthly
             FROM contracts c JOIN tariffs t ON c.tariff_id = t.id
             WHERE c.id = $contractId"
        )->fetch();
        
        if (!$contract) {
            echo "Contract not found";
            return false;
        }
        
        $readings = $this->db->query(
            "SELECT SUM(kwh_consumed) as total
             FROM meter_readings
             WHERE contract_id = $contractId
             AND FORMAT(reading_date, 'yyyy-MM') = '$month'"
        )->fetch();
        
        $totalKwh = $readings['total'] ?? 0;
        if (strpos($contract['tariff_code'], 'FIX') !== false) {
            $amount = $totalKwh * $contract['price_per_kwh'];
            $amount += $contract['fixed_monthly'];
            if ($contract['tariff_code'] == 'FIX_PROMO') {
                $amount = $amount * 0.9;
            }
        } elseif (strpos($contract['tariff_code'], 'INDEX') !== false) {
            $spotPrice = file_get_contents(
                "https://api.energy-market.eu/spot?month=$month"
            );
            $spotData = json_decode($spotPrice, true);
            $amount = $totalKwh * $spotData['avg_price'];
            $amount += $contract['fixed_monthly'];
            if ($totalKwh > 500) {
                $amount = $amount * 0.95;
            }
        } elseif ($contract['tariff_code'] == 'FLAT_RATE') {
            $amount = $contract['fixed_monthly'];
        } else {
            echo "Unknown tariff type";
            return false;
        }
        
        if ($contract['country'] == 'PT') {
            $tax = $amount * 0.23;
        } else {
            $tax = $amount * 0.21;
        }
        
        $total = $amount + $tax;
        $this->db->query(
            "INSERT INTO invoices (contract_id, billing_period, total_kwh, total_amount, status)
             VALUES ($contractId, '$month', $totalKwh, $total, 'draft')"
        );
        
        echo "Invoice created: $total EUR";
        return $total;
    }
}
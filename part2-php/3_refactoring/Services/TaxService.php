<?php

namespace App\Service;

use App\Entity\Contract;

class TaxService
{
    private const VAT_RATES = [
        'PT' => 0.23, 
        'ES' => 0.21, 
        // .
        // .
        // .
        // Or move to DB and get tax rate based in date
    ];

    private const DEFAULT_VAT = 0.21;

    public function calculateTax(float $netAmount, Contract $contract): float
    {
        $countryCode = $contract->getCountry();
        
        $rate = self::VAT_RATES[$countryCode] ?? self::DEFAULT_VAT;

        return $netAmount * $rate;
    }

    public function getTotalWithTax(float $netAmount, Contract $contract): float
    {
        return $netAmount + $this->calculateTax($netAmount, $contract);
    }
}
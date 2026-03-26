<?php

namespace App\Service\Tariff;

use App\Entity\Contract;

class FixTariffStrategy implements TariffStrategyInterface
{
    public function supports(string $tariffCode): bool 
    {
        return str_starts_with($tariffCode, 'FIX');
    }

    public function calculate(Contract $contract, float $totalKwh, string $month): float 
    {
        $tariff = $contract->getTariff();
        
        $amount = ($totalKwh * $tariff->getPricePerKwh()) + $tariff->getFixedMonthly();

        if ($tariff->getDiscountPercentage() > 0) {
            $amount *= (1 - ($tariff->getDiscountPercentage() / 100));
        }

        return $amount;
    }
}
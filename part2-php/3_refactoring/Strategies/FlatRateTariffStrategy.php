<?php

namespace App\Service\Tariff;

use App\Entity\Contract;

class FlatRateTariffStrategy implements TariffStrategyInterface
{
    public function supports(string $tariffCode): bool 
    {
        return $tariffCode === 'FLAT_RATE';
    }

    public function calculate(Contract $contract, float $totalKwh, string $month): float 
    {
        return $contract->getTariff()->getFixedMonthly();
    }
}
<?php

namespace App\Service\Tariff;

use App\Entity\Contract;

interface TariffStrategyInterface
{
    public function supports(string $tariffCode): bool;
    public function calculate(Contract $contract, float $totalKwh, string $month): float;
}
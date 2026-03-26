<?php

namespace App\Service\Tariff;

// https://symfony.com/doc/current/http_client.html
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IndexedTariffStrategy implements TariffStrategyInterface
{
    public function __construct(private HttpClientInterface $energyApiClient) {}

    public function supports(string $tariffCode): bool {
        return str_contains($tariffCode, 'INDEX');
    }

    public function calculate($contract, float $totalKwh, string $month): float {
        // Using Symfony HttpClient with timeout for safety
        $response = $this->energyApiClient->request('GET', "/spot?month=$month");
        $spotData = $response->toArray();
        
        $amount = ($totalKwh * $spotData['avg_price']) + $contract->getTariff()->getFixedMonthly();
        
        // Apply discount
        return $totalKwh > 500 ? $amount * 0.95 : $amount;
    }
}
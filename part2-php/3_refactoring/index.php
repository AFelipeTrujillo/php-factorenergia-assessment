<?php

use App\Service\Tariff\FlatRateTariffStrategy;
use App\Service\Tariff\IndexedTariffStrategy;
use App\Service\Tariff\FixTariffStrategy;
use App\Service\InvoiceCalculator;
use App\Service\TaxService;

// Load PDO, HttpClient and Logger
$db = new PDO("sqlsrv:Server=localhost;Database=FactorEnergia", "user", "pass");
$logger = new Monolog\Logger('billing');
$httpClient = HttpClient::create([
    'base_uri' => 'https://api.energy-market.eu',
    'timeout'  => 2.0,
    'headers'  => [
        'Authorization' => 'MY_SECRET_TOKEN',
    ],
]);

// Load the repositories
$contractRepo = new ContractRepository($db);
$readingRepo = new MeterReadingRepository($db);
$entityManager = new DoctrineEntityManager($db);

// Load Services
$taxService = new TaxService();

// Set-up all Strategies
$strategies = [
    new FixTariffStrategy(),
    new IndexedTariffStrategy($httpClient),
    new FlatRateTariffStrategy()
];

$calculator = new InvoiceCalculator(
    $contractRepo,
    $readingRepo,
    $entityManager,
    $logger,
    $taxService,
    $strategies
);

try {
    $total = $calculator->calculate(12345, '2026-03');
    echo "Success! Total: $total EUR";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}



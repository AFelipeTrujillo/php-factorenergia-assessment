<?php

namespace App\Service;

use App\Repository\ContractRepository;
use App\Repository\MeterReadingRepository;
use App\Service\Tariff\TariffStrategyInterface;
use App\Service\TaxService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InvoiceCalculator
{
    /** @param iterable<TariffStrategyInterface> $strategies */
    public function __construct(
        private ContractRepository $contractRepository,
        private MeterReadingRepository $readingRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private TaxService $taxService,
        private iterable $strategies
    ) {}

    public function calculate(int $contractId, string $month): float
    {
        // 1. Fetch data using Repository (Safe from SQL Injection)
        $contract = $this->contractRepository->findActiveWithTariff($contractId);
        if (!$contract) {
            $this->logger->error("Contract {id} not found", ['id' => $contractId]);
            throw new \Exception("Active contract not found.");
        }

        $totalKwh = $this->readingRepository->getSumByMonth($contract, $month);

        // 2. Select Strategy (Strategy Pattern)
        $strategy = $this->findStrategy($contract->getTariff()->getCode());
        $netAmount = $strategy->calculate($contract, $totalKwh, $month);

        // 3. Tax Calculation (Can be moved to a TaxService later)
        $taxAmount = $this->taxService->calculateTax($netAmount, $contract);
        $totalAmount = $netAmount * $taxAmount;

        // 4. Persistence using Doctrine (ORM)
        $invoice = new Invoice();
        $invoice->setContract($contract)
                ->setBillingPeriod($month)
                ->setTotalKwh($totalKwh)
                ->setTotalAmount($totalAmount)
                ->setStatus('draft');

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $this->logger->info("Invoice generated for contract {id}", ['id' => $contractId]);

        return $totalAmount;
    }

    private function findStrategy(string $code): TariffStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($code)) return $strategy;
        }
        throw new \Exception("No strategy found for tariff: $code");
    }
}
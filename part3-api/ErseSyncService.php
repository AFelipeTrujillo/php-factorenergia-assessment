<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ErseSyncLog;
use App\Repository\ContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ErseSyncService
{
    public function __construct(
        private ContractRepository $contractRepository,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $erseClient,
        private LoggerInterface $logger
    ) {}

    public function syncContract(int $contractId): void
    {

        $contract = $this->contractRepository->find($contractId);
        if (!$contract) {
            $this->logger->error("Sync failed: Contract {id} not found", ['id' => $contractId]);
            throw new \Exception("Contract not found.");
        }

        $syncLog = new ErseSyncLog($contract);
        $this->entityManager->persist($syncLog);
        $this->entityManager->flush();  // Persist one attempt of sync with pending status by default

        try {

            $payload = $this->transformToErseFormat($contract);

            $response = $this->erseClient->request('POST', '/contracts', [
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();

            
            try {   // What happens if the response is not a valid JSON?

                $content = $response->toArray(false); // send false and it don't throw exception on 4xx/5xx
            
            } catch (DecodingException $e) {

                $this->logger->critical("DecodingException error at ERSE.", $e);
                $content = ['raw_response' => $response->getContent(false)];

            }

            $this->handleResponse($syncLog, $statusCode, $content);

        } catch (\Exception $e) {
            $this->logger->critical("Critical sync error: " . $e->getMessage());
            $syncLog->setStatus(ErseSyncLog::STATUS_FAILED);
            $syncLog->setResponsePayload(['exception' => $e->getMessage()]);
        }

        $this->entityManager->flush();
    }

    private function transformToErseFormat(Contract $contract): array
    {
        return [
            "nif" => $contract->getTaxId(),
            "cups" => $contract->getCups(),
            "supply_address" => [
                "street" => $contract->getStreet(),
                "city" => $contract->getCity(),
                "postal_code" => $contract->getZipCode()
            ],
            "tariff_code" => $contract->getTariff()->getCode(),
            "start_date" => $contract->getStartDate()->format('Y-m-d'),
            "estimated_annual_kwh" => $contract->getEstimatedConsumption()
        ];
    }

    private function handleResponse(ErseSyncLog $log, int $statusCode, array $content): void
    {
        $log->setResponsePayload($content); // Stored the full respose

        switch ($statusCode) {
            case 201:
                $log->setStatus(ErseSyncLog::STATUS_SUCCESS);
                $log->setErseExternalId($content['erse_id'] ?? null);
                break;

            case 409:
                $log->setStatus(ErseSyncLog::STATUS_SUCCESS); // We count it as success if already there
                $log->setErseExternalId($content['existing_id'] ?? null);
                $this->logger->info("Contract already registered at ERSE.");
                break;

            case 400:
            case 500:
            default:
                $log->setStatus(ErseSyncLog::STATUS_FAILED);
                $this->logger->error("ERSE Sync failed with status $statusCode");
                break;
        }
    }
}
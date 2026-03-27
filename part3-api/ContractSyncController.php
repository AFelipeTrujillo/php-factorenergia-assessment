<?php

namespace App\Controller;

use App\Repository\ContractRepository;
use App\Service\ErseSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/contracts')]
class ContractSyncController extends AbstractController
{
    public function __construct(
        private ContractRepository $contractRepository,
        private ErseSyncService $syncService
    ) {}

    #[Route('/sync', name: 'api_contract_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $contractId = $data['contract_id'] ?? null;

        if (!$contractId || !is_int($contractId)) {
            return $this->json(['error' => 'Invalid or missing contract_id'], Response::HTTP_BAD_REQUEST);
        }

        $contract = $this->contractRepository->find($contractId);

        if (!$contract) {
            return $this->json(['error' => 'Contract not found'], Response::HTTP_NOT_FOUND);
        }

        if ($contract->getCountry() !== 'PT') {
            return $this->json([
                'error' => 'Invalid Market',
                'details' => 'Only Portuguese contracts can be synced with ERSE.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            
            $this->syncService->syncContract($contractId);

            return $this->json([
                'status' => 'success',
                'message' => 'Sync process completed for contract ' . $contractId
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Sync failed',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
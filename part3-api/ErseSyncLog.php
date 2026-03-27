<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ErseSyncLogRepository;

#[ORM\Entity(repositoryClass: ErseSyncLogRepository::class)]
#[ORM\Table(name: 'erse_sync_logs')]
#[ORM\Index(columns: ['status'], name: 'idx_sync_status')]
class ErseSyncLog
{

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contract::class)] //So, one contract has many attempts at sybc
    #[ORM\JoinColumn(nullable: false)]
    private ?Contract $contract = null;

    #[ORM\Column(length: 50, nullable: true)]   // Could be null
    private ?string $erseExternalId = null; // The "erse_id" from the 201 response

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'json', nullable: true)] // Define as JSON data type
    private ?array $responsePayload = null; // Full JSON response for debugging

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Contract $contract)
    {
        $this->contract = $contract;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); // Use UTC
        $this->updatedAt = $this->createdAt;
    }
    
    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC')); // Use UTC
    }
}
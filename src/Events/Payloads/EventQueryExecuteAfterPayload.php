<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Events\Payloads;


use Cratia\ORM\DBAL\Interfaces\IQueryDTO;
use Doctrine\Common\EventArgs;
use JsonSerializable;

/**
 * Class EventQueryExecuteAfterPayload
 * @package Cratia\ORM\DBAL\Events\Payloads
 */
class EventQueryExecuteAfterPayload extends EventArgs implements JsonSerializable
{
    /**
     * @var IQueryDTO
     */
    private $dto;

    /**
     * QueryExecuteAfter constructor.
     * @param IQueryDTO $dto
     */
    public function __construct(IQueryDTO $dto)
    {
        $this->dto = $dto;
    }

    /**
     * @return IQueryDTO
     */
    public function getDto(): IQueryDTO
    {
        return $this->dto;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return ['dto' => $this->getDto()];
    }
}
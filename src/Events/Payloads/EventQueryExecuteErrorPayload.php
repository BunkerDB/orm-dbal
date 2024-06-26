<?php
declare(strict_types=1);


namespace Cratia\ORM\DBAL\Events\Payloads;


use Doctrine\Common\EventArgs;
use Exception as DBALException;
use Exception;
use JsonSerializable;

/**
 * Class EventQueryExecuteErrorPayload
 * @package Cratia\ORM\DBAL\Events\Payloads
 */
class EventQueryExecuteErrorPayload extends EventArgs implements JsonSerializable
{
    /**
     * @var Exception|DBALException
     */
    private $exception;

    /**
     * QueryExecuteError constructor.
     * @param Exception $e
     */
    public function __construct(Exception $e)
    {
        $this->exception = $e;
    }

    /**
     * @return DBALException|Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return ['exception' => $this->getException()];
    }
}
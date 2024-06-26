<?php
declare(strict_types=1);


namespace Tests\Cratia\ORM\DBAL;


use Cratia\ORM\DBAL\Adapter\Interfaces\IAdapter;
use Cratia\ORM\DBAL\Adapter\Interfaces\ISqlPerformance;
use Cratia\ORM\DBAL\Interfaces\IQueryDTO;
use Cratia\ORM\DBAL\QueryExecute;
use Cratia\ORM\DQL\Field;
use Cratia\ORM\DQL\Filter;
use Cratia\ORM\DQL\Query;
use Cratia\ORM\DQL\QueryInsert;
use Cratia\ORM\DQL\QueryUpdate;
use Cratia\ORM\DQL\Sql;
use Cratia\ORM\DQL\Table;
use Doctrine\Common\EventManager;
use Exception as DBALException;
use Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\UidProcessor;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class QueryExecuteTest
 * @package Tests\Cratia\ORM\DBAL
 */
class QueryExecuteTest extends PHPUnit_TestCase
{
    /**
     * @throws DBALException
     */
    public function testExecute1()
    {
        $table = new Table($_ENV['TABLE_TEST'], "t");
        $query = new Query($table);
        $sql = new Sql();
        $sql->sentence = "SELECT SQL_CALC_FOUND_ROWS t.* FROM {$_ENV['TABLE_TEST']} AS t LIMIT 20 OFFSET 0";
        $sql->params = [];
        $this->assertEquals($sql, $query->toSQL());

        $dto = (new QueryExecute(new Adapter()))->executeQuery($query);;
        $this->assertEquals(20, $dto->getCount());
        $this->assertEquals(20, count($dto->getRows()));
        $this->assertIsArray($dto->getRows());
        $this->assertEquals($sql, $dto->getSql());
        $this->assertFalse($dto->isEmpty());
        $this->assertNotNull($dto->getPerformance());
    }

    /**
     * @throws DBALException
     */
    public function testExecute2()
    {
        $table1 = new Table($_ENV['TABLE_TEST'], "t");
        $field10 = Field::column($table1, "id");
        $field11 = Field::callback(
            function (array $rawRow) {
                $newRow = $rawRow;
                $newRow['connection_id'] = $rawRow['id'] . '- CONNECTION';
                return $newRow;
            },
            'connection_id');

        $query = new Query($table1);
        $query
            ->addField($field10)
            ->addField($field11)
            ->setLimit(1);

        $dto = (new QueryExecute(new Adapter()))->executeQuery($query);;
        $this->assertEquals(1, $dto->getCount());
        $this->assertEquals(1, count($dto->getRows()));
        $this->assertIsArray($dto->getRows());
        $this->assertNotNull($dto->getPerformance());
    }

    /**
     * @throws DBALException
     */
    public function testExecute3()
    {
        $this->expectException(DBALException::class);

        $table1 = new Table($_ENV['TABLE_TEST'], "t");
        $field10 = Field::column($table1, "_id"); //FIELD NO EXIST IN THE TABLE
        $field11 = Field::callback(
            function (array $rawRow) {
                $newRow = $rawRow;
                $newRow['connection_id'] = $rawRow['id'] . '- CONNECTION';
                return $newRow;
            },
            'connection_id');

        $query = new Query($table1);
        $query
            ->addField($field10)
            ->addField($field11)
            ->setLimit(1);

        (new QueryExecute(new Adapter()))->executeQuery($query);
    }


    public function testExecute4()
    {
        $error_msg = "Error in the " . __METHOD__ . "() -> Error expected.";
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($error_msg);

        $table1 = new Table($_ENV['TABLE_TEST'], "t");
        $field10 = Field::column($table1, "id"); //FIELD NO EXIST IN THE TABLE
        $field11 = Field::callback(
            function () use ($error_msg) {
                throw new Exception($error_msg);
            },
            'connection_id');

        $query = new Query($table1);
        $query
            ->addField($field10)
            ->addField($field11)
            ->setLimit(1);

        (new QueryExecute(new Adapter()))->executeQuery($query);
    }

    /**
     * @throws DBALException
     */
    public function testExecute5()
    {
        $table = new Table($_ENV['TABLE_TEST']);
        $query = new QueryInsert($table);

        $query
            ->addField(Field::column($table,'status'), 'inactive')
            ->addField(Field::column($table,'id_connection'), 1)
            ->addField(Field::column($table,'network_service'), 'TEST')
            ->addField(Field::column($table,'network_params'), 'TEST')
            ->addField(Field::column($table,'created'), '2020-02-20 18:53:16')
            ->addField(Field::column($table,'updated'), null)
            ->addField(Field::column($table,'disabled'), false)
            ->addField(Field::column($table,'validity_period_to'), null)
            ->addField(Field::column($table,'validity_period_from'), null)
            ->addField(Field::column($table,'error_exception'), 'TEST');


        $sql = new Sql();
        $sql->sentence = "INSERT INTO `{$_ENV['TABLE_TEST']}` (`status`,`id_connection`,`network_service`,`network_params`,`created`,`updated`,`disabled`,`validity_period_to`,`validity_period_from`,`error_exception`) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $sql->params = ['inactive', 1, 'TEST', 'TEST', '2020-02-20 18:53:16', null, 0, null, null, 'TEST'];

        $this->assertEqualsCanonicalizing($sql->getSentence(), $query->toSql()->getSentence());
        $this->assertEqualsCanonicalizing($sql->getParams(), $query->toSql()->getParams());

        $dto = (new QueryExecute(new Adapter()))->executeNonQuery(IAdapter::CREATE, $query);

        $this->assertInstanceOf(IQueryDTO::class, $dto);
        $this->assertIsString($dto->getResult());
        $this->assertEqualsCanonicalizing(0, $dto->getCount());
        $this->assertIsString($dto->getResult());
        $this->assertInstanceOf( ISqlPerformance::class, $dto->getPerformance());
        $this->assertNotNull($dto->getPerformance());
        $this->assertEqualsCanonicalizing([], $dto->getRows());
        $this->assertEqualsCanonicalizing($query->toSql(), $dto->getSql());
    }

    /**
     * @throws DBALException
     */
    public function testExecute6()
    {
        $this->expectException(DBALException::class);

        $table = new Table('');
        $query = new QueryInsert($table);

        $query
            ->addField(Field::column($table,'status'), 'inactive')
            ->addField(Field::column($table,'id_connection'), 1)
            ->addField(Field::column($table,'network_service'), 'TEST')
            ->addField(Field::column($table,'network_params'), 'TEST')
            ->addField(Field::column($table,'created'), '2020-02-20 18:53:16')
            ->addField(Field::column($table,'updated'), null)
            ->addField(Field::column($table,'disabled'), false)
            ->addField(Field::column($table,'validity_period_to'), null)
            ->addField(Field::column($table,'validity_period_from'), null)
            ->addField(Field::column($table,'error_exception'), 'TEST');

        $sql = new Sql();
        $sql->sentence = "INSERT INTO `` (`status`,`id_connection`,`network_service`,`network_params`,`created`,`updated`,`disabled`,`validity_period_to`,`validity_period_from`,`error_exception`) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $sql->params = ['inactive', 1, 'TEST', 'TEST', '2020-02-20 18:53:16', null, 0, null, null, 'TEST'];

        $this->assertEqualsCanonicalizing($sql->getSentence(), $query->toSql()->getSentence());
        $this->assertEqualsCanonicalizing($sql->getParams(), $query->toSql()->getParams());


        (new QueryExecute(new Adapter()))->executeNonQuery(IAdapter::CREATE, $query);
    }

    public function testExecute7()
    {
        $table = new Table($_ENV['TABLE_TEST'], 't');
        $query = new QueryUpdate($table);

        $query
            ->addField(Field::column($table,'status'), 'inactive')
            ->addField(Field::column($table,'id_connection'), 1)
            ->addFilter(Filter::eq(Field::column($table,'id'), 1));

        $sql = new Sql();
        $sql->sentence = "UPDATE {$_ENV['TABLE_TEST']} AS t SET t.status = ?,t.id_connection = ? WHERE (t.id = ?)";
        $sql->params = ['inactive', 1, 1];

        $this->assertEqualsCanonicalizing($sql->getSentence(), $query->toSql()->getSentence());
        $this->assertEqualsCanonicalizing($sql->getParams(), $query->toSql()->getParams());

        $dto = (new QueryExecute(new Adapter()))->executeNonQuery(IAdapter::UPDATE, $query);

        $this->assertInstanceOf(IQueryDTO::class, $dto);
        $this->assertIsInt($dto->getResult());
        $this->assertEqualsCanonicalizing(0, $dto->getCount());
        $this->assertEqualsCanonicalizing(0, $dto->getResult());
        $this->assertInstanceOf( ISqlPerformance::class, $dto->getPerformance());
        $this->assertNotNull($dto->getPerformance());
        $this->assertEqualsCanonicalizing([], $dto->getRows());
        $this->assertEqualsCanonicalizing($query->toSql(), $dto->getSql());
    }

    /**
     * @throws DBALException
     */
    public function testExecute9()
    {
        $table = new Table($_ENV['TABLE_TEST']);
        $query = new QueryInsert($table);

        $query
            ->addField(Field::column($table,'status'), 'inactive')
            ->addField(Field::column($table,'id_connection'), 1)
            ->addField(Field::column($table,'network_service'), 'TEST')
            ->addField(Field::column($table,'network_params'), 'TEST')
            ->addField(Field::column($table,'created'), '2020-02-20 18:53:16')
            ->addField(Field::column($table,'updated'), null)
            ->addField(Field::column($table,'disabled'), false)
            ->addField(Field::column($table,'validity_period_to'), null)
            ->addField(Field::column($table,'validity_period_from'), null)
            ->addField(Field::column($table,'error_exception'), 'TEST');


        $sql = new Sql();
        $sql->sentence = "INSERT INTO `{$_ENV['TABLE_TEST']}` (`status`,`id_connection`,`network_service`,`network_params`,`created`,`updated`,`disabled`,`validity_period_to`,`validity_period_from`,`error_exception`) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $sql->params = ['inactive', 1, 'TEST', 'TEST', '2020-02-20 18:53:16', null, 0, null, null, 'TEST'];

        $this->assertEqualsCanonicalizing($sql->getSentence(), $query->toSql()->getSentence());
        $this->assertEqualsCanonicalizing($sql->getParams(), $query->toSql()->getParams());

        $adapter = new Adapter();

        $logger = new Logger('orm-dbal');
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());
        $handler = new StreamHandler('php://stdout', Logger::DEBUG);
        $logger->pushHandler($handler);

        $adapter->setLogger($logger);
        $this->assertInstanceOf(LoggerInterface::class, $adapter->getLogger());

        $executer = new QueryExecute($adapter);

        $this->assertNull($executer->getLogger());

        $executer->setLogger($logger);

        $this->assertNotNull($executer->getLogger());
        $this->assertInstanceOf(LoggerInterface::class, $executer->getLogger());

        $dto = $executer->executeNonQuery(IAdapter::CREATE, $query);

        $this->assertInstanceOf(IQueryDTO::class, $dto);
        $this->assertIsString($dto->getResult());
        $this->assertEqualsCanonicalizing(0, $dto->getCount());
        $this->assertInstanceOf( ISqlPerformance::class, $dto->getPerformance());
        $this->assertNotNull($dto->getPerformance());
        $this->assertEqualsCanonicalizing([], $dto->getRows());
        $this->assertEqualsCanonicalizing($sql, $dto->getSql());
        $this->assertInstanceOf(LoggerInterface::class, $executer->getLogger());
        $this->assertInstanceOf(IAdapter::class, $executer->getAdapter());
    }

    /**
     * @throws DBALException
     */
    public function testExecute10()
    {
        $table = new Table($_ENV['TABLE_TEST']);
        $query = new QueryInsert($table);

        $query
            ->addField(Field::column($table,'status'), 'inactive')
            ->addField(Field::column($table,'id_connection'), 1)
            ->addField(Field::column($table,'network_service'), 'TEST')
            ->addField(Field::column($table,'network_params'), 'TEST')
            ->addField(Field::column($table,'created'), '2020-02-20 18:53:16')
            ->addField(Field::column($table,'updated'), null)
            ->addField(Field::column($table,'disabled'), false)
            ->addField(Field::column($table,'validity_period_to'), null)
            ->addField(Field::column($table,'validity_period_from'), null)
            ->addField(Field::column($table,'error_exception'), 'TEST');


        $sql = new Sql();
        $sql->sentence = "INSERT INTO `{$_ENV['TABLE_TEST']}` (`status`,`id_connection`,`network_service`,`network_params`,`created`,`updated`,`disabled`,`validity_period_to`,`validity_period_from`,`error_exception`) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $sql->params = ['inactive', 1, 'TEST', 'TEST', '2020-02-20 18:53:16', null, 0, null, null, 'TEST'];

        $this->assertEqualsCanonicalizing($sql->getSentence(), $query->toSql()->getSentence());
        $this->assertEqualsCanonicalizing($sql->getParams(), $query->toSql()->getParams());

        $adapter = new Adapter();

        $logger = new Logger('orm-dbal');
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());
        $handler = new StreamHandler('php://stdout', Logger::DEBUG);
        $logger->pushHandler($handler);

        $eventManager = new EventManager();
        $subscriber = new EventSubscriberAdapter();
        $eventManager->addEventSubscriber($subscriber);

        $adapter->setLogger($logger);
        $adapter->setEventManager($eventManager);

        $this->assertInstanceOf(LoggerInterface::class, $adapter->getLogger());
        $this->assertInstanceOf(EventManager::class, $adapter->getEventManager());

        $executer = new QueryExecute($adapter);

        $this->assertNull($executer->getLogger());

        $executer->setLogger($logger);

        $this->assertNotNull($executer->getLogger());
        $this->assertInstanceOf(LoggerInterface::class, $executer->getLogger());

        $dto = $executer->executeNonQuery(IAdapter::CREATE, $query);

        $this->assertInstanceOf(IQueryDTO::class, $dto);
        $this->assertIsString($dto->getResult());
        $this->assertEqualsCanonicalizing(0, $dto->getCount());
        $this->assertInstanceOf( ISqlPerformance::class, $dto->getPerformance());
        $this->assertNotNull($dto->getPerformance());
        $this->assertEqualsCanonicalizing([], $dto->getRows());
        $this->assertEqualsCanonicalizing($sql, $dto->getSql());
        $this->assertInstanceOf(LoggerInterface::class, $executer->getLogger());
        $this->assertInstanceOf(IAdapter::class, $executer->getAdapter());

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onAfterQuery);
        $this->assertFalse($subscriber->onBeforeQuery);
        $this->assertTrue($subscriber->onAfterNonQuery);
        $this->assertTrue($subscriber->onBeforeNonQuery);
    }

    /**
     * @throws DBALException
     */
    public function testExecute11()
    {
        $table = new Table($_ENV['TABLE_TEST'], "t");
        $query = new Query($table);
        $sql = new Sql();
        $sql->sentence = "SELECT SQL_CALC_FOUND_ROWS t.* FROM {$_ENV['TABLE_TEST']} AS t LIMIT 20 OFFSET 0";
        $sql->params = [];
        $this->assertEquals($sql, $query->toSQL());

        $eventManager = new EventManager();
        $subscriber = new EventSubscriberAdapter();

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onAfterQuery);
        $this->assertFalse($subscriber->onBeforeQuery);
        $this->assertFalse($subscriber->onAfterNonQuery);
        $this->assertFalse($subscriber->onBeforeNonQuery);

        $eventManager->addEventSubscriber($subscriber);

        $adapter = new Adapter();
        $adapter->setEventManager($eventManager);

        $dto = (new QueryExecute($adapter))->executeQuery($query);;
        $this->assertEquals(20, $dto->getCount());
        $this->assertEquals(20, count($dto->getRows()));
        $this->assertIsArray($dto->getRows());
        $this->assertEquals($sql, $dto->getSql());
        $this->assertFalse($dto->isEmpty());
        $this->assertNotNull($dto->getPerformance());

        $this->assertFalse($subscriber->onError);
        $this->assertTrue($subscriber->onAfterQuery);
        $this->assertTrue($subscriber->onBeforeQuery);
        $this->assertFalse($subscriber->onAfterNonQuery);
        $this->assertFalse($subscriber->onBeforeNonQuery);
    }

    /**
     * @throws DBALException
     */
    public function testExecute12()
    {

        $table1 = new Table($_ENV['TABLE_TEST'], "t");
        $field10 = Field::column($table1, "_id"); //FIELD NO EXIST IN THE TABLE
        $field11 = Field::callback(
            function (array $rawRow) {
                $newRow = $rawRow;
                $newRow['connection_id'] = $rawRow['id'] . '- CONNECTION';
                return $newRow;
            },
            'connection_id');

        $query = new Query($table1);
        $query
            ->addField($field10)
            ->addField($field11)
            ->setLimit(1);

        $eventManager = new EventManager();
        $subscriber = new EventSubscriberAdapter();

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onAfterQuery);
        $this->assertFalse($subscriber->onBeforeQuery);
        $this->assertFalse($subscriber->onAfterNonQuery);
        $this->assertFalse($subscriber->onBeforeNonQuery);

        $eventManager->addEventSubscriber($subscriber);

        $adapter = new Adapter();
        $adapter->setEventManager($eventManager);

        try {
            (new QueryExecute($adapter))->executeQuery($query);
        } catch (Exception $e) {

        }

        $this->assertTrue($subscriber->onError);
        $this->assertFalse($subscriber->onAfterQuery);
        $this->assertTrue($subscriber->onBeforeQuery);
        $this->assertFalse($subscriber->onAfterNonQuery);
        $this->assertFalse($subscriber->onBeforeNonQuery);

    }

    /**
     * @throws DBALException
     */
    public function testExecute13()
    {
        $table = new Table($_ENV['TABLE_TEST'], "t");
        $query = new Query($table);
        $sql = new Sql();
        $sql->sentence = "SELECT SQL_CALC_FOUND_ROWS t.* FROM {$_ENV['TABLE_TEST']} AS t LIMIT 20 OFFSET 0";
        $sql->params = [];
        $this->assertEquals($sql, $query->toSQL());

        $eventManager = new EventManager();
        $subscriber = new EventSubscriberQueryExecute();

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onBeforeExecuteQuery);
        $this->assertFalse($subscriber->onAfterExecuteQuery);
        $this->assertFalse($subscriber->onBeforeExecuteNonQuery);
        $this->assertFalse($subscriber->onAfterExecuteNonQuery);

        $eventManager->addEventSubscriber($subscriber);

        $dto = (new QueryExecute(new Adapter(), null, $eventManager))->executeQuery($query);

        $this->assertEquals(20, $dto->getCount());
        $this->assertEquals(20, count($dto->getRows()));
        $this->assertIsArray($dto->getRows());
        $this->assertEquals($sql, $dto->getSql());
        $this->assertFalse($dto->isEmpty());
        $this->assertNotNull($dto->getPerformance());

        $this->assertFalse($subscriber->onError);
        $this->assertTrue($subscriber->onBeforeExecuteQuery);
        $this->assertTrue($subscriber->onAfterExecuteQuery);
        $this->assertFalse($subscriber->onBeforeExecuteNonQuery);
        $this->assertFalse($subscriber->onAfterExecuteNonQuery);

    }

    /**
     * @throws DBALException
     */
    public function testExecute14()
    {

        $table1 = new Table($_ENV['TABLE_TEST'], "t");
        $field10 = Field::column($table1, "_id"); //FIELD NO EXIST IN THE TABLE
        $field11 = Field::callback(
            function (array $rawRow) {
                $newRow = $rawRow;
                $newRow['connection_id'] = $rawRow['id'] . '- CONNECTION';
                return $newRow;
            },
            'connection_id');

        $query = new Query($table1);
        $query
            ->addField($field10)
            ->addField($field11)
            ->setLimit(1);

        $eventManager = new EventManager();
        $subscriber = new EventSubscriberQueryExecute();

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onBeforeExecuteQuery);
        $this->assertFalse($subscriber->onAfterExecuteQuery);
        $this->assertFalse($subscriber->onBeforeExecuteNonQuery);
        $this->assertFalse($subscriber->onAfterExecuteNonQuery);

        $eventManager->addEventSubscriber($subscriber);

        $adapter = new Adapter();

        try {
            (new QueryExecute($adapter, null, null))
                ->setEventManager($eventManager)
                ->executeQuery($query);
        } catch (Exception $e) {

        }

        $this->assertTrue($subscriber->onError);
        $this->assertTrue($subscriber->onBeforeExecuteQuery);
        $this->assertFalse($subscriber->onAfterExecuteQuery);
        $this->assertFalse($subscriber->onBeforeExecuteNonQuery);
        $this->assertFalse($subscriber->onAfterExecuteNonQuery);

    }

    /**
     * @throws DBALException
     */
    public function testExecute15()
    {
        $table = new Table($_ENV['TABLE_TEST']);
        $query = new QueryInsert($table);

        $query
            ->addField(Field::column($table,'status'), 'inactive')
            ->addField(Field::column($table,'id_connection'), 1)
            ->addField(Field::column($table,'network_service'), 'TEST')
            ->addField(Field::column($table,'network_params'), 'TEST')
            ->addField(Field::column($table,'created'), '2020-02-20 18:53:16')
            ->addField(Field::column($table,'updated'), null)
            ->addField(Field::column($table,'disabled'), false)
            ->addField(Field::column($table,'validity_period_to'), null)
            ->addField(Field::column($table,'validity_period_from'), null)
            ->addField(Field::column($table,'error_exception'), 'TEST');


        $sql = new Sql();
        $sql->sentence = "INSERT INTO `{$_ENV['TABLE_TEST']}` (`status`,`id_connection`,`network_service`,`network_params`,`created`,`updated`,`disabled`,`validity_period_to`,`validity_period_from`,`error_exception`) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $sql->params = ['inactive', 1, 'TEST', 'TEST', '2020-02-20 18:53:16', null, 0, null, null, 'TEST'];

        $this->assertEqualsCanonicalizing($sql->getSentence(), $query->toSql()->getSentence());
        $this->assertEqualsCanonicalizing($sql->getParams(), $query->toSql()->getParams());

        $adapter = new Adapter();

        $eventManager = new EventManager();
        $subscriber = new EventSubscriberQueryExecute();

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onBeforeExecuteQuery);
        $this->assertFalse($subscriber->onAfterExecuteQuery);
        $this->assertFalse($subscriber->onBeforeExecuteNonQuery);
        $this->assertFalse($subscriber->onAfterExecuteNonQuery);

        $eventManager->addEventSubscriber($subscriber);

        $executer = new QueryExecute($adapter);

        $this->assertNull($executer->getLogger());
        $this->assertNull($executer->getEventManager());

        $executer->setEventManager($eventManager);

        $this->assertNotNull($executer->getEventManager());
        $this->assertInstanceOf(EventManager::class, $executer->getEventManager());

        $dto = $executer->executeNonQuery(IAdapter::CREATE, $query);

        $this->assertInstanceOf(IQueryDTO::class, $dto);
        $this->assertIsString($dto->getResult());
        $this->assertEqualsCanonicalizing(0, $dto->getCount());
        $this->assertInstanceOf( ISqlPerformance::class, $dto->getPerformance());
        $this->assertNotNull($dto->getPerformance());
        $this->assertEqualsCanonicalizing([], $dto->getRows());
        $this->assertEqualsCanonicalizing($sql, $dto->getSql());
        $this->assertInstanceOf(IAdapter::class, $executer->getAdapter());
        $this->assertInstanceOf(EventManager::class, $executer->getEventManager());

        $this->assertFalse($subscriber->onError);
        $this->assertFalse($subscriber->onBeforeExecuteQuery);
        $this->assertFalse($subscriber->onAfterExecuteQuery);
        $this->assertTrue($subscriber->onBeforeExecuteNonQuery);
        $this->assertTrue($subscriber->onAfterExecuteNonQuery);
    }
}
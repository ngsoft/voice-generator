<?php
/** @noinspection ALL */
namespace Sql{


const FETCH_ASSOC = 2;
const FETCH_NUM = 3;
const FETCH_BOTH = 4;
const FETCH_OBJ = 5;


interface Driver
{

    /**
     * Returns the driver type (mysql,sqlite,...)
     * @return string
     */
    public function type();

    /** @return object|resource|null */
    public function link();

    /**
     * @param string $string
     * @return string
     */
    public function quote($string);

    /** @return bool */
    public function beginTransaction();

    /** @return bool */
    public function rollBack();

    /** @return bool */
    public function commit();

    /**
     * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
     * @return bool
     */
    public function connect(array $params);


    /** @return bool */
    public function close();

    /** @return array{int, string} */
    public function error();


    /**
     * @param string $query
     * @return Result|false
     */
    public function query($query);

    /**
     * @param string $query
     * @return bool
     */
    public function exec($query);


    /** @return int|string */
    public function lastInsertId();

    /**
     * @param string $query
     * @return Statement|false
     */
    public function prepare($query);


    /**
     * @param Statement $statement
     * @param array $params
     * @return Statement|false
     */
    public function bindParams($statement, array $params);


    /**
     * @param Statement $statement
     * @return Result|false
     */
    public function execute($statement);

    /**
     * @param Result $result
     * @param int{2,3,4,5} $mode
     * @return array|object|null
     */
    public function fetch($result, $mode = FETCH_BOTH);


}

}
namespace Sql{


class SqlException extends \Exception
{


    /**
     * @param string $message
     * @param ...$replacements
     * @return static
     */

    public static function newInstance($message = "", $replacements = [])
    {

        if (!is_array($replacements)) {
            $replacements = array_slice(func_get_args(), 1);
            if (count($replacements)) {
                $message = vsprintf($message, $replacements);
            }
        }

        return new static($message);

    }


    public static function cannotConnect($prev = null)
    {
        return new self(
            'Cannot connect to database', 0, $prev
        );
    }

    public static function cannotPrepare($prev = null)
    {

        return new self(
            'Cannot prepare SQL statement, invalid query', 0, $prev
        );

    }


    public static function cannotBind($prev = null)
    {

        return new self(
            'Cannot bind params, invalid number of parameters', 0, $prev
        );

    }


    public static function cannotExecute($prev = null)
    {

        return new self(
            'Cannot execute query', 0, $prev
        );

    }


    public static function cannotFetch($prev = null)
    {
        return new self(
            'Cannot fetch row', 0, $prev
        );
    }


    public static function cannotStartTransaction($prev = null)
    {
        return new self(
            'Cannot start transaction', 0, $prev
        );
    }

    public static function cannotEndTransaction($prev = null)
    {
        return new self(
            'Cannot end transaction', 0, $prev
        );
    }


}

}
namespace Sql{



interface Maker
{


    /**
     * @param array<string|int, mixed> $data
     * @param ?static $instance
     * @return static
     */
    public static function make(array $data, $instance = null);

}

}
namespace Sql{




/**
 * Preload class for constants
 */
class_exists(Driver::class);

class Statement implements \IteratorAggregate
{

    /** @var Driver */
    protected $driver;

    /** @var \mysqli_stmt|\PDOStatement|\SQLite3Stmt|object|resource|string */
    protected $statement;
    protected $sql = "";

    /**
     * @var Result|false
     */
    protected $result = false;

    /**
     * @param Driver $driver
     * @param object|string|resource $statement
     * @param string $sql
     */
    public function __construct(Driver $driver, $statement, $sql = "")
    {
        if (!is_object($statement) && !is_string($statement) && !is_resource($statement)) {
            throw new \InvalidArgumentException(sprintf('$statement argument must be a string, resource or object, %s given', get_debug_type($statement)));
        }

        if (!is_string($sql)) {
            throw new \InvalidArgumentException(sprintf('$sql argument must be a string, %s given', get_debug_type($sql)));
        }

        $this->driver = $driver;
        $this->statement = $statement;
        $this->sql = $sql;
    }


    public function __debugInfo()
    {
        return [
            "driver" => get_debug_type($this->driver),
            "statement" => get_debug_type($this->statement),
            "result" => get_debug_type($this->result),
        ];
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }


    /** @return Driver */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return \mysqli_stmt|\PDOStatement|\SQLite3Stmt|object|resource|string
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @return Result|false
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param array $params
     * @return static
     */
    public function bindParams(array $params)
    {
        return $this->driver->bindParams($this, $params) ?: $this;
    }


    /**
     * @param array $bindings
     * @return static|null
     */
    public function execute(array $bindings = [])
    {
        // clears previous result set
        $this->result = false;
        // add mysqli/pdo php 8.2 execute shortcut for php5+
        if (count($bindings)) {
            if (!$this->driver->bindParams($this, $bindings)) {
                return null;
            }
        }

        if ($this->result = $this->driver->execute($this)) {
            return $this;
        }
        return null;
    }

    /**
     * Iterates all the results from the set
     * @param int{2,3,4,5} $mode
     * @return \Traversable
     */
    public function fetch($mode = FETCH_BOTH)
    {
        if (!$this->result) {
            return new \EmptyIterator();
        }
        return $this->result->fetch($mode);
    }

    /**
     * Returns one row from the set
     * @param int{2,3,4,5} $mode
     * @return array|null|object
     */
    public function fetchOne($mode = FETCH_BOTH)
    {

        if (!$this->result) {
            return null;
        }
        return $this->result->fetchOne($mode);
    }

    /**
     * Returns all the results at once
     * @param int{2,3,4,5} $mode
     * @return array[]|object[]
     */
    public function fetchAll($mode = FETCH_BOTH)
    {
        if (!$this->result) {
            return [];
        }
        return $this->result->fetchAll($mode);
    }

    /**
     * Returns one column from the next result row
     * @param int $columnIndex
     * @return mixed
     */
    public function fetchCol($columnIndex = 0)
    {
        if (!$this->result) {
            return null;
        }
        return $this->result->fetchCol($columnIndex);
    }


    /**
     * @template T of Maker
     * @psalm-param class-string<T>|object<T> $className
     * @return null|T
     */
    public function make($className)
    {
        if ($this->result) {
            return $this->result->make($className);
        }
        return null;
    }


    /**
     * @template T of Maker
     * @psalm-param class-string<T>|object<T> $className
     * @return T[]
     */
    public function makeMany($className)
    {
        if ($this->result) {
            return $this->result->makeMany($className);
        }
        return [];
    }


    public function getIterator()
    {
        return $this->fetch(FETCH_ASSOC);
    }
}

}
namespace Sql{




/**
 * Preload class for constants
 */
class_exists(Driver::class);

class Result implements \IteratorAggregate
{

    /** @var Driver */
    protected $driver;

    /** @var \SQLite3Result|\mysqli_result|\PDOStatement|resource|array|object|null|bool */
    protected $result;

    public function __construct(Driver $driver, $result)
    {
        $this->driver = $driver;
        $this->result = $result;
    }


    public function __debugInfo()
    {
        return [
            "driver" => get_debug_type($this->driver),
            "result" => get_debug_type($this->result),
        ];
    }

    /** @return Driver */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return array|\mysqli_result|object|\PDOStatement|resource|\SQLite3Result|null|bool
     */
    public function getResult()
    {
        return $this->result;
    }


    /**
     * Iterates all the results from the set
     * @param int{2,3,4,5} $mode
     * @return \Traversable
     */
    public function fetch($mode = FETCH_BOTH)
    {
        while ($row = $this->fetchOne($mode)) {
            yield $row;
        }
    }

    /**
     * Returns all the results at once
     * @param int{2,3,4,5} $mode
     * @return array
     */
    public function fetchAll($mode = FETCH_BOTH)
    {
        return iterator_to_array($this->fetch($mode));
    }

    /**
     * Returns one row from the set
     * @param int{2,3,4,5} $mode
     * @return array|null|object
     */
    public function fetchOne($mode = FETCH_BOTH)
    {
        $result = $this->driver->fetch($this, $mode);
        return $result === false ? null : $result;
    }

    /**
     * Returns one column from the next result row
     * @param int $columnIndex
     * @return mixed
     */
    public function fetchCol($columnIndex = 0)
    {
        if (($result = $this->fetchOne(FETCH_NUM)) && isset($result[$columnIndex])) {
            return $result[$columnIndex];
        }
        return null;
    }

    /**
     * @template T of Maker
     * @psalm-param class-string<T>|object<T> $className
     * @return null|T
     */
    public function make($className)
    {

        if (is_subclass_of($className, Maker::class, is_string($className))) {
            $obj = is_object($className) ? $className : null;
            $name = is_object($className) ? get_class($className) : $className;
            if ($data = $this->fetchOne()) {
                return $name::make($data, $obj);
            }
        }

        return null;
    }


    /**
     * @template T of Maker
     * @psalm-param class-string<T>|object<T> $className
     * @return T[]
     */
    public function makeMany($className)
    {
        $result = [];
        if (is_subclass_of($className, Maker::class, is_string($className))) {
            $name = is_object($className) ? get_class($className) : $className;
            foreach ($this->fetch() as $data) {
                $result[] = $name::make($data);
            }
        }
        return $result;
    }


    public function getIterator()
    {
        return $this->fetch(FETCH_ASSOC);
    }
}

}
namespace Sql{




class_exists("EventListener");

class QueryHelper
{


    /** @var Driver */
    protected $driver;

    /** @var \Observable\EventDispatcher|null */
    protected $dispatcher = null;


    /** @var Builder\QueryBuilder */
    protected $builder = null;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
        if (class_exists(\Observable\EventDispatcher::class)) {
            $this->dispatcher = new \Observable\EventDispatcher();
        }
    }

    /**
     * @return Builder\QueryBuilder
     */
    public function getBuilder()
    {
        if (!$this->builder) {
            $this->builder = new Builder\QueryBuilder();
            $this->builder->setQueryHelper($this);
        }

        return $this->builder;
    }

    /**
     * @param string $type
     * @param mixed $data
     * @return \Observable\Event|null
     */
    public function dispatchEvent($type, $data = null)
    {
        if ($this->dispatcher) {
            return $this->dispatcher->dispatchEvent(
                new \Observable\Event($type, $data)
            );
        }
        return null;
    }

    /**
     * @param string $type
     * @param callable $listener
     * @param int $priority
     * @return $this
     */
    public function addEventListener($type, callable $listener, $priority = 100)
    {
        if ($this->dispatcher) {
            $this->dispatcher->addEventListener($type, $listener, $priority);
        }
        return $this;
    }


    /**
     * Begins a SELECT statement
     * @param string ...$fields
     * @return Builder\QueryBuilder
     */
    public function select($fields = "*")
    {
        return $this->getBuilder()->select(!is_array($fields) ? func_get_args() : $fields);
    }

    /**
     * Begins an UPDATE statement
     * @param string $table
     * @param ?string $alias
     * @return Builder\QueryBuilder
     */
    public function update($table, $alias = null)
    {
        return $this->getBuilder()->update($table, $alias);
    }

    /**
     * Begins an UPDATE statement
     * @param string $table
     * @return Builder\QueryBuilder
     */
    public function insert($table)
    {
        return $this->getBuilder()->insert($table);
    }


    /**
     * Begins a DELETE statement
     * @param string $table
     * @param ?string $alias
     * @return Builder\QueryBuilder
     */
    public function delete($table, $alias = null)
    {
        return $this->getBuilder()->delete($table, $alias);
    }


    /**
     * Returns the driver type (mysql,sqlite,...)
     * @return string
     */
    public function type()
    {
        return $this->driver->type();
    }

    /**
     * @param string $string
     * @return string
     */
    public function quote($string)
    {
        return $this->driver->quote($string);
    }

    /** @return bool */
    public function beginTransaction()
    {
        return $this->driver->beginTransaction();
    }

    /** @return bool */
    public function rollBack()
    {
        return $this->driver->rollBack();
    }

    /** @return bool */
    public function commit()
    {
        return $this->driver->commit();
    }


    /**
     * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
     * @return bool
     */
    public function connect(array $params)
    {
        return $this->driver->connect($params);
    }


    /** @return bool */
    public function close()
    {
        return $this->driver->close();
    }


    /** @return array{int, string} */
    public function error()
    {
        return $this->driver->error();
    }


    /**
     * @param string $query
     * @return Result|false
     */
    public function query($query)
    {
        return $this->driver->query($query);
    }


    /**
     * @param string $query
     * @return bool
     */
    public function exec($query)
    {
        return $this->driver->exec($query);
    }

    /** @return int|string */
    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    /**
     * @param string $query
     * @return Statement|false
     */
    public function prepare($query)
    {
        return $this->driver->prepare($query);
    }

    /**
     * @param Statement $statement
     * @param array $params
     * @return Statement|false
     */
    public function bindParams($statement, array $params)
    {
        return $this->driver->bindParams($statement, $params);
    }

    /**
     * @param Statement $statement
     * @return Result|null
     */
    public function execute($statement)
    {
        return $this->driver->execute($statement) ?: null;
    }

    /**
     * @param Result $result
     * @param int{2,3,4,5} $mode
     * @return array|object|null
     */
    public function fetch($result, $mode = FETCH_BOTH)
    {
        return $this->driver->fetch($result, $mode);
    }

    /**
     * @return Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param Driver $driver
     * @return static
     */
    public function setDriver(Driver $driver)
    {
        $this->driver = $driver;
        return $this;
    }
}

}
namespace Sql\Builder{



class Expression implements \Countable, \Stringable
{
    const TYPE_AND = 'AND';
    const TYPE_OR = 'OR';

    /** @var string */
    private $type;
    /** @var static[] */
    private $parts = [];

    /**
     * @param string $type
     * @param array $parts
     */
    public function __construct($type, array $parts = [])
    {
        if (!in_array($type, [self::TYPE_AND, self::TYPE_OR])) {
            throw new \InvalidArgumentException("Invalid type $type");
        }
        $this->type = $type;
        $this->addMany($parts);

    }


    public function __clone()
    {
        foreach ($this->parts as &$part) {
            if($part instanceof self) {
                $part = clone $part;
            }
            
        }
    }

    /**
     * @param static|non-empty-string $part
     * @return static
     */
    public function add($part)
    {
        if ((!empty($part) && is_string($part)) || ($part instanceof self && $part->count() > 0)) {
            $this->parts[] = $part;
        }
        return $this;
    }


    public function addMany(array $parts = [])
    {
        foreach ($parts as $part) {
            $this->add($part);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getParts()
    {
        return $this->parts;
    }


    public function count()
    {
        return count($this->parts);
    }

    public function __toString()
    {
        if ($this->count() === 1) {
            return sprintf("%s", $this->parts[0]);
        }

        return sprintf("(%s)", implode(
            sprintf(") %s (", $this->type),
            $this->parts
        ));
    }
}

}
namespace Sql\Builder{




/**
 * Improved version of Doctrine DBAL QueryBuilder v2.5.13, using pure SQL
 * @link https://github.com/doctrine/dbal/blob/v2.5.13/lib/Doctrine/DBAL/Query/QueryBuilder.php
 */
class QueryBuilder implements \Countable, \Stringable
{
    const SELECT = 0;
    const DELETE = 1;
    const UPDATE = 2;
    const INSERT = 3;


    const INNER_JOIN = "INNER JOIN";
    const LEFT_JOIN = "LEFT JOIN";
    const RIGHT_JOIN = "RIGHT JOIN";


    private $sql = null;
    private $params = [];
    private $extraParams = []; // having clause
    private $type = self::SELECT;


    /** @var string[] */
    private $fields = [];

    /** @var array{string, ?string}[] */
    private $tables = [];

    /** @var array<string,array{type: string, table: string, alias: string, cond: string}[]> */
    private $joins = [];

    /** @var array<string, mixed> */
    private $set = [];

    /** @var ?Expression */
    private $where = null;

    /** @var string[] */
    private $group_by = [];

    /** @var ?Expression */
    private $having = null;

    /** @var string[] */
    private $order_by = [];

    /** @var array<string, mixed> */
    private $values = [];

    /** @var ?int */
    private $offset = null;
    /** @var ?int */
    private $limit = null;


    private $aliases = [];
    private $joinRef = [];


    /** @var ?\Sql\QueryHelper */
    private $queryHelper = null;


    public function __clone()
    {
        if (is_object($this->where)) {
            $this->where = clone $this->where;
        }

        if (is_object($this->having)) {
            $this->having = clone $this->having;
        }
    }

    /**
     * @param array $params
     * @return null|\Sql\Statement
     */
    public function execute(array $params = [])
    {
        if ($this->queryHelper) {

            if ($stmt = $this->queryHelper->prepare($this->getSql())) {
                if (!count($params)) {
                    $params = $this->getParams();
                }
                return $stmt->execute($params);
            }
        }

        return null;
    }

    /**
     * @return \Sql\QueryHelper
     */
    public function getQueryHelper()
    {
        return $this->queryHelper;
    }

    /**
     * @param \Sql\QueryHelper $queryHelper
     * @return static
     */
    public function setQueryHelper(\Sql\QueryHelper $queryHelper)
    {
        $this->queryHelper = $queryHelper;
        return $this;
    }


    /** @return int */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Reset the query builder to starting condition
     * @return static
     */
    public function clear()
    {
        $this->sql = null;
        $this->params = [];
        $this->extraParams = [];
        $this->type = self::SELECT;
        $this->fields = [];
        $this->tables = [];
        $this->joins = [];
        $this->set = [];
        $this->where = null;
        $this->group_by = [];
        $this->having = null;
        $this->order_by = [];
        $this->values = [];
        $this->offset = null;
        $this->limit = null;
        $this->aliases = [];
        $this->joinRef = [];
        return $this;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        if (null === $this->sql) {
            $this->sql = "";
            switch ($this->getType()) {

                case self::INSERT:
                    $sql = $this->getSqlInsert();
                    break;
                case self::UPDATE:
                    $sql = $this->getSqlUpdate();
                    break;
                case self::DELETE:
                    $sql = $this->getSqlDelete();
                    break;
                case self::SELECT:
                default:
                    $sql = $this->getSqlSelect();
            }

            if ($sql) {
                $this->sql = $sql;
            }
        }

        return isset($this->sql) ? $this->sql : "";
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return array_merge($this->params, $this->extraParams);
    }


    /**
     * Begins a SELECT statement
     * @param string ...$fields
     * @return static
     */
    public function select($fields = "*")
    {
        if (!is_array($fields)) {
            $fields = func_get_args();
        }
        $this->clear();
        $this->fields = $fields;
        return $this;
    }

    /**
     * Begins an UPDATE statement
     * @param string $table
     * @param ?string $alias
     * @return static
     */
    public function update($table, $alias = null)
    {
        $this->clear();
        $this->type = self::UPDATE;
        return $this->from($table, $alias);
    }

    /**
     * Begins an UPDATE statement
     * @param string $table
     * @return static
     */
    public function insert($table)
    {
        $this->clear();
        $this->type = self::INSERT;
        return $this->from($table);
    }


    /**
     * Begins a DELETE statement
     * @param string $table
     * @param ?string $alias
     * @return static
     */
    public function delete($table, $alias = null)
    {
        $this->clear();
        $this->type = self::DELETE;
        return $this->from($table, $alias);
    }

    /**
     * select table for statement
     * @param string $table
     * @param ?string $alias
     * @return static
     */
    public function from($table, $alias = null)
    {

        if (!empty($alias)) {
            if (isset($this->aliases[$alias])) {
                throw \Sql\SqlException::newInstance(
                    "The alias '%s' is already defined for table '%s'.",
                    $alias,
                    $this->aliases[$alias]
                );
            }
            $this->aliases[$alias] = $table;
            $this->joins[$alias] = [];
        }

        $this->aliases[$table] = $table;
        $this->joins[$table] = [];
        $this->tables[] = [$table, $alias];
        return $this->clearSql();
    }

    /**
     * Join a table
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     * @return $this
     */

    public function join($fromAlias, $table, $alias, $cond)
    {
        return $this->innerJoin($fromAlias, $table, $alias, $cond);
    }

    /**
     * Join a table
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     * @return $this
     */
    public function innerJoin($fromAlias, $table, $alias, $cond)
    {

        if (!isset($this->aliases[$fromAlias])) {

            throw \Sql\SqlException::newInstance(
                "table alias '%s' is not defined",
                $fromAlias
            );
        }

        $this->aliases[$alias] = $this->aliases[$table] = $table;
        $this->joins[$alias] = $this->joins[$table] = [];
        $this->joins[$fromAlias][] = [
            "type" => self::INNER_JOIN,
            "table" => $table,
            "alias" => $alias,
            "cond" => $cond,
        ];


        return $this->clearSql();
    }

    /**
     * Left Join a table
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     * @return $this
     */
    public function leftJoin($fromAlias, $table, $alias, $cond)
    {

        if (!isset($this->aliases[$fromAlias])) {

            throw \Sql\SqlException::newInstance(
                "table alias '%s' is not defined",
                $fromAlias
            );
        }

        $this->aliases[$alias] = $this->aliases[$table] = $table;
        $this->joins[$alias] = $this->joins[$table] = [];
        $this->joins[$fromAlias][] = [
            "type" => self::LEFT_JOIN,
            "table" => $table,
            "alias" => $alias,
            "cond" => $cond,
        ];

        return $this->clearSql();
    }

    /**
     * Right Join a table
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     * @return $this
     */
    public function rightJoin($fromAlias, $table, $alias, $cond)
    {

        if (!isset($this->aliases[$fromAlias])) {
            throw \Sql\SqlException::newInstance(
                "table alias '%s' is not defined",
                $fromAlias
            );
        }

        $this->aliases[$alias] = $this->aliases[$table] = $table;
        $this->joins[$alias] = $this->joins[$table] = [];
        $this->joins[$fromAlias][] = [
            "type" => self::RIGHT_JOIN,
            "table" => $table,
            "alias" => $alias,
            "cond" => $cond,
        ];

        return $this->clearSql();
    }

    /**
     * Begins a WHERE clause, removing the previous clauses
     * to add another clauses use andWhere or orWhere
     *
     * @param string[]|array<string,mixed>|string $cond
     * @return static
     */
    public function where($cond)
    {

        if (!is_array($cond)) {
            $cond = func_get_args();
        }
        list($where, $values) = $this->parseWhereCond($cond);
        $this->where = new Expression(Expression::TYPE_AND, $where);
        foreach ($values as $value) {
            $this->params[] = $value;
        }


        return $this->clearSql();
    }

    /**
     * Adds a AND clause to WHERE statement
     *
     * @param string[]|array<string,mixed>|string $cond
     * @return static
     */
    public function andWhere($cond)
    {

        if (!is_array($cond)) {
            $cond = func_get_args();
        }

        $current = $this->where;
        list($where, $values) = $this->parseWhereCond($cond);
        if (!$current || $current->getType() !== Expression::TYPE_AND) {
            array_unshift($where, $current);
            $this->where = new Expression(Expression::TYPE_AND, $where);
        } else {
            $current->addMany($where);
        }
        foreach ($values as $value) {
            $this->params[] = $value;
        }
        return $this->clearSql();
    }

    /**
     * Adds a OR clause to WHERE statement
     *
     * @param string[]|array<string,mixed>|string $cond
     * @return static
     */
    public function orWhere($cond)
    {

        if (!is_array($cond)) {
            $cond = func_get_args();
        }
        $current = $this->where;
        list($where, $values) = $this->parseWhereCond($cond);
        if (!$current || $current->getType() !== Expression::TYPE_OR) {
            array_unshift($where, $current);
            $this->where = new Expression(Expression::TYPE_OR, $where);
        } else {
            $current->addMany($where);
        }
        foreach ($values as $value) {
            $this->params[] = $value;
        }
        return $this->clearSql();
    }

    /**
     * @param string ...$fields field names
     * @return static
     */
    public function groupBy($fields)
    {

        if (empty($fields)) {
            return $this;
        }
        if (!is_array($fields)) {
            $fields = func_get_args();
        }
        foreach ($fields as $field) {
            $this->group_by[] = $field;
        }
        return $this->clearSql();
    }


    /**
     * Adds a having condition
     * GROUP BY must be used
     *
     * @param string[]|array<string,mixed>|string $cond
     * @return static
     */
    public function having($cond)
    {

        if (!is_array($cond)) {
            $cond = func_get_args();
        }
        list($having, $values) = $this->parseWhereCond($cond);
        $this->having = new Expression(Expression::TYPE_AND, $having);
        foreach ($values as $value) {
            $this->extraParams[] = $value;
        }

        return $this->clearSql();
    }

    public function andHaving($cond)
    {

        if (!is_array($cond)) {
            $cond = func_get_args();
        }

        $current = $this->where;
        list($having, $values) = $this->parseWhereCond($cond);
        if (!$current || $current->getType() !== Expression::TYPE_AND) {
            array_unshift($having, $current);
            $this->having = new Expression(Expression::TYPE_AND, $having);
        } else {
            $current->addMany($having);
        }
        foreach ($values as $value) {
            $this->extraParams[] = $value;
        }
        return $this->clearSql();
    }


    public function orHaving($cond)
    {

        if (!is_array($cond)) {
            $cond = func_get_args();
        }

        $current = $this->where;
        list($having, $values) = $this->parseWhereCond($cond);
        if (!$current || $current->getType() !== Expression::TYPE_OR) {
            array_unshift($having, $current);
            $this->having = new Expression(Expression::TYPE_OR, $having);
        } else {
            $current->addMany($having);
        }
        foreach ($values as $value) {
            $this->extraParams[] = $value;
        }
        return $this->clearSql();
    }


    public function orderBy($fields, $ascending = true)
    {
        static $keywords = [" DESC", " ASC"];

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            $sort = $keywords[intval($ascending)];
            foreach ($keywords as $keyword) {
                if (str_ends_with(strtoupper($field), $keyword)) {
                    $sort = "";
                    break;
                }
            }
            $this->order_by[] = $field . $sort;
        }
        return $this->clearSql();
    }

    public function values(array $values)
    {
        foreach ($values as $field => $value) {
            if (!is_string($field)) {
                throw \Sql\SqlException::newInstance(
                    '$values must be indexed by field name, %d given',
                    $field
                );
            }
            $this->values[$field] = $value;
        }
        return $this->clearSql();
    }


    public function set($values)
    {

        if (!is_array($values)) {
            $values = [$values];
        }

        foreach ($values as $field => $value) {
            if (!is_string($field)) {
                throw \Sql\SqlException::newInstance(
                    '$values must be indexed by field name, %d given',
                    $field
                );
            }
            $this->set[$field] = $value;
        }

        return $this->clearSql();
    }


    public function limit($limit, $offset = null)
    {

        if ($limit > 0 && is_int($limit)) {
            $this->limit = $limit;
            if ($offset > 0 && is_int($offset)) {
                $this->offset = $offset;
            }
        }
        return $this->clearSql();
    }


    public function count()
    {
        return count($this->getParams());
    }


    /**
     * @param array $cond
     * @return array{array,array}
     */
    private function parseWhereCond($cond)
    {

        $values = [];

        $where = [];

        foreach ($cond as $key => $val) {
            if (is_int($key)) {
                $where[] = $val;
                continue;
            }
            if (!str_contains($key, '?')) {
                $key = "$key = ?";
            }

            $where[] = $key;
            $values[] = $val;
        }

        return [$where, $values];
    }

    /**
     * @return static
     */
    private function clearSql()
    {
        $this->sql = null;
        return $this;
    }


    private function esc($value)
    {
        if (!str_contains($value, '`') && !str_contains($value, ' ') && !str_contains($value, '.')) {
            return sprintf('`%s`', $value);
        }
        return $value;
    }


    private function getSqlInsert()
    {

        if (empty($this->tables) || empty($this->values)) {
            return null;
        }

        $query = [
            "INSERT INTO",
            $this->esc($this->tables[0][0]),
        ];

        $fields = [];
        $bindings = [];
        $values = [];
        foreach ($this->values as $field => $value) {
            $fields[] = $this->esc($field);
            if (is_null($value)) {
                $values[] = 'NULL';
                continue;
            }


            $values[] = "?";
            $bindings[] = $value;
        }

        $this->params = $bindings;
        $query[] = sprintf('(%s)', implode(', ', $fields));
        $query[] = sprintf('VALUES(%s)', implode(', ', $values));
        return implode(" ", $query);
    }


    private function getSqlJoin($ref)
    {


        $sql = [];
        foreach ($this->joins[$ref] as $join) {
            $type = $join['type'];
            $alias = $join['alias'];
            $table = $join['table'];
            $cond = $join['cond'];

            if (isset($this->joinRef[$alias])) {
                throw \Sql\SqlException::newInstance(
                    "The given alias '%s' is not unique in FROM and JOIN clause table. The currently registered aliases are: %s",
                    $alias,
                    implode(", ", array_keys($this->joinRef))
                );
            }
            $sql[] = sprintf('%s %s %s ON %s', $type, $this->esc($table), $alias, $cond);
            $this->joinRef[$alias] = true;
        }
        foreach ($this->joins[$ref] as $join) {
            $sql[] = $this->getSqlJoin($join['alias']);
        }
        return implode(" ", $sql);
    }

    private function getSqlUpdate()
    {
        if (empty($this->tables) || empty($this->set)) {
            return null;
        }

        if (empty($alias = $this->tables[0][1])) {
            $alias = "";
        }


        $query = "UPDATE " . rtrim($this->esc($this->tables[0][0]) . " $alias");

        $fields = $bindings = [];

        foreach ($this->set as $field => $value) {

            $field = $this->esc($field);
            if (is_null($value)) {
                $fields[] = "$field = NULL";
                continue;
            }

            $fields[] = "$field = ?";
            $bindings[] = $value;
        }

        // switch bindings
        $this->extraParams = $this->params;
        $this->params = $bindings;

        $query .= " SET " . implode(', ', $fields);

        if ($this->where && count($this->where) > 0) {
            $query .= sprintf(' WHERE %s', $this->where);
        }

        return $query;
    }


    private function getSqlDelete()
    {
        if (empty($this->tables) || empty($this->where) || !count($this->where)) {
            return null;
        }

        return implode(" ", [
            "DELETE FROM",
            $this->esc($this->tables[0][0]),
            sprintf('WHERE %s', $this->where)
        ]);
    }


    private function getSqlSelect()
    {
        if (empty($this->tables) || empty($this->fields)) {
            return null;
        }

        $this->joinRef = [];

        $extra = false;
        $query = [
            "SELECT",
            implode(", ", $this->fields),
        ];

        // FROM + JOIN
        $tables = [];
        foreach ($this->tables as list($table, $alias)) {
            $ref = $table;
            $sql = $this->esc($table) . " ";
            if ($alias) {
                $ref = $alias;
                $sql .= "$alias ";
            }
            $tables[$ref] = $sql . $this->getSqlJoin($ref);
        }

        $query[] = sprintf('FROM %s', implode(", ", $tables));

        // WHERE
        if ($this->where && count($this->where) > 0) {
            $query[] = sprintf('WHERE %s', $this->where);
        }
        // GROUP BY
        if (!empty($this->group_by)) {
            $query[] = sprintf('GROUP BY %s', implode(', ', $this->group_by));
            // having
            if ($this->having && count($this->having) > 0) {
                $query[] = sprintf('HAVING %s', $this->having);
                $extra = true;
            }
        }

        // ORDER BY
        if (!empty($this->order_by)) {
            $query[] = sprintf('ORDER BY %s', implode(', ', $this->order_by));
        }

        if ($this->limit) {
            $limit = sprintf("LIMIT %d", $this->limit);
            if ($this->offset) {
                $limit = sprintf("LIMIT %d, %d", $this->offset, $this->limit);
            }
            $query[] = $limit;
        }


        if (!$extra) {
            $this->extraParams = [];
        }

        return implode(" ", $query);
    }

    public function __toString()
    {
        return $this->getSql();
    }
}

}
namespace Sql{



abstract class BaseDriver implements Driver
{

    const DRIVER_TYPE = "";

    /** @var null|\mysqli|\Pdo|\SQLite3|resource|object */
    protected $link = null;
    private $transactionCounter = 0;
    protected $throwsOnError = false;


    public function __construct($throwsOnError = false)
    {
        $this->throwsOnError = $throwsOnError;
    }

    /**
     * @return null|\mysqli|\Pdo|\SQLite3|resource|object
     */
    public function link()
    {
        return $this->link;
    }

    public function type()
    {
        return static::DRIVER_TYPE;
    }


    abstract protected function doBeginTransaction();

    abstract protected function doRollBack();

    abstract protected function doCommit();

    final public function beginTransaction()
    {
        if (!$this->link) {
            return $this->noLink();
        }

        if (!$this->transactionCounter++) {

            try {
                $this->doBeginTransaction();
            } catch (\Exception $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotStartTransaction($err);
                }
                return false;

            } catch (\Throwable $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotStartTransaction($err);
                }
                return false;
            }

        }

        return $this->transactionCounter >= 0;
    }

    final public function rollBack()
    {

        if (!$this->link) {
            return $this->noLink();
        }


        try {
            if ($this->transactionCounter >= 0) {
                try {
                    return $this->doRollBack();
                } catch (\Exception $err) {
                    if ($this->throwsOnError) {
                        throw SqlException::cannotEndTransaction($err);
                    }
                    return false;

                } catch (\Throwable $err) {
                    if ($this->throwsOnError) {
                        throw SqlException::cannotEndTransaction($err);
                    }
                    return false;
                }
            }
            return false;
        } finally {
            $this->transactionCounter = 0;
        }

    }

    final public function commit()
    {
        if (!$this->link) {
            return $this->noLink();
        }


        if (!--$this->transactionCounter) {

            try {
                return $this->doCommit();
            } catch (\Exception $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotEndTransaction($err);
                }
                return false;

            } catch (\Throwable $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotEndTransaction($err);
                }
                return false;
            }

        }
        return $this->transactionCounter >= 0;
    }


    public function close()
    {
        $this->link = null;
        $this->transactionCounter = 0;
        return false;
    }

    public function exec($query)
    {
        return $this->query($query) !== false;
    }


    public function prepare($query)
    {

        if ($this->link) {

            try {
                $stmt = $query;
                if ($this->canPrepare($query)) {
                    $stmt = $this->link->prepare($query);
                }

                if ($stmt) {
                    return new Statement($this, $stmt, $query);
                }


            } catch (\Exception $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotPrepare($err);
                }
            }
            return false;
        }
        return $this->noLink();
    }

    public function error()
    {
        return [-1, "database connection error"];
    }


    public function lastInsertId()
    {
        if ($this->throwsOnError) {
            throw SqlException::cannotConnect();
        }
        return 0;
    }

    /**
     * @return bool
     */
    public function throwsOnError()
    {
        return $this->throwsOnError;
    }

    /**
     * @param bool $throwsOnError
     * @return static
     */
    public function setThrowsOnError($throwsOnError)
    {
        $this->throwsOnError = $throwsOnError !== false;

        return $this;
    }


    protected function noLink($value = false)
    {
        if (!$this->throwsOnError) {
            return $value;
        }

        throw SqlException::cannotConnect();

    }


    protected function assertResult($value)
    {
        if (!($value instanceof Result)) {
            throw new \InvalidArgumentException(sprintf('$result must be an instance of %s, %s given', Result::class, get_debug_type($value)));
        }
    }

    protected function assertStatement($value)
    {
        if (!($value instanceof Statement)) {
            throw new \InvalidArgumentException(sprintf('$statement must be an instance of %s, %s given', Statement::class, get_debug_type($value)));
        }
    }

    protected function assertQueryIsString($value)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('$query must be a string, %s given', get_debug_type($value)));
        }
    }

    /**
     * @param string $query
     * @return bool$value
     */
    protected function canPrepare($query)
    {
        $this->assertQueryIsString($query);
        return 0 < $this->parseNumberOfQueryParameters($query);
    }

    /**
     * @param string $query
     * @return int
     */
    protected function parseNumberOfQueryParameters($query)
    {
        return substr_count($query, "?");
    }

    /**
     * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
     * @return array{?string, ?string, ?string, ?string, ?string}
     */
    protected function parseParams(array $params)
    {

        $host =
        $username =
        $password =
        $database =
        $charset =
            null;

        if (!empty($params['host'])) {
            $host = $params['host'];
        }
        if (!empty($params['username'])) {
            $username = $params['username'];
        }
        if (!empty($params['password'])) {
            $password = $params['password'];
        }
        if (!empty($params['database'])) {
            $database = $params['database'];
        }
        if (!empty($params['charset'])) {
            $charset = $params['charset'];
        }

        return [$host, $username, $password, $database, $charset];

    }

}

}
namespace Sql{


abstract class BasePdoDriver extends BaseDriver
{
    public function connect(array $params)
    {
        if ($this->link) {
            $this->link->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_BOTH);
            $this->link->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $this->link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return true;
        }
        return $this->noLink();
    }

    public function error()
    {
        if ($this->link) {
            $err = $this->link->errorInfo();
            return array_slice($err, 1);
        }
        return parent::error();
    }

    public function lastInsertId()
    {

        if ($this->link) {
            try {
                return $this->link->lastInsertId();
            } catch (\PDOException $err) {
            }
        }

        return parent::lastInsertId();
    }


    protected function doBeginTransaction()
    {
        return $this->link->beginTransaction();
    }

    protected function doRollBack()
    {
        return $this->link->rollBack();
    }

    protected function doCommit()
    {
        return $this->link->commit();
    }

    public function close()
    {
        parent::close();
        return true;
    }


    public function quote($string)
    {
        if (!is_string($string)) {
            return "";
        }
        if (!$this->link) {
            return $this->noLink($string);
        }
        return $this->link->quote($string) ?: "";
    }


    public function query($query)
    {

        if ($this->link) {

            try {

                if ($result = $this->link->query($query)) {
                    return new Result($this, $result);
                }

            } catch (\PDOException $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotExecute($err);
                }
            }


            return false;
        }
        return $this->noLink();


    }

    public function bindParams($statement, array $params)
    {
        if ($this->link) {
            $this->assertStatement($statement);
            $stmt = $statement->getStatement();
            if ($cnt = count($params)) {


                try {

                    if (!($stmt instanceof \PDOStatement)) {
                        throw new \InvalidArgumentException('$statement is not a prepared statement, params cannot be bound.');
                    }


                    if ($cnt !== $this->parseNumberOfQueryParameters($statement->getSql())) {
                        throw new \InvalidArgumentException("Number of variables doesn't match number of parameters in prepared statement");
                    }


                    foreach (array_keys($params) as $i) {

                        $refs = [$i + 1, &$params[$i]];
                        switch (get_debug_type($params[$i])) {
                            case "null":
                                $refs[] = \PDO::PARAM_NULL;
                                break;
                            case "bool":
                                $refs[] = \PDO::PARAM_BOOL;
                                break;
                            case "int":
                                $refs[] = \PDO::PARAM_INT;
                                break;
                            default:
                                $refs[] = \PDO::PARAM_STR;
                        }
                        if (!call_user_func_array([$stmt, 'bindParam'], $refs)) {
                            return false;
                        }
                    }


                } catch (\PDOException $err) {
                    if ($this->throwsOnError) {
                        throw SqlException::cannotBind($err);
                    }
                    return false;
                }


            }

            return $statement;
        }

        return $this->noLink();
    }

    /**
     * @param \PDOStatement $stmt
     * @return void
     */
    private function closeCursor($stmt)
    {

        try {
            $stmt->closeCursor();
        } catch (\PDOException $err) {
        }

    }

    public function execute($statement)
    {
        if ($this->link) {

            $this->assertStatement($statement);
            $stmt = $statement->getStatement();

            if (is_string($stmt)) {
                return $this->query($stmt);
            }

            // close cursor for some compatible drivers
            // repeated uses of the same statement
            $this->closeCursor($stmt);


            try {

                if (!$stmt->execute()) {
                    return false;
                }
                return new Result($this, $stmt);

            } catch (\PDOException $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotExecute($err);
                }

            }
            return false;
        }

        return $this->noLink();

    }


    public function fetch($result, $mode = FETCH_BOTH)
    {
        if ($this->link) {
            $this->assertResult($result);

            $resp = $result->getResult();
            if ($resp instanceof \PDOStatement) {

                switch ($mode) {
                    case FETCH_NUM:
                        $method = \PDO::FETCH_NUM;
                        break;
                    case FETCH_ASSOC:
                        $method = \PDO::FETCH_ASSOC;
                        break;
                    case FETCH_OBJ:
                        $method = \PDO::FETCH_OBJ;
                        break;
                    default:
                        $method = \PDO::FETCH_BOTH;
                }

                try {

                    if ($row = $resp->fetch($method)) {
                        return $row;
                    }

                } catch (\PDOException $err) {
                    if ($this->throwsOnError) {
                        throw SqlException::cannotFetch($err);
                    }

                }
            }

            return null;
        }

        return $this->noLink(null);
    }
}

}
namespace Sql{


if (extension_loaded('pdo_mysql')) {
    class MysqlPdoDriver extends BasePdoDriver implements Driver
    {

        const DRIVER_TYPE = "mysql";

        /**
         * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
         * @return bool
         */
        function connect(array $params)
        {


            if ($this->link) {
                $this->close();
            }

            list($host, $username, $password, $database, $charset) = $this->parseParams($params);

            if (!$charset) {
                $charset = 'utf8mb4';
            }

            $port = null;
            if ($host && str_contains($host, ':')) {
                @list($host, $_port) = explode(':', $host);
                if (is_numeric($_port)) {
                    $port = intval($_port);
                }
            }


            try {

                $dsn = 'mysql:host=' . $host;
                if ($port) {
                    $dsn .= ';port=' . $port;
                }

                if ($charset) {
                    $dsn .= ';charset=' . $charset;
                }

                if ($database) {
                    $dsn .= ';dbname=' . $database;
                }

                $link = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]);

                $this->link = $link;

            } catch (\PDOException $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotConnect($err);
                }
            }

            return parent::connect($params);
        }
    }
}

}
namespace Sql{


if (extension_loaded('pdo_sqlite')) {

    /**
     * @param array{database: ?string} $params
     * @return bool
     */
    class SqlitePdoDriver extends BasePdoDriver implements Driver
    {

        const DRIVER_TYPE = "sqlite";

        public function connect(array $params)
        {
            if ($this->link) {
                $this->close();
            }
            try {

                $db = ":memory:";

                if (isset($params["database"])) {
                    $db = $params["database"];
                } elseif (!empty($params["host"])) {
                    $db = $params["host"];
                }


                $conn = new \PDO('sqlite:' . $db, null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]);

                $this->link = $conn;
                parent::connect($params);
                $conn->exec('PRAGMA busy_timeout=5000');
                $conn->exec('PRAGMA foreign_keys=ON');
                $conn->exec('PRAGMA locking_mode=NORMAL');
                $conn->exec('PRAGMA journal_mode=WAL');

            } catch (\PDOException $err) {
                if ($this->throwsOnError) {
                    throw SqlException::cannotConnect($err);
                }
                return false;
            }


            return true;
        }

    }
}

}
namespace {

class SqlConnector
{

    const DEFAULT_CONNECTION = 'default';
    const DEFAULT_TYPE = 'mysql';


    protected static $drivers = [
        "mysql" => [
            "Sql\MysqliDriver",
            "Sql\\MysqlPdoDriver",
        ],
        "sqlite" => [
            "Sql\\Sqlite3Driver",
            "Sql\\SqlitePdoDriver",
        ],
    ];


    /** @var array<string,Sql\QueryHelper> */
    protected static $connections = [];
    /** @var array<string,array{type: string, host: string|string[]|null, username: string|string[]|null, password: string|string[]|null, database: ?string, charset: ?string}> */
    protected static $configuration = [];


    /**
     * @template T of Sql\Driver
     *
     * @param T|class-string<T> $driver
     * @param bool $append
     * @return void
     */
    public static function addDriver($driver, $append = true)
    {
        if (is_object($driver)) {
            $driver = get_class($driver);
        }
        if (is_subclass_of($driver, Sql\Driver::class)) {
            $type = (new $driver)->type();
            if (!isset(self::$drivers[$type])) {
                self::$drivers[$type] = [];
            }
            $drivers = array_filter(self::$drivers[$type], function ($className) use ($driver) {
                return $className !== $driver;
            });
            self::$drivers[$type] = $append
                ? array_merge($drivers, [$driver])
                : array_merge([$driver], $drivers);
        }

    }


    /**
     * @return bool
     */
    public static function hasDatabaseConnection($connectionName = self::DEFAULT_CONNECTION)
    {
        return isset($connections[$connectionName]) || !empty(self::$configuration[$connectionName]);
    }

    /**
     * @param string $connectionName
     * @return bool
     */
    public static function hasDatabaseConfiguration($connectionName = self::DEFAULT_CONNECTION)
    {
        return isset(self::$configuration[$connectionName]);
    }

    /**
     * @param Sql\QueryHelper $queryHelper
     * @param string $connectionName
     * @return void
     */
    public static function setConnection($queryHelper, $connectionName = self::DEFAULT_CONNECTION)
    {
        if ($queryHelper instanceof Sql\QueryHelper) {
            self::$connections[$connectionName] = $queryHelper;
        }
    }

    /**
     * @param string $connectionName
     *
     * @return Sql\QueryHelper
     */
    public static function getConnection($connectionName = self::DEFAULT_CONNECTION)
    {

        if (!is_string($connectionName)) {
            return null;
        }

        if (!isset(self::$connections[$connectionName])) {
            if (self::hasDatabaseConfiguration($connectionName)) {
                $cacheKey = sha1("Connection::" . getcwd() . $connectionName);
                $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . ".cache";
                $prev = null;
                if (file_exists($cacheFile)) {
                    $prev = file_get_contents($cacheFile);
                }
                $config = self::$configuration[$connectionName];
                $type = $config["type"];
                unset($config["type"]);
                $drivers = [];

                if (!empty(self::$drivers[$type])) {
                    foreach (self::$drivers[$type] as $className) {
                        if (class_exists($className)) {
                            $drivers[] = new $className(constant_get("DEV_ENV") === true);
                            break; // first matching driver
                        }
                    }
                }

                if (empty($drivers)) {
                    throw new RuntimeException(sprintf('Cannot connect to database driver %s.', $type));
                }

                $hosts = $config["host"];
                $users = $config["username"];
                $passwords = $config["password"];

                if (!is_array($hosts)) {
                    $hosts = [$hosts];
                }
                if (!is_array($users)) {
                    $users = [$users];
                }
                if (!is_array($passwords)) {
                    $passwords = [$passwords];
                }

                $params = [
                    "host" => $prev,
                    "username" => $users[0],
                    "password" => $passwords[0],
                    "charset" => $config["charset"],
                    "database" => $config["database"],
                ];


                if (!empty($prev)) {

                    $index = array_search($prev, $hosts);
                    if ($index !== false) {
                        $params["host"] = $prev;
                        if (isset($users[$index])) {
                            $params["username"] = $users[$index];
                        }
                        if (isset($passwords[$index])) {
                            $params["password"] = $passwords[$index];
                        }
                        /** @var Sql\Driver $connector */
                        foreach ($drivers as $connector) {
                            if ($connector->connect($params)) {
                                // no need to save cache there as previous connection has been used
                                return self::$connections[$connectionName] = new Sql\QueryHelper($connector);
                            }
                        }

                    }

                }

                // iterate all hostnames
                foreach ($hosts as $index => $current) {
                    // prev already been checked
                    if ($prev === $current) {
                        continue;
                    }

                    $params["host"] = $current;
                    if (isset($users[$index])) {
                        $params["username"] = $users[$index];
                    }
                    if (isset($passwords[$index])) {
                        $params["password"] = $passwords[$index];
                    }

                    /** @var Sql\Driver $connector */
                    foreach ($drivers as $connector) {
                        if ($connector->connect($params)) {
                            @file_put_contents($cacheFile, $current);
                            return self::$connections[$connectionName] = new Sql\QueryHelper($connector);
                        }
                    }
                }

                throw new RuntimeException('Cannot connect to database');

            }
        }

        return self::$connections[$connectionName];
    }

    /**
     * @param string|string[] $host
     * @param string|string[]|null $user
     * @param string|string[]|null $password
     * @param null|string $db
     * @param mixed $name
     * @param string $type
     */
    public static function setDatabaseConfiguration($host, $user = null, $password = null, $db = null, $name = self::DEFAULT_CONNECTION, $type = self::DEFAULT_TYPE)
    {
        if (is_array($db)) {
            $db = $db[0];
        }
        self::$configuration[$name] = [
            "type" => $type,
            "host" => $host,
            "username" => $user,
            "password" => $password,
            "database" => $db,
            "charset" => null,
        ];
    }

    /**
     * @param string $connectionName
     * @return bool
     */
    public static function closeConnection($connectionName = self::DEFAULT_CONNECTION)
    {

        if ($conn = self::getConnection($connectionName)) {
            if ($conn->close()) {
                unset(self::$connections[$connectionName]);
                return true;
            }
        }

        return false;
    }


    /**
     * @param string $connectionName
     * @return bool
     */
    public static function tryConnect($connectionName = self::DEFAULT_CONNECTION)
    {
        try {
            return self::getConnection($connectionName) !== null;
        } catch (Exception $err) {
        }

        return false;
    }

}

}
namespace {

class Sql extends SqlConnector
{


    /**
     * @param string $db
     * @param string $connectionName
     * @return bool
     */

    public static function useDb($db, $connectionName = self::DEFAULT_CONNECTION)
    {
        if (self::getConnection($connectionName)->type() === "mysql") {
            return self::easyQuery("USE `$db`") !== null;
        }
        return false;
    }


    /**
     * @param string $connectionName
     * @return array{int,string}
     */
    public static function getError($connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->error();
    }


    /**
     * @param string $value
     * @param string $connectionName
     * @return string
     */
    public static function escapeValue($value, $connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->quote($value);
    }


    /**
     * @param array $arr
     * @param string $connectionName
     * @return array
     */
    public static function escapeRecursive(array $arr, $connectionName = self::DEFAULT_CONNECTION)
    {

        $result = [];

        foreach ($arr as $key => $value) {

            if (is_array($value)) {
                $result[$key] = self::escapeRecursive($value, $connectionName);
                continue;
            }

            $result[$key] = self::escapeValue($value, $connectionName);
        }
        return $result;
    }

    /**
     * Escape super globales.
     */
    public static function escapeRequest($connectionName = self::DEFAULT_CONNECTION)
    {
        static $escaped = false;

        if (!$escaped) {
            $escaped = true;
            if (self::hasDatabaseConnection()) {
                $_GET = self::escapeRecursive($_GET, $connectionName);
                $_POST = self::escapeRecursive($_POST, $connectionName);
                $_REQUEST = self::escapeRecursive($_REQUEST, $connectionName);
            }
        }
    }

    /**
     * @param string $query
     * @param string $connectionName
     * @return false|Sql\Statement
     */
    public static function prepare($query, $connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->prepare($query);
    }


    /**
     * @param $stmt
     * @param $bindings
     * @return Sql\Statement|null
     */
    public static function bindParams($stmt, $bindings = [])
    {
        if ($stmt instanceof Sql\Statement) {
            return $stmt->bindParams($bindings);
        }

        return null;
    }

    /**
     * @param Sql\Statement $stmt
     * @return ?Sql\Statement
     */
    public static function execute($stmt, array $bindings = [])
    {
        if ($stmt instanceof Sql\Statement && $stmt->execute($bindings)) {
            return $stmt;
        }
        return null;
    }

    /**
     * @param string $connectionName
     * @return int|string
     */
    public static function getLastInsertId($connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->lastInsertId();
    }

    /**
     * @param string|Sql\Statement $stmt
     * @param array $bindings
     * @param bool $assoc
     * @param string $connectionName
     * @return Traversable
     */
    public static function getResults($stmt, array $bindings = [], $assoc = false, $connectionName = self::DEFAULT_CONNECTION)
    {

        if (is_string($stmt)) {
            $stmt = self::prepare($stmt, $connectionName);
        }

        if ($stmt instanceof Sql\Statement && $stmt->execute()) {
            return $stmt->fetch($assoc ? Sql\FETCH_ASSOC : Sql\FETCH_BOTH);
        }

        return new EmptyIterator();
    }

    /**
     * /!\ If using foreach getResults() is faster (less memory consumption).
     * @param string|Sql\Statement $stmt
     * @param array $bindings
     * @param bool $assoc
     * @param string $connectionName
     * @return array
     */
    public static function getResultsArray($stmt, array $bindings = [], $assoc = false, $connectionName = self::DEFAULT_CONNECTION)
    {
        return iterator_to_array(self::getResults($stmt, $bindings, $assoc, $connectionName));
    }


    /**
     * @param string|Sql\Statement $stmt
     * @param array $bindings
     * @param bool $assoc
     * @param string $connectionName
     * @return array|null
     */
    public static function findOne($stmt, array $bindings = [], $assoc = false, $connectionName = self::DEFAULT_CONNECTION)
    {
        if (is_string($stmt)) {
            if (!str_contains($stmt, ' limit ')) {
                $stmt .= " LIMIT 1";
            }
            $stmt = self::prepare($stmt, $connectionName);
        }

        if ($stmt instanceof Sql\Statement && $stmt->execute($bindings)) {
            return $stmt->fetchOne($assoc ? Sql\FETCH_ASSOC : Sql\FETCH_BOTH);
        }
        return null;
    }

    public static function findColumn($stmt, array $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if (is_string($stmt)) {
            if (!str_contains($stmt, ' limit ')) {
                $stmt .= " LIMIT 1";
            }
            $stmt = self::prepare($stmt, $connectionName);
        }

        if ($stmt instanceof Sql\Statement && $stmt->execute($bindings)) {
            return $stmt->fetchCol();
        }
        return null;
    }

    /**
     * @param Sql\Statement|string $query
     * @param array $bindings
     * @param string $connectionName
     * @return \Sql\Statement|null
     */
    public static function easyQuery($query, array $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        $stmt = $query;
        if (is_string($query)) {
            $stmt = self::prepare($query, $connectionName);
        }
        return self::execute($stmt, $bindings);
    }

    /**
     * @param string $table
     * @param array<string,mixed> $values
     * @param string $connectionName
     * @return int|string
     */
    public static function easyInsert($table, $values = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if (!is_string($table) || !count($values)) {
            return 0;
        }

        $query = $table;

        if (0 !== mb_stripos($table, 'insert into')) {
            $query = "INSERT INTO $table";
        }

        $query = rtrim($query);
        $bindings = $keys = [];

        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                return 0;
            }
            $bindings[] = $values[$key];

            if (false === mb_strpos($key, '`')) {
                $key = "`{$key}`";
            }

            $keys[$key] = '?';
        }

        $query = sprintf(
            '%s (%s) VALUES(%s) ',
            $query,
            implode(', ', array_keys($keys)),
            implode(', ', array_values($keys))
        );


        if (self::easyQuery($query, $bindings, $connectionName)) {
            return self::getLastInsertId($connectionName);
        }


        return 0;
    }

    /**
     * @param string $table
     * @param array|string $cond
     * @param array<string,mixed> $values
     * @param string $connectionName
     * @return bool
     */
    public static function easyUpdate($table, $cond, $values = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if (!is_string($table) || !count($values)) {
            return false;
        }

        $query = sprintf('UPDATE %s SET', $table);


        $bindings = [];

        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                return false;
            }

            if (null !== $values[$key]) {
                $bindings[] = $values[$key];
                $query .= sprintf(' `%s` = ?,', $key);
                continue;
            }
            $query .= sprintf(' `%s` = NULL,', $key);
        }

        $query = rtrim($query, ',');

        $whereStmt = '';

        if (is_string($cond)) {
            $whereStmt = ltrim($cond);
        } elseif (is_array($cond) && count($cond) > 0) {
            $prev = key($cond);

            $conditions = [];

            foreach ($cond as $index => $val) {
                if (gettype($prev) !== gettype($index)) {
                    throw new InvalidArgumentException("Invalid condition index {$index}");
                }
                $prev = $index;

                if (0 === $index) {
                    // ['id = ? AND num = ?', $id, $num]
                    $conditions[] = $val;
                    continue;
                }

                if (!is_int($index)) {
                    if (false !== mb_strpos($index, ' ')) {
                        // ['id = ?' => $id ]
                        $conditions[] = $index;
                    } else {
                        // ['id' => $id ]
                        $conditions[] = sprintf('`%s` LIKE ?', $index);
                    }
                }
                $bindings[] = $val;
            }
            $whereStmt = implode(' AND ', $conditions);
        }

        if (empty($whereStmt)) {
            return false;
        }

        if (0 !== mb_stripos($whereStmt, 'where ')) {
            $whereStmt = "WHERE $whereStmt";
        }

        $query .= " $whereStmt";

        return self::easyQuery($query, $bindings, $connectionName) !== null;
    }

    /**
     * @param string $table
     * @param array<string,mixed>|string[]|string $cond
     * @param string $connectionName
     * @return \Sql\Statement|null
     */
    public static function buildSelectStatement($table, $cond = '', $connectionName = self::DEFAULT_CONNECTION)
    {
        static $except = ['order by ', 'limit ', 'group by ', 'having '];

        if (!is_string($table)) {
            return null;
        }
        $bindings = [];
        $query = $table;

        if (0 !== mb_stripos($table, 'select ')) {
            $query = "SELECT * FROM $table";
        }

        $where = $cond;

        if (!empty($where)) {
            $whereStmt = '';
            if (is_string($where)) {
                $whereStmt = "WHERE {$where}";
                foreach ($except as $startsWith) {
                    if (0 === mb_stripos($where, $startsWith)) {
                        $whereStmt = $where;
                        break;
                    }
                }
            } elseif (is_array($where)) {
                $conditions = [];
                $suffix = '';

                foreach ($where as $index => $val) {
                    if (is_int($index)) {
                        foreach ($except as $startsWith) {
                            if (0 === mb_stripos($val, $startsWith)) {
                                $suffix .= " {$val}";
                                continue 2;
                            }
                        }
                        // list of conditions
                        $conditions[] = $val;
                        continue;
                    }
                    // index is str
                    $bindings[] = $val;
                    if (false === mb_stripos($index, ' ')) {
                        $conditions[] = sprintf('%s LIKE ?', $index);
                    } else {
                        $conditions[] = $index;
                    }
                }
                if (!empty($conditions)) {
                    $whereStmt = rtrim(sprintf('WHERE %s %s', implode(' AND ', $conditions), $suffix));
                }
            }

            $query .= " $whereStmt";
            $query = rtrim($query);
        }

        return self::bindParams(self::prepare($query, $connectionName), $bindings);
    }

    /**
     * @param string $table
     * @param array<string,mixed>|string[]|string $cond
     * @param array $bindings
     * @param string $connectionName
     * @return int
     */
    public static function easyCount($table, $cond = '', $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        $result = 0;

        if (false === mb_stripos($table, 'select ')) {
            $table = "SELECT COUNT(*) FROM $table";
        }

        if (!is_array($cond)) {
            $cond = [$cond];
        }

        // query faster using limit than not (symfony polyfills are very useful)
        $limit = array_any($cond, function ($line) {
            return str_contains(strtolower($line), 'limit ');
        });

        if (!$limit) {
            $cond[] = 'LIMIT 1';
        }


        if ($stmt = self::buildSelectStatement($table, $cond, $connectionName)) {
            $result = intval(self::findColumn($stmt, $bindings));
        }

        return $result;
    }


    /**
     * @param string $table
     * @param array<string,mixed>|string[]|string $cond
     * @param array $bindings
     * @param string $connectionName
     *
     * @return array
     */
    public static function easySelect($table, $cond = '', $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if ($stmt = self::buildSelectStatement($table, $cond, $connectionName)) {
            if ($result = self::execute(self::bindParams($stmt, $bindings))) {
                return $result->fetchAll(Sql\FETCH_ASSOC);
            }
        }
        return [];
    }


    /**
     * @param string $table
     * @param array<string,mixed>|string[]|string $cond
     * @param array $bindings
     * @param string $connectionName
     *
     * @return ?array
     */
    public static function easySelectOne($table, $cond = '', $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {

        if (!is_array($cond)) {
            $cond = [$cond];
        }

        // query faster using limit than not
        $limit = array_any($cond, function ($line) {
            return str_contains(strtolower($line), 'limit ');
        });

        if (!$limit) {
            $cond[] = 'LIMIT 1';
        }

        if ($stmt = self::buildSelectStatement($table, $cond, $connectionName)) {
            if ($result = self::execute(self::bindParams($stmt, $bindings))) {
                return $result->fetchOne(Sql\FETCH_ASSOC);
            }
        }
        return null;
    }


    /**
     * @param string $table
     * @param null|string|string[] $field
     * @param string $connectionName
     *
     * @return TableDescriptionField[]
     */
    public static function describeTable($table, $field = null, $connectionName = self::DEFAULT_CONNECTION)
    {

        $driver = self::getConnection($connectionName);
        $type = $driver->type();

        $result = [];

        if ($type === "mysql") {


            $where = '';
            $bindings = [];

            $table = trim($table, '`');

            if (null !== $field) {
                if (!is_array($field)) {
                    $field = [$field];
                }

                $cond = [];

                foreach ($field as $f) {
                    if (is_string($f)) {
                        $f = trim($f, '`');
                        $cond[] = '?';
                        $bindings[] = "{$f}";
                    }
                }

                if (count($bindings)) {
                    $where = sprintf('WHERE Field IN (%s)', implode(', ', $cond));
                }
            }

            foreach (
                self::getResults(
                    self::easyQuery(
                        "SHOW COLUMNS FROM {$table} {$where}",
                        $bindings,
                        $connectionName
                    )
                ) as $item
            ) {
                $result[$item['Field']] = TableDescriptionField::make($item);
            }
        }
        return $result;
    }
}

}
namespace {

class TableDescriptionField
{
    protected $field = '';
    protected $type = '';
    protected $null = 'NO';
    protected $key = '';
    protected $default = '';
    protected $extra = '';

    /**
     * @var string
     */
    protected $fieldType = null;

    /**
     * @var int
     */
    protected $fieldLength = null;

    /**
     * @var array<int,mixed>
     */
    protected $fieldChoices = null;

    /**
     * @param string[] $data
     * @param null|mixed $instance
     *
     * @return self
     */
    public static function make($data = [], $instance = null)
    {

        if (!($instance instanceof self)) {
            $instance = new self();
        }


        foreach ($data as $field => $value) {
            $key = mb_strtolower($field);

            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }
        return $instance;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getFieldType()
    {
        $this->parseType();
        return $this->fieldType;
    }

    /**
     * @return int
     */
    public function getFieldLength()
    {
        $this->parseType();
        return $this->fieldLength;
    }

    /**
     * @return array
     */
    public function getFieldChoices()
    {
        $this->parseType();
        return $this->fieldChoices;
    }

    /**
     * @return bool
     */
    public function isNullable()
    {
        return 'YES' === $this->null;
    }

    /**
     * @return string
     */
    public function getFieldKey()
    {
        return $this->key;
    }

    /**
     * @return bool
     */
    public function isPrimaryKey()
    {
        return 'PRI' === $this->key;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        $def = $this->default;

        if ('NULL' === $def) {
            return null;
        }
        return $this->decode($def);
    }

    public function getExtra()
    {
        return $this->extra;
    }

    protected function decode($value)
    {
        if (is_string($value)) {
            if ('null' === mb_strtolower($value)) {
                return null;
            }

            if (mb_strlen($value)) {
                $decoded = json_decode($value, true);

                if (null === $decoded) {
                    $decoded = $value;
                }

                $value = $decoded;
            }
        }

        return $value;
    }

    protected function parseType()
    {
        if (null !== $this->fieldType) {
            return;
        }
        $this->fieldLength = 0;
        $this->fieldChoices = [];
        $type = $this->type;
        $len = -1;

        if (preg_match('#^(.+)\((.+)\)$#', $this->type, $matches)) {
            list(, $type, $choices) = $matches;

            $choices = explode(',', $choices);
            $decoded = [];

            foreach ($choices as $choice) {
                $choice = trim($choice);
                $choice = trim($choice, "'");
                $decChoice = $this->decode($choice);

                if (null === $decChoice) {
                    $decChoice = $choice;
                }

                if (is_string($decChoice)) {
                    $l = mb_strlen($decChoice);

                    if ($l > $len) {
                        $len = $l;
                    }
                }
                $decoded[] = $decChoice;
            }

            switch (count($decoded)) {
                case 1:
                    $this->fieldLength = $decoded[0];
                    break;

                default:
                    $this->fieldChoices = $decoded;
            }
        }

        $this->fieldType = trim($type);

        if ($len > -1) {
            $this->fieldLength = $len;
        }
    }

}

}
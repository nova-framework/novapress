<?php
/**
 * Connection - A PDO based Database Connection.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Database;

use Core\Config;
use Database\Model;
use Database\Query;

use PDO;


class Connection
{
    /**
     * Connection instances
     *
     * @var Connection[]
     */
    private static $instances = array();

    /**
     * The active PDO connection.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * The default fetch mode of the connection.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_OBJECT;

    /**
     * The table prefix for the connection.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * Create a new connection instance.
     *
     * @param  array  $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->tablePrefix = $config['prefix'];

        // Create the PDO instance from the given configuration.
        extract($config);

        $dsn = "$driver:host={$hostname};dbname={$database}";

        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$collation}"
        );

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    /**
     * Get an instance of the Database Connection.
     *
     * @param $name string Name of the connection provided in the configuration
     * @return Connection|\PDO|null
     * @throws \Exception
     */
    public static function getInstance($name = 'default')
    {
        if (isset(static::$instances[$name])) {
            // When already have an Connection instance, return it.
            return static::$instances[$name];
        }

        // Get the requested Connection options.
        $config = Config::get('database');

        if (isset($config[$name])) {
            // Create the Connection instance.
            static::$instances[$name] = new static($config[$name]);

            // Return the Connection instance.
            return static::$instances[$name];
        }

        throw new \Exception("Connection name '$name' is not defined in your configuration");
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return \Database\Query
     */
    public function table($table)
    {
        $query = new Query($this);

        return $query->from($table);
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function select($query, $bindings = array())
    {
        $statement = $this->getPdo()->prepare($query);

        $statement->execute($bindings);

        return $statement->fetchAll($this->getFetchMode());
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($query, $bindings = array())
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function update($query, $bindings = array())
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, $bindings = array())
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = array())
    {
        return $this->getPdo()->prepare($query)->execute($bindings);
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = array())
    {
        $statement = $this->getPdo()->prepare($query);

        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * Get the table prefix for the connection.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Set the table prefix in use by the connection.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * Get the PDO instance.
     *
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Get the default fetch mode for the connection.
     *
     * @return int
     */
    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    /**
     * Set the default fetch mode for the connection.
     *
     * @param  int  $fetchMode
     * @return int
     */
    public function setFetchMode($fetchMode)
    {
        $this->fetchMode = $fetchMode;
    }
}
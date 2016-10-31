<?php

namespace Laelaps\GearmanBundle;

class LogClient
{
    /**
     * Store all calls from different gearman clients
     * @var array
     */
    protected static $calls = array();
    
    /**
     * List of servers;
     * 
     * @var array
     */
    protected $servers = array();

    /**
     *
     * @var \GearmanClient
     */
    protected $client;

    public function __construct()
    {
        $this->client = new \GearmanClient();
    }

    /**
     * Add a server
     * 
     * @param string $host
     * @param int $port
     * @return boolean
     */
    public function addServer($host, $port)
    {
        $start = microtime(true);
        $result = $this->client->addServer($host, $port);
        $this->logCall($start, 'addServer', array($host, $port), $result);
        $this->servers[] = array($host, $port);

        return $result;
    }

    /**
     * Get the list of servers
     * 
     * @return array
     */
    public function getServers()
    {
        return $this->servers;
    }
    
    /**
     * 
     * @param string $name
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        // Only store calls to do something.
        if (strpos($name, 'do') !== false) {
            $start = microtime(true);
            $result = $this->client->$name($arguments[0], $arguments[1]);
            $this->logCall($start, $name, $arguments, $result);
        } elseif (count($arguments) == 1) {
            $result = $this->client->$name($arguments[0]);
        } elseif (count($arguments) > 1) {
            $result = $this->client->$name($arguments);
        } else {
            $result = $this->client->$name();
        }

        return $result;
    }

    /**
     * Store the results in the array.
     * 
     * @param float $start
     * @param string $name
     * @param string $workload
     * @param string $result
     */
    protected function logCall($start, $name, $workload, $result)
    {
        $time = microtime(true) - $start;
        self::$calls[] = (object) compact('start', 'time', 'name', 'workload', 'result');
    }

    /**
     * Get the call information
     * 
     * @return array
     */
    public function getCalls()
    {
        return self::$calls;
    }
}
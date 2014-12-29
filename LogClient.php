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
     *
     * @var \GearmanClient
     */
    protected $client;

    public function __construct()
    {
        $this->client = new \GearmanClient();
    }

    public function addServer($host, $port)
    {
        $start = microtime(true);
        $result = $this->client->addServer($host, $port);
        $this->logCall($start, 'addServer', array($host, $port), $result);

        return $result;
    }

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
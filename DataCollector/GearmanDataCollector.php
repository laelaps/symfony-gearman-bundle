<?php
namespace Laelaps\GearmanBundle\DataCollector;

use \Laelaps\GearmanBundle\LogClient,
    \Symfony\Component\HttpFoundation\Request,
    \Symfony\Component\HttpFoundation\Response,
    \Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * 
 */
class GearmanDataCollector extends DataCollector
{
  /**
   *
   * @var LogClient
   */
  protected $client;

  public function __construct(LogClient $client)
  {
    $this->client = $client;
  }
  
  /**
   * 
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Symfony\Component\HttpFoundation\Response $response
   * @param \Exception $exception
   */
  public function collect(Request $request, Response $response, \Exception $exception = null)
  {
    $this->data = array('memory' => memory_get_peak_usage(true),
                        'calls'  => $this->client->getCalls(),
       );
  }

  /**
   * 
   * @return string
   */
  public function getName()
  {
    return 'gearman';
  }

  /**
   * 
   * @return int
   */
  public function getRequestcount()
  {
    return isset($this->data['calls']) ? count($this->data['calls']) : 0;
  }

  /**
   * Get the total request time
   * 
   * @return float time in ms
   */
  public function getTime()
  {
    if (!$this->getRequestcount()) return 0;
    
    $time = 0;

    foreach ($this->data['calls'] as $request) {
      $time += $request->time;
    }

    return $time;
  }

  /**
   * Return all calls details to Gearman 
   * @return array
   */
  public function getCalls()
  {
    return isset($this->data['calls']) ? $this->data['calls'] : array(); 
  }
}
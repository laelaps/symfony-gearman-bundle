# Symfony Gearman Bundle

## Installation

### composer.json

``` yaml
{
    "require": {
        "laelaps/symfony-gearman-bundle": "1.*@dev"
    }
}
```

### config.yml

We can configure one server for the client. Because only one is used. You need 
load balancer if you would like to share the load over multiple Gearman servers.

We can configure multiple servers for the workers. Because they do look for 
work on all configured Gearman servers.

![Gearman cluster](http://gearman.org/img/cluster.png)

``` yaml
laelaps_gearman:
    client_server: localhost:4730
    worker_servers:
        - localhost:4730
```

### app/AppKernel.php

``` php
<?php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Laelaps\GearmanBundle\LaelapsGearmanBundle(),
        // ...
    );
}
```

## Worker supervisor cron tool

There is a simple supervisor bash script available. For instructions, see:

https://github.com/laelaps/symfony-gearman-bundle/issues/2#issuecomment-16257507

## Examples

### Worker

``` php
<?php

// AcmeDemoBundle\Worker\ExampleWorker.php

namespace AcmeDemoBundle\Worker;

use GearmanJob;
use Laelaps\GearmanBundle\Annotation as Gearman;
use Laelaps\GearmanBundle\Worker;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleWorker extends Worker
{
    /**
     * @Gearman\PointOfEntry(name="example_job_name")
     * @param GearmanJob $job
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return boolean returning false means job failure
     */
    public function doExampleJob(GearmanJob $job, OutputInterface $output)
    {
        // do your job
    }
}
```

### Running worker

Symfony Style Notation
```
$ ./app/console gearman:worker:run AcmeBundle:ExampleWorker
```

_Note that this would look for Acme\Bundle\AcmeBundle\Worker\ExampleWorker_

```
$ ./app/console gearman:worker:run ./src/AcmeDemoBundle/Worker/ExampleWorker.php
```

Wildcard is also available (not recommended but possible - results in single process for multiple workers):

```
$ ./app/console gearman:worker:run "./src/AcmeDemoBundle/Worker/*.php"
```

Runs all workers from all bundles:

```
$ ./app/console gearman:worker:run "./src/*/Worker/*.php"
```

### Calling job from controller

``` php
<?php

class ExampleController
{
    public function exampleAction()
    {
        // job name taken from PointOfEntry annotation
        $this->get('laelaps.gearman.client')->doBackground('example_job_name', $optionalWorkload = '');
    }
}
```

### Calling job from command line

```
$ ./app/console gearman:job:run example_job_name
```

```
$ ./app/console gearman:job:run example_job_name optional_workload_string
```

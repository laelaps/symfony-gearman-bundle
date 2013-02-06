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

### app/AppKernel.php

``` php
<?php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Laelaps\GearmanBundle\GearmanBundle(),
        // ...
    );
}
```

## Examples

### Worker

``` php
<?php

// AcmeDemoBundle\Worker\ExampleWorker.php

use GearmanJob;
use Laelaps\GearmanBundle\Annotation as Gearman;
use Laelaps\GearmanBundle\Worker;
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
    public function registerBundles(GearmanJob $job, OutputInterface $output)
    {
        // do your job
    }
}
```

### Running worker

```
$ ./app/console gearman:worker:run AcmeDemoBundle\Worker\ExampleWorker.php
```

Wildcard is also available:

```
$ ./app/console gearman:worker:run "AcmeDemoBundle\Worker\*.php"
```

### Calling job from controller

``` php
<?php

class ExampleController
{
    public function exampleAction()
    {
        $this->get('laelaps.gearman.client')->doBackground('example_job_name');
    }
}
```

### Calling job from command line

```
$ ./app/console gearman:job:run example_job_name
```

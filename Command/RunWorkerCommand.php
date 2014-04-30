<?php

namespace Laelaps\GearmanBundle\Command;

use GearmanJob;
use GearmanWorker;
use Laelaps\GearmanBundle\Annotation\PointOfEntry as PointOfEntryAnnotation;
use Laelaps\GearmanBundle\Worker;
use ReflectionMethod;
use ReflectionObject;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Mateusz Charytoniuk <mateusz.charytoniuk@absolvent.pl>
 */
class RunWorkerCommand extends ContainerAwareCommand
{
    const ARGUMENT_WORKER_FILENAME = 'filename';
    const GEARMAN_SERVERS_PARAMETER_KEY = 'gearman_servers';
    const WORKER_CLASS_NAME = 'Laelaps\GearmanBundle\Worker';

    /**
     * @var boolean
     */
    protected $shouldStop = false;

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('gearman:worker:run')
            ->setDescription('Run given worker by filename.')
            ->addArgument(self::ARGUMENT_WORKER_FILENAME, InputArgument::REQUIRED, 'Worker filename. Wildcard "*" is allowed.')
        ;
    }

    /**
     * @param Symfony\Component\Console\Input\InputInterface $input
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $gmworker = $container->get('laelaps.gearman.worker');
        $filename = $input->getArgument(self::ARGUMENT_WORKER_FILENAME);

        /**
         * Load controller-style
         */
        if (strpos($filename, ':') !== false) {

            $kernel = $container->get('kernel');
            $class = null;
            list($bundleName, $className) = explode(':',$filename, 2);

            foreach ($kernel->getBundle($bundleName, false) as $bundle) {
                $try = $bundle->getNamespace().'\\Worker\\'.$className;

                if (class_exists($try)) {
                    $class = $try;
                    break;
                }
            }

            if ($class === null) {
                throw new \InvalidArgumentException(sprintf('Could not find worker "%s"', $filename));
            }

            // Good, register worker
            $worker = new $class($container);
            $this->registerWorker($worker, $gmworker, $output);

        /**
         * Load from Glob
         */
        } else {
            $workerFiles = glob($filename);

            if (empty($workerFiles)) {
                throw new RuntimeException(sprintf('No filename matching "%s" glob pattern found.', $workerFilenamePattern));
            }

            foreach ($workerFiles as $workerFilename) {
                $worker = $this->instantiateWorker($workerFilename, $output);
                $this->registerWorker($worker, $gmworker, $output);
            }
        }

        while (!$this->shouldStop && $gmworker->work()) {}
    }

    /**
     * @param string $filename
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return Laelaps\GearmanBundle\Worker
     */
    public function instantiateWorker($filename, OutputInterface $output)
    {
        $className = $this->readWorkerClassName($filename, $output);

        if (!class_exists($className)) {
            // worker is out of autoloader scope
            require_once $filename;
            if (!class_exists($className)) {
                throw new RuntimeException(sprintf('Unable to load class "%s"', $className));
            }
        }

        if (!is_a($className, self::WORKER_CLASS_NAME, $allowString = true)) {
            throw new RuntimeException(sprintf('Worker "%s" must extend "%s" class.', $filename, self::WORKER_CLASS_NAME));
        }

        $container = $this->getContainer();

        return new $className($container);
    }

    /**
     * @param Laelaps\GearmanBundle\Worker $worker
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return array [ point of entry name => callable point of entry ]
     * @throws RuntimeException
     */
    public function readPointsOfEntry(Worker $worker, OutputInterface $output)
    {
        $annotationReader = $this->getContainer()->get('annotation_reader');

        $pointsOfEntry = array();

        $workerReflection = new ReflectionObject($worker);
        $workerReflectionMethods = $workerReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($workerReflectionMethods as $reflectionMethod) {
            foreach ($annotationReader->getMethodAnnotations($reflectionMethod) as $annotation) {
                if ($annotation instanceof PointOfEntryAnnotation) {
                    $pointsOfEntry[$annotation->name] = array($worker, $reflectionMethod->name);
                }
            }
        }

        return $pointsOfEntry;
    }

    /**
     * @param string $filename
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return string|null
     * @throws RuntimeException
     */
    public function readWorkerClassName($filename, OutputInterface $output)
    {
        if (!file_exists($filename)) {
            throw new RuntimeException(sprintf('File "%s" does not exist.', $filename));
        }

        $fileContents = file_get_contents($filename);
        $codeTokens = token_get_all($fileContents);

        foreach ($codeTokens as $codeTokenIndex => $codeTokenValue) {
            if (is_array($codeTokenValue)) {
                list($codeTokenType, $codeTokenContent) = $codeTokenValue;
                if (!isset($className) && T_CLASS === $codeTokenType) {
                    $className = $codeTokens[$codeTokenIndex + 2][1];
                }
                if (!isset($namespace) && T_NAMESPACE === $codeTokenType) {
                    $namespace = '';
                    for ($i = 2; true; ++ $i) {
                        $namespaceToken = $codeTokens[$codeTokenIndex + $i];
                        if (is_array($namespaceToken)) {
                            $namespace .= $namespaceToken[1];
                        }
                        if (is_string($namespaceToken)) {
                            break;
                        }
                    }
                }
            }
            if (isset($className) && isset($namespace)) {
                return implode('\\', array($namespace, $className));
            }
        }

        return null;
    }

    protected function getManager()
    {
        return $this->getContainer()->get('doctrine')->getManager();
    }

    /**
     * @return \Laelaps\GearmanBundle\Entity\Job
     */
    protected function createJob()
    {
        return new \Laelaps\GearmanBundle\Entity\Job();
    }

    /**
     * @param Laelaps\GearmanBundle\Worker $worker
     * @param GearmanWorker $gmworker
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     * @throws RuntimeException
     */
    public function registerWorker(Worker $worker, GearmanWorker $gmworker, OutputInterface $output)
    {
        $pointsOfEntry = $this->readPointsOfEntry($worker, $output);
        if (empty($pointsOfEntry)) {
            throw new RuntimeException(sprintf('No "PointOfEntry" annotations found in public methods of "%s".', get_class($worker)));
        }

        foreach ($pointsOfEntry as $entryPointName => $entryPoint) {
            $gmworker->addFunction($entryPointName, function (GearmanJob $gearmanJob) use ($entryPoint, $entryPointName, $output) {
                gc_enable();

                // Add a new job
                $job = $this->createJob();
                $job->setName($entryPointName);
                $job->setStartTime(new \DateTime());
                $job->setWorkload($gearmanJob->workload());

                $manager = $this->getManager();
                $manager->persist($job);
                $manager->flush($job);

                try {
                    ob_start();
                    $taskReturnStatus = call_user_func_array(array($entryPoint[0], $entryPoint[1]), array($gearmanJob, $output));
                    $output = ob_get_clean();
                } catch (\Exception $e) {
                    $output = ob_get_clean();

                    $job->setErrorOutput($e->getMessage());
                }

                // GOTCHA: null means success
                (false === $taskReturnStatus) ? $gearmanJob->sendFail() : $gearmanJob->sendComplete($taskReturnStatus);

                $job->setEndTime(new \DateTime());
                $job->setReturnStatus($taskReturnStatus);
                $job->setOutput($output);
                $manager->flush($job);

                gc_collect_cycles();
                gc_disable();

                return $taskReturnStatus;
            });

            $entryPointTarget = implode('::', array(get_class($entryPoint[0]), $entryPoint[1]));
            $output->writeln(sprintf('Registered "%s" function pointing to "%s".', $entryPointName, $entryPointTarget));
        }
    }
}

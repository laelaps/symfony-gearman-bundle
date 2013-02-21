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

declare(ticks = 1);

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
        $workerFilenamePattern = $input->getArgument(self::ARGUMENT_WORKER_FILENAME);
        $workerFiles = glob($workerFilenamePattern);
        if (empty($workerFiles)) {
            throw new RuntimeException(sprintf('No filename matching "%s" glob pattern found.', $workerFilenamePattern));
        }

        $gmworker = new GearmanWorker();
        $container = $this->getContainer();
        if ($container->hasParameter(self::GEARMAN_SERVERS_PARAMETER_KEY)) {
            $gmworker->addServers($container->getParameter(self::GEARMAN_SERVERS_PARAMETER_KEY));
        } else {
            // add default server
            $gmworker->addServer();
        }

        foreach ($workerFiles as $workerFilename) {
            $worker = $this->instanciateWorker($workerFilename, $output);
            $this->registerWorker($worker, $gmworker, $output);
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () use ($output) {
                $output->writeln('Registered SIGTERM handle.');

                return $this->onSigterm($output);
            });
        }

        while (!$this->shouldStop && $gmworker->work()) {}
    }

    /**
     * @param string $filename
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return Laelaps\GearmanBundle\Worker
     */
    public function instanciateWorker($filename, OutputInterface $output)
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
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return Laelaps\GearmanBundle\Worker
     */
    public function onSigterm(OutputInterface $output)
    {
        $this->shouldStop = true;

        $output->writeln('SIGTERM caught - trying to exit gracefully.');
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

        $pointsOfEntry = [];

        $workerReflection = new ReflectionObject($worker);
        $workerReflectionMethods = $workerReflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($workerReflectionMethods as $reflectionMethod) {
            foreach ($annotationReader->getMethodAnnotations($reflectionMethod) as $annotation) {
                if ($annotation instanceof PointOfEntryAnnotation) {
                    $pointsOfEntry[$annotation->name] = [$worker, $reflectionMethod->name];
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
                return implode('\\', [$namespace, $className]);
            }
        }

        return null;
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
            $gmworker->addFunction($entryPointName, function (GearmanJob $gearmanJob) use ($entryPoint, $output) {
                gc_enable();

                $taskReturnStatus = $entryPoint($gearmanJob, $output);

                // GOTCHA: null means success
                (false === $taskReturnStatus) ? $gearmanJob->sendFail() : $gearmanJob->sendComplete($taskReturnStatus);

                gc_collect_cycles();
                gc_disable();

                return $taskReturnStatus;
            });

            $entryPointTarget = implode('::', [get_class($entryPoint[0]), $entryPoint[1]]);
            $output->writeln(sprintf('Registered "%s" function pointing to "%s".', $entryPointName, $entryPointTarget));
        }
    }
}

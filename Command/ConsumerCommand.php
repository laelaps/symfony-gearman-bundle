<?php

namespace Laelaps\GearmanBundle\Command;

use Laelaps\GearmanBundle\Worker\HandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumerCommand extends Command
{
    const OPTION_MAX_MESSAGES = 'max-messages';
    const OPTION_MAX_RUNTIME  = 'max-runtime';

    /** @var \GearmanWorker */
    protected $gmworker;
    /** @var HandlerInterface */
    protected $handler;
    /** @var LoggerInterface */
    protected $logger;
    /** @var float */
    protected $timeStop = 0;
    /** @var int the number of message this worker has processed (is processing) */
    protected $messageNumber = 0;
    /** @var int */
    protected $maxNumMessages = 0;
    /** @var string */
    protected $queueName;

    /**
     * ConsumerCommand constructor.
     *
     * @param \GearmanWorker           $gmworker
     * @param HandlerInterface         $handler
     * @param LoggerInterface          $logger
     * @param string                   $queueName       The name of the queue to listen to
     * @param string                   $name            The name of the command
     */
    public function __construct(
        \GearmanWorker $gmworker,
        LoggerInterface $logger,
        HandlerInterface $handler,
        $queueName,
        $name
    ) {
        parent::__construct($name);

        $this->gmworker = $gmworker;
        $this->logger = $logger;
        $this->handler = $handler;
        $this->queueName = $queueName;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Executes a consumer')
            ->addOption(
                static::OPTION_MAX_RUNTIME,
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum time in seconds the consumer will run. Must be less than PHP_INT_MAX / 1000. '.
                    'Pass zero or negative for eternal',
                0
            )
            ->addOption(
                static::OPTION_MAX_MESSAGES,
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of messages that should be consumed. Pass zero or negative for no maximum',
                0
            )
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (($timeout = $input->getOption(static::OPTION_MAX_RUNTIME)) > 0) {
            if (PHP_INT_MAX > $timeout * 1000) {
                // Set the timeout of the gearman connection.
                $this->timeStop = microtime(true) + $timeout;
                $this->gmworker->setTimeout((int) ($timeout * 1000));
            } else {
                $this->logger->warning('Timeout is too large, no timeout has been configured');
            }
        } else {
            $this->timeStop = 0;
        }

        $this->maxNumMessages = (int) $input->getOption(static::OPTION_MAX_MESSAGES);
        $this->messageNumber = 0;

        // Set worker to non-blocking to allow for better end-of-time and max-message handling.
        $this->gmworker->addOptions(GEARMAN_WORKER_NON_BLOCKING);

        // Add the handle job method
        $this->gmworker->addFunction($this->queueName, function (\GearmanJob $gearmanJob) {
            ++$this->messageNumber;

            $this->logger->debug('Start with job', ['message-number' => $this->messageNumber]);

            $success = $this->handleJob($gearmanJob);

            $this->logger->debug('Done with job', ['message-number' => $this->messageNumber]);

            return true; // Always return true; stopping is handled in the loop itself.
        });

        $this->logger->info('Consumer: Registered worker', [
            'queue' => $this->queueName,
            'max-messages' => $this->maxNumMessages,
            'max-runtime' => $timeout,
        ]);

        try {
            while ($this->gmworker->work()
                || $this->gmworker->returnCode() === GEARMAN_IO_WAIT
                || $this->gmworker->returnCode() === GEARMAN_NO_JOBS
            ) {
                if ($this->gmworker->returnCode() !== GEARMAN_SUCCESS) {
                    // No job received? Wait for action from server.
                    $this->gmworker->wait();
                }

                if ($this->shouldStop()) {
                    $this->logger->debug('Stopped working');
                    break;
                }
            }

            return 0;
        } finally {
            // Always log unregistering. Even if an exception was thrown.
            $this->logger->info('Consumer: Unregistered worker', ['queue' => $this->queueName]);
        }
    }

    /**
     * Handles $gearmanJob.
     *
     * @param \GearmanJob $gearmanJob
     *
     * @return bool
     */
    protected function handleJob(\GearmanJob $gearmanJob)
    {
        gc_enable();

        $taskReturnStatus = call_user_func_array([$this->handler, 'handle'], [$gearmanJob->workload()]);

        // GOTCHA: null means success
        // TODO: http://stackoverflow.com/questions/5143755/gearman-php-proper-way-for-a-worker-to-send-back-a-failure
        // TODO: at the moment $taskReturnStatus is true/false, but maybe the response can be returned this way as well
        (false === $taskReturnStatus) ? $gearmanJob->sendFail() : $gearmanJob->sendComplete($taskReturnStatus);

        gc_collect_cycles();
        gc_disable();

        return $taskReturnStatus;
    }

    /**
     * @return bool
     */
    protected function shouldStop()
    {
        if (0 < $this->timeStop && microtime(true) > $this->timeStop) {
            $this->logger->debug('Reached end of time');

            return true;
        }

        if ($this->maxNumMessages > 0 && $this->messageNumber > $this->maxNumMessages) {
            $this->logger->debug('Reached maximum number of messages to handle');

            return true;
        }

        return false;
    }
}

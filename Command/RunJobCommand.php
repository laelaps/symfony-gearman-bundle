<?php

namespace Laelaps\GearmanBundle\Command;

use Laelaps\GearmanBundle\Worker;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Mateusz Charytoniuk <mateusz.charytoniuk@absolvent.pl>
 */
class RunJobCommand extends ContainerAwareCommand
{
    const ARGUMENT_JOB_NAME = 'name';
    const ARGUMENT_JOB_DATA = 'data';
    const WORKER_CLASS_NAME = 'Laelaps\GearmanBundle\Worker';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('gearman:job:run')
            ->setDescription('Run given gearman job.')
            ->addArgument(self::ARGUMENT_JOB_NAME, InputArgument::REQUIRED)
            ->addArgument(self::ARGUMENT_JOB_DATA, InputArgument::OPTIONAL)
        ;
    }

    /**
     * @param Symfony\Component\Console\Input\InputInterface $input
     * @param Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     * @throws RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobName = $input->getArgument(self::ARGUMENT_JOB_NAME);
        $jobData = $input->getArgument(self::ARGUMENT_JOB_DATA);

        $gmclient = $this->getContainer()->get('laelaps.gearman.client');
        $jobHandle = $gmclient->doBackground($jobName, $jobData);

        $returnCode = $gmclient->returnCode();
        if (GEARMAN_SUCCESS !== $returnCode) {
            throw new RuntimeException(sprintf('Gearman server returned non-success code "%"', $returnCode));
        }
    }
}

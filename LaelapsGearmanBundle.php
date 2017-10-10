<?php

namespace Laelaps\GearmanBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Laelaps\GearmanBundle\DependencyInjection\Compiler\AddWorkerHandlerCompilerPass;

/**
 * @author Mateusz Charytoniuk <mateusz.charytoniuk@absolvent.pl>
 */
class LaelapsGearmanBundle extends Bundle {
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddWorkerHandlerCompilerPass());
    }
}

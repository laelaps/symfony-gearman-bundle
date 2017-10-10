<?php

namespace Laelaps\GearmanBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class AddWorkerHandlerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $abstractConsumerDefinition = $container->getDefinition('laelaps.command.abstract_consumer');
        $taggedServices = $container->findTaggedServiceIds('laelaps.handler');

        foreach ($taggedServices as $serviceId => $tags) {
            foreach ($tags as $attributes) {

                if (!isset($attributes['queue_name'])) {
                    throw new InvalidArgumentException('"queue_name" attribute is required for tag "laelaps.handler"');
                }
                $queueName = $attributes['queue_name'];
                if (substr($queueName, 0, 1) === '%' && substr($queueName, -1, 1) === '%') {
                    $queueName = $container->getParameter(substr($queueName,1,-1));
                }

                $newConsumerDefinition = clone $abstractConsumerDefinition;
                $newConsumerDefinition->setAbstract(false);
                $newConsumerDefinition->addArgument(new Reference($serviceId));
                $newConsumerDefinition->addArgument($queueName);
                $newConsumerDefinition->addArgument(sprintf("gearman:consumer:%s", $queueName));
                $newConsumerDefinition->addTag('console.command');

                // add definition
                $definitionId = sprintf("laelaps.command.%s", $queueName);
                $container->setDefinition($definitionId, $newConsumerDefinition);

                // add definition as a console command
                $consoleCommands = $container->getParameter('console.command.ids');
                $consoleCommands[] = $definitionId;
                $container->setParameter('console.command.ids', $consoleCommands);
            }
        }
    }
}

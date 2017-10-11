<?php
namespace Laelaps\GearmanBundle\DependencyInjection;

use \Symfony\Component\Config\FileLocator,
  \Symfony\Component\DependencyInjection\ContainerBuilder,
  \Symfony\Component\DependencyInjection\Loader\YamlFileLoader,
  \Symfony\Component\DependencyInjection\Reference,
  \Symfony\Component\HttpKernel\DependencyInjection\Extension;

class LaelapsGearmanExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        /**
         * Load definitions
         */
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        if ($container->getParameter('kernel.debug')) {
            $loader->load('debug.yml');
        }
        
        $clientServer = $config['client_server'];
        $workerServers = $config['worker_servers'];
        $container->setParameter('laelaps_gearman.client_server', $clientServer);
        $container->setParameter('laelaps_gearman.worker_servers', $workerServers);

        $gearmanClientDefinition = $container->getDefinition('laelaps.gearman.client');
        $gearmanWorkerDefinition = $container->getDefinition('laelaps.gearman.worker');
        list($host, $port) = explode(':', $clientServer);
        $gearmanClientDefinition->addMethodCall('addServer', array($host, $port));
        
        foreach ($workerServers as $server) {
            list($host, $port) = explode(':', $server);
            $gearmanWorkerDefinition->addMethodCall('addServer', array($host, $port));
        }
    }
}

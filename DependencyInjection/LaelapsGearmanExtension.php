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
        
        $servers = $config['servers'];
        $container->setParameter('laelaps_gearman.servers', $servers);

        /**
         * Build container, if array, add multiple servers, if string add one
         */
        $gearmanClientDefinition = $container->getDefinition('laelaps.gearman.client');
        $gearmanWorkerDefinition = $container->getDefinition('laelaps.gearman.worker');

        if (is_array($servers)) {
            foreach ($servers as $server) {
                list($host, $port) = explode(':', $server);
                $gearmanClientDefinition->addMethodCall('addServer', array($host, $port));
                $gearmanWorkerDefinition->addMethodCall('addServer', array($host, $port));
            }
        } else {
            $gearmanClientDefinition->addMethodCall('addServers', array($servers));
            $gearmanWorkerDefinition->addMethodCall('addServers', array($servers));
        }
    }
}

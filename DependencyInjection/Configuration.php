<?php

namespace Laelaps\GearmanBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('laelaps_gearman');

        $this->addClientServer($rootNode);
        $this->addWorkerServers($rootNode);

        return $treeBuilder;
    }
    
    /**
     * Add client server. Client will only connect to on server
     * 
     * laelaps_gearman:
     *     client_server: <host:port>
     * 
     * @param ArrayNodeDefinition $node
     */
    private function addClientServer(ArrayNodeDefinition $node)
    {
        $node->children()->scalarNode('client_server')->isRequired()->cannotBeEmpty()->end();
    }
    
    /**
     * Adds worker servers. Workers can connect to more then one server.
     * 
     * laelaps_gearman:
     *     worker_servers:
     *       - <host1:port>
     *       - <host2:port>
     * 
     * @param ArrayNodeDefinition $node
     */
    private function addWorkerServers(ArrayNodeDefinition $node)
    {
        $node->children()->arrayNode('worker_servers')->prototype('scalar')->isRequired()->cannotBeEmpty()->end();
    }
}

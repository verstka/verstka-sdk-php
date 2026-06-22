<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('verstka');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('api_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('api_secret')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('callback_url')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('api_url')->defaultValue('https://api.r2.verstka.org/integration')->end()
                ->scalarNode('basic_auth_user')->defaultNull()->end()
                ->scalarNode('basic_auth_password')->defaultNull()->end()
                ->integerNode('max_content_size')->defaultValue(104857600)->min(1)->end()
                ->floatNode('request_timeout')->defaultValue(60.0)->min(0.0)->end()
                ->floatNode('download_timeout')->defaultValue(120.0)->min(0.0)->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->scalarNode('callback_route_prefix')->defaultValue('/verstka')->end()
            ->end();

        return $treeBuilder;
    }
}

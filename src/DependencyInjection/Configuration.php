<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('xiidea_easy_img_proxy');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Hex-encoded key for signing URLs')
                ->end()
                ->scalarNode('salt')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Hex-encoded salt for signing URLs')
                ->end()
                ->scalarNode('base_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Base URL of the imgproxy server')
                ->end()
                ->arrayNode('presets')
                    ->info('Custom preset configurations')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('options')
                                ->info('Processing options for this preset')
                                ->useAttributeAsKey('key')
                                ->variablePrototype()->end()
                            ->end()
                            ->scalarNode('extension')
                                ->info('Output format for this preset')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

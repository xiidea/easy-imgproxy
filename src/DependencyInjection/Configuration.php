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
                ->booleanNode('presets_only')
                    ->defaultFalse()
                    ->info('When true, matches IMGPROXY_ONLY_PRESETS=true on the server. Only server presets are allowed and URL uses /{preset1}:{preset2}/ format.')
                ->end()
                ->booleanNode('enable_pro')
                    ->defaultFalse()
                    ->info('Enable imgproxy Pro processing options. When false, pro options are silently ignored with a warning.')
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

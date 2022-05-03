<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoImageAlternatives\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('contao_image_alternatives');
        $treeBuilder
            ->getRootNode()
            ->children()
                ->arrayNode('alternatives')
                    ->info('The available image alternatives.')
                    ->example(['tablet', 'mobile'])
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('sizes')
                    ->info('Allows to define the usage of alternatives for existing contao.image.sizes entries.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('items')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('media')
                                            ->info('The media query this item applies too (needs to be the same as in the contao.image.sizes entry).')
                                        ->end()
                                        ->scalarNode('alternative')
                                            ->info('Defines which image alternative should automatically be used for this item.')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}

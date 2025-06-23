<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
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
                    ->info('Allows to define the usage of alternatives for existing contao.image.sizes entries. Also allows you to set whether to pre-crop images to the important part.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('pre_crop')
                                ->info('Pre-crops the image to the important part before processing the rest of the pipeline.')
                            ->end()
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

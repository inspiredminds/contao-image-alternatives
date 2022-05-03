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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContaoImageAlternativesExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config')))->load('services.yaml');

        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('contao_image_alternatives.alternatives', $config['alternatives']);
        $container->setParameter('contao_image_alternatives.alternative_sizes', $this->processImageSizes($config['sizes'] ?? []));
    }

    public function prepend(ContainerBuilder $container): void
    {
        $contaoConfigs = $container->getExtensionConfig('contao');

        $sizes = [];

        foreach ($contaoConfigs as $config) {
            if (!isset($config['image']['sizes'])) {
                continue;
            }

            $sizes = array_merge($sizes, $config['image']['sizes']);
        }

        $container->setParameter('contao_image_alternatives.predefined_sizes', $this->processImageSizes($sizes));
    }

    private function processImageSizes(array $sizes): array
    {
        $imageSizes = [];

        foreach ($sizes as $name => $value) {
            $imageSizes['_'.$name] = $this->camelizeKeys($value);
        }

        return $imageSizes;
    }

    /**
     * Camelizes keys so "resize_mode" becomes "resizeMode".
     */
    private function camelizeKeys(array $config): array
    {
        $keys = array_keys($config);

        foreach ($keys as &$key) {
            if (\is_array($config[$key])) {
                $config[$key] = $this->camelizeKeys($config[$key]);
            }

            if (\is_string($key)) {
                $key = lcfirst(Container::camelize($key));
            }
        }

        unset($key);

        return array_combine($keys, $config);
    }
}

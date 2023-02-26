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

use Contao\CoreBundle\Controller\PreviewLinkController;
use Contao\CoreBundle\DependencyInjection\Configuration as ContaoConfig;
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
        if (class_exists(PreviewLinkController::class)) {
            $contaoConfig = new ContaoConfig((string) $container->getParameter('kernel.project_dir'));
        } else {
            $contaoConfig = new ContaoConfig((string) $container->getParameter('kernel.project_dir'), (string) $container->getParameter('kernel.default_locale'));
        }

        $config = $this->processConfiguration($contaoConfig, $container->getExtensionConfig('contao'));

        $imageSizes = [];

        // Do not add a size with the special name "_defaults" but merge its values into all other definitions instead.
        foreach ($config['image']['sizes'] as $name => $value) {
            if ('_defaults' === $name) {
                continue;
            }

            if (isset($config['image']['sizes']['_defaults'])) {
                // Make sure that arrays defined under _defaults will take precedence over empty arrays (see #2783)
                $value = array_merge(
                    $config['image']['sizes']['_defaults'],
                    array_filter($value, static fn ($v) => !\is_array($v) || !empty($v))
                );
            }

            $imageSizes[$name] = $value;
        }

        $container->setParameter('contao_image_alternatives.predefined_sizes', $this->processImageSizes($imageSizes));
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

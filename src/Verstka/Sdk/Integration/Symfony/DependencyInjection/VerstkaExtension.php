<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Verstka\Sdk\Config\VerstkaConfig;

final class VerstkaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('verstka.callback_route_prefix', $config['callback_route_prefix']);

        $container->register(VerstkaConfig::class)
            ->setAutowired(false)
            ->setAutoconfigured(false)
            ->setArguments([
                $config['api_key'],
                $config['api_secret'],
                $config['callback_url'],
                $config['api_url'],
                $config['max_content_size'],
                $config['request_timeout'],
                $config['download_timeout'],
                $config['debug'],
            ]);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'verstka';
    }
}

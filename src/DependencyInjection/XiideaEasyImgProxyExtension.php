<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Xiidea\EasyImgProxyBundle\Preset\Preset;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;

class XiideaEasyImgProxyExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('xiidea_easy_img_proxy.key', $config['key']);
        $container->setParameter('xiidea_easy_img_proxy.salt', $config['salt']);
        $container->setParameter('xiidea_easy_img_proxy.base_url', $config['base_url']);
        $container->setParameter('xiidea_easy_img_proxy.presets_only', $config['presets_only']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        // Build and register presets
        $registry = $container->getDefinition(PresetRegistry::class);
        foreach ($config['presets'] ?? [] as $presetName => $presetConfig) {
            $preset = new Preset(
                $presetConfig['options'] ?? [],
                $presetConfig['extension'] ?? null
            );
            $registry->addMethodCall('register', [$presetName, $preset]);
        }
    }
}

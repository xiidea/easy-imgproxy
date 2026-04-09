<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;
use Xiidea\EasyImgProxyBundle\Service\ImgProxyUrlGenerator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(PresetRegistry::class);

    $services->set(ImgProxyUrlGenerator::class)
        ->args([
            param('xiidea_easy_img_proxy.key'),
            param('xiidea_easy_img_proxy.salt'),
            param('xiidea_easy_img_proxy.base_url'),
            service(PresetRegistry::class)->nullOnInvalid(),
            param('xiidea_easy_img_proxy.presets_only'),
        ]);
};

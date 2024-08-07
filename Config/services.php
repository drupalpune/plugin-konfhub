<?php

declare(strict_types=1);

use MauticPlugin\MailjetBundle\Mailer\Factory\MailjetTransportFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('MauticPlugin\\KonfhubBundle\\', '../')
        ->exclude('../{Config}');
};

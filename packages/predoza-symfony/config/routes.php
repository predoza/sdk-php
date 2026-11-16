<?php

declare(strict_types=1);

use Predoza\Symfony\Controller\HandoffController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('predoza_handoff', '/predoza/handoff')
        ->controller(HandoffController::class)
        ->methods(['POST']);
};

<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Php80\Rector\If_\NullsafeOperatorRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // get parameters
    $parameters = $containerConfigurator->parameters();

    // Define what rule sets will be applied
    $parameters->set(Option::SETS, [
        SetList::DEAD_CODE,
    ]);

    $containerConfigurator->import(SetList::PHP_80);

    // get services (needed for register a single rule)
     $services = $containerConfigurator->services();

    // register a single rule
     $services->set(NullsafeOperatorRector::class);
};

<?php

namespace Aster255\FilamentLogger\Loggers;

class ResourceLogger extends AbstractModelLogger
{
    protected function getLogName(): string
    {
        return config('filament-logger.resources.log_name');
    }
}

<?php

namespace NSWDPC\Embed\Services;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Simple log handling
 */
class Logger
{
    public static function log(string $message, string $level = "DEBUG")
    {
        Injector::inst()->get(LoggerInterface::class)->log($level, $message);
    }
}

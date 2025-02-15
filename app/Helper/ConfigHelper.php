<?php

namespace App\Helper;

class ConfigHelper
{
    public static function isAiServiceManual(): bool
    {
        return config('ai-services.mode') === 'manual';
    }
}

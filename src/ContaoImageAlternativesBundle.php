<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoImageAlternatives;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ContaoImageAlternativesBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}

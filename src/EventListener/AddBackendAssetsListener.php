<?php

namespace InspiredMinds\ContaoImageAlternatives\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener]
class AddBackendAssetsListener
{
    public function __construct(private readonly ScopeMatcher $scopeMatcher)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->scopeMatcher->isBackendMainRequest($event)) {
            return;
        }

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoimagealternatives/importantParts.js|static|async';
        $GLOBALS['TL_CSS'][] = 'bundles/contaoimagealternatives/backend.css|static';
    }
}

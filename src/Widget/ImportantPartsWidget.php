<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoImageAlternatives\Widget;

use Contao\Controller;
use Contao\Widget;

class ImportantPartsWidget extends Widget
{
    protected $blnSubmitInput = true;
    protected $blnForAttribute = true;
    protected $strTemplate = 'be_importantParts';

    public function generate(): string
    {
        return sprintf(
            '<div id="ctrl_%s" class="tl_text_field%s">%s</div>%s',
            $this->strId,
            ($this->strClass ? ' '.$this->strClass : ''),
            'Hello World!',
            $this->wizard
        );
    }

    public function getImageData(): array
    {
        $template = (object) [];
        Controller::addImageToTemplate($template, [
            'singleSRC' => $this->objDca->id,
            'size' => [699, 524, 'box'],
        ]);
        $template->floatClass .= ' tl_edit_preview';

        return (array) $template;
    }
}

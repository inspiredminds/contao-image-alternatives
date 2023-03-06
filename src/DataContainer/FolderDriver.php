<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoImageAlternatives\DataContainer;

use Contao\BackendTemplate;
use Contao\DC_Folder;
use Contao\System;
use InspiredMinds\ContaoFileUsage\DataContainer\FolderDataContainer;

if (class_exists(FolderDataContainer::class)) {
    class FolderParent extends FolderDataContainer
    {
    }
} else {
    class FolderParent extends DC_Folder
    {
    }
}

class FolderDriver extends FolderParent
{
    protected function row($palette = null)
    {
        $row = parent::row($palette);

        if ('name' === $this->strField && null !== $this->objActiveRecord && 'file' === $this->objActiveRecord->type) {
            $row = preg_replace('~<script>Backend\\.editPreviewWizard\\(\\$\\(\'ctrl_preview_[a-z0-9]+\'\\)\\);</script>~', '', $row, -1, $count);

            if ($count > 0) {
                $template = new BackendTemplate('be_importantPartSwitch');
                $template->alternatives = System::getContainer()->getParameter('contao_image_alternatives.alternatives');
                $row = $template->parse().$row;

                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoimagealternatives/importantParts.js|static|async';
                $GLOBALS['TL_CSS'][] = 'bundles/contaoimagealternatives/backend.css|static';
            }
        }

        return $row;
    }
}

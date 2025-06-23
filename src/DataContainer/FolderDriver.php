<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
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
            }
        }

        return $row;
    }
}

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

use Contao\DC_Folder;
use Contao\System;

class FolderDriver extends DC_Folder
{
    protected function row($palette = null)
    {
        $row = parent::row($palette);

        if ('name' === $this->strField && null !== $this->objActiveRecord && 'file' === $this->objActiveRecord->type) {
            $row = $row = '<style>.tl_edit_preview_important_part { user-select: none; touch-action: none; }</style>'.$row;
            $row = preg_replace('~<script>Backend\\.editPreviewWizard\\(\\$\\(\'ctrl_preview_[a-z0-9]+\'\\)\\);</script>~', '', $row, -1, $count);

            if ($count > 0) {
                $translator = System::getContainer()->get('translator');
                $alternatives = System::getContainer()->getParameter('contao_image_alternatives.alternatives');
                $select = '<div class="widget" style="width: 355px"><select class="tl_select" name="alternative-selection"><option value="default">Default</option>';
    
                foreach ($alternatives as $alternative) {
                    $select .= '<option value="'.$alternative.'>'.$translator->trans($alternative, [], 'image_alternatives').'</option>';
                }
    
                $select .= '</select></div>';
    
                $row = $select . $row;

                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoimagealternatives/interact.min.js|static';
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoimagealternatives/importantParts.js|static|async';
                $GLOBALS['TL_CSS'][] = 'bundles/contaoimagealternatives/backend.css|static';
            }
        }

        return $row;
    }
}

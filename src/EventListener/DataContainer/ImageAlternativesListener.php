<?php

declare(strict_types=1);

/*
 * This file is part of the Contao Image Alternatives extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoImageAlternatives\EventListener\DataContainer;

use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImageAlternativesListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly array $alternatives,
        private readonly string $projectDir,
        private readonly array $validExtensions,
    ) {
    }

    #[AsCallback('tl_files', 'config.onload')]
    public function adjustDataContainer(DataContainer $dc): void
    {
        if ([] === $this->alternatives) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act')) {
            return;
        }

        if (!file_exists(Path::join($this->projectDir, $dc->id))) {
            return;
        }

        $file = Dbafs::addResource($dc->id);

        if (null === $file) {
            return;
        }

        if ('folder' === $file->type || !\in_array($file->extension, $this->validExtensions, true)) {
            return;
        }

        $pm = PaletteManipulator::create();
        $pm->addLegend('image_alternatives', null);

        foreach ($this->alternatives as $alternative) {
            $fieldName = 'alternative_'.StringUtil::standardize($alternative);
            $GLOBALS['TL_DCA']['tl_files']['fields'][$fieldName] = [
                'label' => [$this->translator->trans($alternative, [], 'image_alternatives'), $this->translator->trans('alternative_description', ['%alternative%' => $alternative], 'ContaoImageAlternativesBundle')],
                'inputType' => 'fileTree',
                'eval' => [
                    'extensions' => Config::get('validImageTypes'),
                    'filesOnly' => true,
                    'fieldType' => 'radio',
                    'tl_class' => 'clr',
                    'doNotSaveEmpty' => true,
                ],
                'load_callback' => [['contao_image_alternatives.data_container.image_alternatives', 'alternativeLoadCallback']],
                'save_callback' => [['contao_image_alternatives.data_container.image_alternatives', 'alternativeSaveCallback']],
                'alternativeName' => $alternative,
            ];

            $pm->addField($fieldName, 'image_alternatives', PaletteManipulator::POSITION_APPEND);
        }

        $pm->applyToPalette('default', 'tl_files');
    }

    public function alternativeLoadCallback(mixed $value, DataContainer $dc): string|null
    {
        $file = FilesModel::findByPath($dc->id);

        $alternatives = StringUtil::deserialize($file->alternatives, true);

        $alternativeName = $GLOBALS['TL_DCA']['tl_files']['fields'][$dc->field]['alternativeName'];

        return $alternatives[$alternativeName] ?? null;
    }

    public function alternativeSaveCallback(mixed $value, DataContainer $dc): string|null
    {
        $file = FilesModel::findByPath($dc->id);

        $alternativeName = $GLOBALS['TL_DCA']['tl_files']['fields'][$dc->field]['alternativeName'];

        $alternatives = StringUtil::deserialize($file->alternatives, true);
        $alternatives[$alternativeName] = $value;

        $file->alternatives = serialize($alternatives);
        $file->save();

        return null;
    }

    #[AsCallback('tl_image_size_item', 'fields.alternative.options')]
    public function alternativeOptionsCallback(): array
    {
        $options = [];

        foreach ($this->alternatives as $alternative) {
            $options[$alternative] = $this->translator->trans($alternative, [], 'image_alternatives');
        }

        return $options;
    }

    #[AsCallback('tl_image_size_item', 'list.sorting.child_record')]
    public function imageSizeItemChildRecordCallback(array $row): string
    {
        $original = (new \tl_image_size_item())->listImageSizeItem($row);

        if ($row['alternative']) {
            $alternative = $this->translator->trans($row['alternative'], [], 'image_alternatives');
            $original = str_replace('</div>', ' <span style="color:#999;padding-left:3px">['.$alternative.']</span></div>', $original);
        }

        return $original;
    }
}

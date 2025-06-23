<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoImageAlternatives\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Dbafs;
use Contao\FilesModel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportantPartsListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $projectDir,
        private readonly array $validExtensions,
    ) {
    }

    #[AsCallback('tl_files', 'config.onload')]
    public function adjustPalettes(DataContainer $dc): void
    {
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

        if ('file' !== $file->type || !\in_array($file->extension, $this->validExtensions, true)) {
            return;
        }

        PaletteManipulator::create()
            ->addField('importantParts', null)
            ->applyToPalette('default', 'tl_files')
        ;
    }

    #[AsCallback('tl_files', 'fields.importantParts.load')]
    public function importantPartsLoadCallback($value, DataContainer $dc): string
    {
        try {
            $importantParts = $value ? json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            $importantParts = [];
        }

        $file = FilesModel::findByPath($dc->id);

        if ($file->importantPartWidth > 0 && $file->importantPartHeight > 0) {
            $importantParts['default'] = [
                'x' => $file->importantPartX,
                'y' => $file->importantPartY,
                'width' => $file->importantPartWidth,
                'height' => $file->importantPartHeight,
            ];
        }

        return json_encode((object) $importantParts, JSON_PRETTY_PRINT);
    }

    #[AsCallback('tl_files', 'fields.importantParts.save')]
    public function importantPartsSaveCallback($value, DataContainer $dc): string
    {
        try {
            $importantParts = $value ? json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\JsonException) {
            $importantParts = [];
        }

        $file = FilesModel::findByPath($dc->id);

        $file->importantPartX = $importantParts['default']['x'] ?? 0;
        $file->importantPartY = $importantParts['default']['y'] ?? 0;
        $file->importantPartWidth = $importantParts['default']['width'] ?? 0;
        $file->importantPartHeight = $importantParts['default']['height'] ?? 0;

        $file->save();

        // Remove any invalid entries
        $importantParts = array_filter($importantParts, static fn (array $importantPart): bool => (float) $importantPart['width'] > 0 && (float) $importantPart['height'] > 0);

        // "compress" JSON
        return json_encode((object) $importantParts, JSON_THROW_ON_ERROR);
    }
}

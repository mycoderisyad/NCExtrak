<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Service;

use OCA\NCExtrak\Exception\UnsupportedFormatException;
use OCA\NCExtrak\Extractor\ExtractorInterface;
use OCA\NCExtrak\Extractor\RarExtractor;
use OCA\NCExtrak\Extractor\SevenZipExtractor;
use OCA\NCExtrak\Extractor\TarExtractor;
use OCA\NCExtrak\Extractor\ZipExtractor;

class ExtractorRegistry
{
    /** @var list<ExtractorInterface> */
    private array $extractors;

    /**
     * @param list<ExtractorInterface>|null $extractors
     */
    public function __construct(?array $extractors = null)
    {
        $this->extractors = $extractors ?? [
            new ZipExtractor(),
            new TarExtractor(),
            new RarExtractor(),
            new SevenZipExtractor(),
        ];
    }

    public function getExtractor(string $format): ExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if (!$extractor->isAvailable()) {
                continue;
            }

            if (in_array($format, $extractor->getFormats(), true)) {
                return $extractor;
            }
        }

        throw new UnsupportedFormatException(sprintf('Unsupported archive format: %s', $format));
    }

    /**
     * @return list<string>
     */
    public function getSupportedFormats(): array
    {
        $formats = [];
        foreach ($this->extractors as $extractor) {
            if (!$extractor->isAvailable()) {
                continue;
            }
            $formats = array_merge($formats, $extractor->getFormats());
        }

        return array_values(array_unique($formats));
    }
}

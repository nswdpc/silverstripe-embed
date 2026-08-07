<?php

namespace NSWDPC\Embed\Services;

use NSWDPC\Embed\Extensions\Embeddable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\XssSanitiser;
use SilverStripe\ORM\DataObject;

class HTMLFilter
{
    use Injectable;

    public function sanitiseHtml(string $embedHtml, array $elementsToRemove = []): string
    {
        try {
            if ($embedHtml === '') {
                return '';
            } else {
                $sanitiser = XssSanitiser::create();
                if ($elementsToRemove !== []) {
                    $sanitiser = $sanitiser->setElementsToRemove($elementsToRemove);
                }

                return $sanitiser->sanitiseString($embedHtml);
            }
        } catch (\Exception $exception) {
            Logger::log("Failed to sanitise: {$exception->getMessage()}", "INFO");
            return '';
        }
    }

    /**
     * Get HTML from a DataObject that has Embeddable as an extension
     */
    public function getHtmlFromEmbeddable(object $embeddable, array $elementsToRemove = []): string
    {
        if (($embeddable instanceof DataObject) && $embeddable->hasExtension(Embeddable::class)) {
            return $this->sanitiseHtml(($embeddable->getField('EmbedHTML') ?? ''), $elementsToRemove);
        } else {
            return '';
        }
    }

}

<?php

namespace NSWDPC\Embed\Models;

use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use NSWDPC\Embed\Extensions\Embeddable;

/**
 * Embed
 **/
class Embed extends DataObject
{
    /**
     * @inheritdoc
     */
    private static string $table_name = 'Embed';

    /**
     * @inheritdoc
     */
    private static string $singular_name = 'Embed';

    /**
     * @inheritdoc
     */
    private static string $plural_name = 'Embed';

    /**
     * @inheritdoc
     */
    private static array $summary_fields = [
        'EmbedTitle' => 'Title',
        'EmbedType' => 'Type',
        'EmbedSourceURL' => 'URL'
    ];

    /**
     * @inheritdoc
     */
    private static array $extensions = [
        Embeddable::class
    ];

    /**
     * List the allowed included embed types.  If empty all are allowed.
     */
    private static array $allowed_embed_types = [];

    /**
     * Defines upload folder for embedded assets
     */
    private static string $embed_folder = 'Embed';

    /**
     * @inheritdoc
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(
            TabSet::create(
                "Root",
                Tab::create("Main")
            )
            ->setTitle(_t(self::class. '.TABMAIN', "Main"))
        );
        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        return $this->EmbedTitle;
    }

    /**
     * Set CSS classes for templates
     * See Embeddable::getEmbedClass()
     * @param string $class CSS classes
     */
    public function setClass(string $class): self
    {
        $this->setEmbedClass($class);
        return $this;
    }

    /**
     * Returns the classes for this embed.
     * See Embeddable::getEmbedClass()
     */
    public function getClass(): string
    {
        return $this->getEmbedClass();
    }

    /**
     * Renders an HTML anchor tag for this link
     * See Embeddable::getEmbed()
     */
    public function forTemplate()
    {
        return $this->getEmbed();
    }
}

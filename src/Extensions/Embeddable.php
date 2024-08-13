<?php

namespace NSWDPC\Embed\Extensions;

use Embed\Embed;
use Embed\Extractor;
use Embed\OEmbed;
use NSWDPC\Embed\Services\Logger;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\HTML;
use SilverStripe\View\SSViewer;

/**
 * Embeddable extension for Silverstripe DataObject
 */
class Embeddable extends DataExtension
{
    public const EMBED_TYPE_VIDEO = 'video';
    public const EMBED_TYPE_RICH = 'rich';
    public const EMBED_TYPE_IMAGE = 'image';
    public const EMBED_TYPE_PHOTO = 'photo';
    public const EMBED_TYPE_PICTURE = 'picture';
    public const EMBED_TYPE_LINK = 'link';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'EmbedTitle' => 'Varchar(255)',
        'EmbedType' => 'Varchar',
        'EmbedSourceURL' => 'Varchar(255)',
        'EmbedSourceImageURL' => 'Varchar(255)',
        'EmbedHTML' => 'HTMLText',
        'EmbedWidth' => 'Varchar',
        'EmbedHeight' => 'Varchar',
        'EmbedAspectRatio' => 'Varchar',
        'EmbedDescription' => 'HTMLText'
    ];

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'EmbedImage' => Image::class
    ];

    /**
     * @inheritdoc
     */
    private static array $owns = [
        'EmbedImage'
    ];

    /**
     * Defines tab to insert the embed fields into.
     */
    private static string $embed_tab = 'Main';

    /**
     * List of custom CSS classes for template.
     */
    protected array $classes = [];

    /**
     * Defines the template to render the embed in.
     */
    protected string $embedTemplate = 'NSWDPC/Embed/Models/Embed';

    /**
     * @inheritdoc
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $tab = $owner->config()->get('embed_tab');
        $tab = is_string($tab) ? $tab : 'Main';

        // Ensure these fields don't get added by fields scaffold
        $fields->removeByName([
            'EmbedTitle',
            'EmbedType',
            'EmbedSourceURL',
            'EmbedSourceImageURL',
            'EmbedHTML',
            'EmbedWidth',
            'EmbedHeight',
            'EmbedAspectRatio',
            'EmbedDescription',
            'EmbedImage'
        ]);

        $fields->addFieldsToTab(
            'Root.' . $tab,
            [
                TextField::create(
                    'EmbedTitle',
                    _t(self::class . '.TITLELABEL', 'Title')
                )
                ->setDescription(
                    _t(self::class . '.TITLEDESCRIPTION', 'Optional. Will be auto-generated if left blank')
                ),
                CompositeField::create(
                    TextField::create(
                        'EmbedSourceURL',
                        _t(self::class . '.SOURCEURLLABEL', 'Source URL')
                    )
                    ->setDescription(
                        _t(self::class . '.SOURCEURLDESCRIPTION', 'Specify an external URL')
                    ),
                    CheckboxField::create(
                        'ForceUpdate',
                        _t(self::class . '.FORCEUPDATE', 'Check to update the meta data for this URL')
                    )
                ),
                UploadField::create(
                    'EmbedImage',
                    _t(self::class . '.IMAGELABEL', 'Image')
                )
                ->setFolderName($owner->EmbedFolder)
                ->setAllowedExtensions(['jpg','png','gif']),
                TextareaField::create(
                    'EmbedDescription',
                    _t(self::class . '.DESCRIPTIONLABEL', 'Description')
                )
            ]
        );

        $allowedEmbedTypes = $this->getAllowedEmbedTypes();
        if (count($allowedEmbedTypes) > 1) {
            $fields->addFieldToTab(
                'Root.' . $tab,
                ReadonlyField::create(
                    'EmbedType',
                    _t(self::class . '.TYPELABEL', 'Type')
                ),
                'EmbedImage'
            );
        }

        return $fields;
    }

    /**
     * Get the Extractor for the source URL
     */
    public function getExtractor(): Extractor
    {
        $sourceURL = $this->getOwner()->EmbedSourceURL ?? '';
        if($sourceURL === '') {
            throw new \RuntimeException(_t(self::class . '.EMPTY_SOURCE_URL', 'Source URL is empty'));
        }
        $parts = parse_url($sourceURL);
        if(!isset($parts['scheme'])) {
            throw new \RuntimeException(_t(self::class . '.EMPTY_SOURCE_URL_SCHEME', 'Source URL has no scheme'));
        }
        if(!isset($parts['host'])) {
            throw new \RuntimeException(_t(self::class . '.EMPTY_SOURCE_URL_HOST', 'Source URL has no host'));
        }
        $embed = new Embed();
        $extractor = $embed->get($sourceURL);
        return $extractor;
    }

    /**
     * Return the OEmbed for the URL, if it exists
     */
    public function getOEmbed(Extractor $extractor): OEmbed
    {
        return $extractor->getOEmbed();
    }

    /**
     * Get the embed data using a source URL and write relevant data to the owner
     */
    protected function writeFromEmbed(bool $force = false): bool
    {
        try {
            $extractor = $this->getExtractor();
            $owner = $this->getOwner();
            // write title if current is empty
            if ($owner->EmbedTitle == '') {
                $owner->EmbedTitle = $extractor->title;
            }

            // write description if current is empty
            if ($owner->EmbedDescription == '') {
                $owner->EmbedDescription = $extractor->description;
            }

            $urlChanged = $owner->isChanged('EmbedSourceURL', DataObject::CHANGE_VALUE);
            if ($force || $urlChanged) {
                // embed data from updated source URL
                $owner->EmbedHTML = $extractor->code->html;
                $oembed = $this->getOEmbed($extractor);
                // save type for oembed, if it exists
                $owner->EmbedType = $oembed ? strtolower($oembed->get('type') ?? '') : '';
                $owner->EmbedWidth = $extractor->code->width;
                $owner->EmbedHeight = $extractor->code->height;
                $owner->EmbedAspectRatio = $extractor->code->ratio;
                // allow some customisation from the owner object prior to write, when the source url has changed
                $owner->extend('onEmbedSourceChange', $embed);
            }

            return true;

        } catch (\Throwable $throwable) {
            Logger::log("Error writing embed object: " . $throwable->getMessage(), "NOTICE");
            throw \SilverStripe\ORM\ValidationException::create(_t(
                self::class . ".FAILED_TO_WRITE_EMBED",
                "Sorry, the embed details could not be found or saved. Please check the URL entered and try again."
            ));
        }
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $owner = $this->getOwner();
        $this->writeFromEmbed($this->owner->ForceUpdate == '1');
    }

    /**
     * Get embed types allowed in this instance
     */
    public function getAllowedEmbedTypes(): array
    {
        $allowedEmbedTypes = $this->getOwner()->config()->get('allowed_embed_types');
        if(!is_array($allowedEmbedTypes)) {
            $allowedEmbedTypes = [];
        }

        return $allowedEmbedTypes;
    }

    /**
     * Return embed folder from configuration or default
     */
    public function getEmbedFolder(): string
    {
        $owner = $this->getOwner();
        $folder = (string)$owner->config()->get('embed_folder');
        if ($folder === '') {
            $folder = 'Embeddable';
        }

        return $folder;
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes
     */
    public function setEmbedClass(string $class): DataObject
    {
        $classes = ($class) ? explode(' ', $class) : [];
        foreach ($classes as $value) {
            $this->classes[$value] = $value;
        }

        return $this->getOwner();
    }

    /**
     * Returns the CSS classes for this embed
     */
    public function getEmbedClass(): string
    {
        return implode(' ', $this->classes);
    }

    /**
     * Set the template to use for Embed
     * @param string $template template name without the .ss
     * @return DataObject Owner
     */
    public function setEmbedTemplate(string $template): DataObject
    {
        $this->embedTemplate = $template;
        return $this->getOwner();
    }

    /**
     * Get the template to use for Embed
     */
    public function getEmbedTemplate(): string
    {
        return $this->embedTemplate;
    }

    /**
     * Renders embed into appropriate template HTML
     */
    public function getEmbed(): DBHTMLText
    {
        $owner = $this->getOwner();
        $type = (string)$owner->EmbedType;
        $template = $this->getEmbedTemplate();
        $templates = [];
        if($type !== '') {
            $templates[] = $template . '_' . $type;
        }

        $templates[] = $template;
        $templates[] = "Embed";// BC support for original Embed template
        if (SSViewer::hasTemplate($templates)) {
            $embed = $owner->renderWith($templates);
        } else {
            // get HTML based on type
            $embed = $this->getEmbedByType();
        }
        return $embed;
    }

    /**
     * Return embed code by type
     */
    public function getEmbedByType(): DBHTMLText
    {
        $owner = $this->getOwner();
        $title = $owner->EmbedTitle;
        $type = (string)$owner->EmbedType;
        $cssClasses = $owner->EmbedClass;
        $embedHTML = $owner->EmbedHTML;
        $sourceURL = $owner->EmbedSourceURL;
        $width = $owner->EmbedWidth;
        $height = $owner->EmbedHeight;
        $html = '';
        $attributes = [];
        if($cssClasses !== '') {
            $attributes['class'] = $cssClasses;
        }
        switch ($type) {
            case self::EMBED_TYPE_VIDEO:
            case self::EMBED_TYPE_RICH:
                $html = HTML::createTag('div', $attributes, $embedHTML);
                break;
            case self::EMBED_TYPE_LINK:
                $attributes['href'] = $sourceURL;
                $html = HTML::createTag('a', $attributes, $title);
                break;
            case self::EMBED_TYPE_PHOTO:
            case self::EMBED_TYPE_IMAGE:
            case self::EMBED_TYPE_PICTURE:
                $attributes['src'] = $sourceURL;
                $attributes['width'] = $width;
                $attributes['height'] = $height;
                $attributes['alt'] = $title ?? '';
                $html = HTML::createTag('img', $attributes);
                break;
            default:
                $html = "<!-- cannot embed -->";
                break;
        }
        return DBField::create_field(DBHTMLText::class, $html);
    }
}

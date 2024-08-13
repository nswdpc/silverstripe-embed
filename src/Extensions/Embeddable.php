<?php

namespace NSWDPC\Embed\Extensions;

use Embed\Embed;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Storage\AssetStore;
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
use SilverStripe\View\SSViewer;

/**
 * Embeddable extension for Silverstripe DataObject
 */
class Embeddable extends DataExtension
{
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
     * @var string
     */
    private static string $embed_tab = 'Main';

    /**
     * List of custom CSS classes for template.
     * @var array
     */
    protected array $classes = [];

    /**
     * Defines the template to render the embed in.
     * @var string
     */
    protected string $template = 'NSWDPC/Embed/Models/Embed';

    /**
     * @inheritdoc
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $tab = $owner->config()->get('embed_tab');
        $tab = isset($tab) ? $tab : 'Main';

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
                    _t(__CLASS__ . '.TITLELABEL', 'Title')
                )
                ->setDescription(
                    _t(__CLASS__ . '.TITLEDESCRIPTION', 'Optional. Will be auto-generated if left blank')
                ),
                TextField::create(
                    'EmbedSourceURL',
                    _t(__CLASS__ . '.SOURCEURLLABEL', 'Source URL')
                )
                ->setDescription(
                    _t(__CLASS__ . '.SOURCEURLDESCRIPTION', 'Specify a external URL')
                ),
                UploadField::create(
                    'EmbedImage',
                    _t(__CLASS__ . '.IMAGELABEL', 'Image')
                )
                ->setFolderName($owner->EmbedFolder)
                ->setAllowedExtensions(['jpg','png','gif']),
                TextareaField::create(
                    'EmbedDescription',
                    _t(__CLASS__ . '.DESCRIPTIONLABEL', 'Description')
                )
            ]
        );

        if (isset($owner->AllowedEmbedTypes) && count($owner->AllowedEmbedTypes) > 1) {
            $fields->addFieldToTab(
                'Root.' . $tab,
                ReadonlyField::create(
                    'EmbedType',
                    _t(__CLASS__ . '.TYPELABEL', 'Type')
                ),
                'EmbedImage'
            );
        }

        return $fields;
    }

    /**
     * Get the embed data using a source URL and write relevant data to the owner
     */
    protected function writeFromEmbed(string $sourceURL): bool {
        try {
            if($sourceURL === '') {
                throw new \RuntimeException(_t(self::class . '.EMPTY_SOURCE_URL', 'Source URL is empty'));
            }
            $embed = new Embed($sourceURL);
            if(!$embed) {
                throw new \RuntimeException(_t(self::class . '.INVALID_EMBED', 'The embed record is invalid'));
            }

            $owner = $this->getOwner();
            // write title if current is empty
            if ($owner->EmbedTitle == '') {
                $owner->EmbedTitle = $embed->Title;
            }
            // write description if current is empty
            if ($owner->EmbedDescription == '') {
                $owner->EmbedDescription = $embed->Description;
            }

            if ($this->owner->isChanged('EmbedSourceURL')) {
                // embed data from updated source URL
                $owner->EmbedHTML = $embed->code->html;
                $owner->EmbedType = null;// update embed type in your own DataObject
                $owner->EmbedWidth = $embed->code->width;
                $owner->EmbedHeight = $embed->code->height;
                $owner->EmbedAspectRatio = $embed->code->ratio;
                // allow some customisation from the owner object prior to write, when the source url has changed
                $owner->extend('onEmbedSourceChange', $embed);
            }

        } catch (\Throwable $e) {
            Logger::log("Error writing embed object: " . $e->getMessage());
            throw new ValidationException(
                _t(
                    self::class . ".FAILED_TO_WRITE_EMBED",
                    "Sorry, the embed details could not be found or saved. Please check the URL entered and try again"
                )
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $owner = $this->getOwner();
        $this->writeFromEmbed($owner->EmbedSourceURL ?? '');
    }

    /**
     * Get embed types allowed in this instance
     */
    public function getAllowedEmbedTypes() : array|null
    {
        return $this->getOwner()->config()->get('allowed_embed_types');
    }

    /**
     * @return string
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
        foreach ($classes as $key => $value) {
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
     * Set CSS classes for templates
     * @param string $template template name without the .ss
     * @return DataObject Owner
     */
    public function setEmbedTemplate(string $template): DataObject
    {
        $this->template = $template;
        return $this->getOwner();
    }

    /**
     * Renders embed into appropriate template HTML
     * @return HTML
     */
    public function getEmbed()
    {
        $owner = $this->getOwner();
        $title = $owner->EmbedTitle;
        $cssClasses = $owner->EmbedClass;
        $type = $owner->EmbedType;
        $template = $this->template;
        $embedHTML = $owner->EmbedHTML;
        $sourceURL = $owner->EmbedSourceURL;
        $templates = [];
        if($type !== '') {
            $templates[] = $template . '_' . $type;
        }
        $templates[] = $template;
        $templates[] = "Embed";// BC support for original Embed template
        if (SSViewer::hasTemplate($templates)) {
            return $owner->renderWith($templates);
        }
        $html = '';
        $attributes = [];
        if($cssClasses !== '') {
            $attributes['class'] = $cssClasses;
        }
        switch ($type) {
            case 'video':
            case 'rich':
                $html = HTML::createTag('div', $attributes, $embedHTML);
                break;
            case 'link':
                $attributes['href'] = $sourceURL;
                $html = HTML::createTag('a', $attributes, $title);
                break;
            case 'photo':
            case 'image':
            case 'picture':
                $attributes['src'] = $sourceURL;
                $attributes['width'] = $this->Width;
                $attributes['height'] = $this->Height;
                $attributes['alt'] = $title ?? '';
                $html = HTML::createTag('img', $attributes);
                break;
            default:
                $html = "<!-- cannot embed -->";
                break;
        }
        return $html;
    }
}

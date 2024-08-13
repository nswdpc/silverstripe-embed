# Documentation

## Changes

Since the original v1:

+ Namespace update
+ Template name/path change in Embeddable from Embed to NSWDPC/Embed/Models/Embed, although `templates/Embed.ss` is still allowed
+ Dropped support for gorriecoe/silverstripe-htmltag
+ Requires embed/embed:^4
+ Requires silverstripe/framework:^5
+ Requires silverstripe/asset-admin:^2 (`UploadField`)
+ Remove logic no longer supported by embed/embed:^4

You can diff across repos to view all changes.

## Usage

The module can be used via standard Silverstripe relationship handling or via the `Embeddable` extension (or both)


### Via relationship

Example implementation, using HasOneButtonField

```php
<?php
namespace My\App;

use NSWDPC\Embed\Models\Embed;
use NSWDPC\Embed\Models\Video;
use SilverShop\HasOneField\HasOneButtonField;

class ClassName extends DataObject
{

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'Embed' => Embed::class,
        'Video' => Video::class
    ];

    /**
     * @inheritdoc
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Main',
            [
                HasOneButtonField::create(
                    'Embed',
                    'Embed',
                    $this
                ),
                HasOneButtonField::create(
                    'Video',
                    'Video',
                    $this
                )
            ]
        );
        return $fields;
    }
}
```

### Via extension

Update current DataObject to be Embeddable with DataExtension

```php
<?php
namespace My\App;

use NSWDPC\Embed\Extensions\Embeddable;

class OtherClassName extends DataObject
{

    /**
     * @inheritdoc
     */
    private static array $extensions = [
        Embeddable::class,
    ];

    /**
     * List the allowed included embed types.  If empty all are allowed.
     * @var array
     */
    private static array $allowed_embed_types = [
        'video',
        'photo'
    ];

    /**
     * Defines tab to insert the embed fields into.
     * @var string
     */
    private static string $embed_tab = 'Main';

    // other logic for the class
}
```

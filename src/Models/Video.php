<?php

namespace NSWDPC\Embed\Models;

use NSWDPC\Embed\Models\Embed;

/**
 * Video - video specific embed
 **/
class Video extends Embed
{
    /**
     * @inheritdoc
     */
    private static string $table_name = 'Video';

    /**
     * @inheritdoc
     */
    private static string $singular_name = 'Video';

    /**
     * @inheritdoc
     */
    private static string $plural_name = 'Video';

    /**
     * List the allowed included embed types.  If empty all are allowed.
     */
    private static array $allowed_embed_types = [
        'video'
    ];

    /**
     * Defines upload folder for embedded assets
     */
    private static string $embed_folder = 'Video';
}

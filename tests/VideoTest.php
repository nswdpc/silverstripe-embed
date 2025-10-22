<?php

namespace NSWDPC\Embed\Tests;

use NSWDPC\Embed\Extensions\Embeddable;
use NSWDPC\Embed\Models\Video;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\SSViewer;

/**
 * Test Video model
 */
class VideoTest extends SapphireTest
{

    protected $usesDatabase = true;

    /**
     * @inheritdoc
     * Set up test asset store, tmp file
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        // Remove project themes
        $themes = [
            "nswdpc/silverstripe-embed:tests/templates",
            SSViewer::DEFAULT_THEME,
        ];
        SSViewer::set_themes($themes);
    }

    /**
     * Create a video embed
     */
    public function testYoutubeEmbed(): void
    {
        $url = "https://www.youtube.com/watch?v=YH3c1QZzRK4";
        $video = Video::create();
        $this->assertTrue($video->hasExtension(Embeddable::class));
        $video->EmbedSourceURL = $url;
        $video->write();

        $this->assertEquals($url, $video->EmbedSourceURL);
        $this->assertNotEmpty($video->EmbedHTML);
        $this->assertNotEmpty($video->EmbedDescription);
        $this->assertNotEmpty($video->EmbedTitle);
        $this->assertEquals('video', $video->EmbedType);
        $this->assertNotEmpty($video->EmbedWidth);
        $this->assertNotEmpty($video->EmbedHeight);
        $extractor = $video->getExtractor();
        $this->assertInstanceOf(\Embed\Adapters\Youtube\Extractor::class, $extractor);

        $video->setEmbedTemplate('Embed_UnitTest');
        $template = $video->forTemplate();

        $this->assertEquals(
            '<div data-test="1">' . $video->EmbedHTML . '</div>',
            $template->__toString()
        );

    }
}
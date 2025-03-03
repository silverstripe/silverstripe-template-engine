<?php

namespace SilverStripe\TemplateEngine\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Core\TempFolder;
use SilverStripe\Versioned\Versioned;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\Director;
use SilverStripe\TemplateEngine\Exception\SSTemplateParseException;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Psr16Cache;

// Not actually a data object, we just want a ModelData object that's just for us

class SSTemplateEngineCacheBlockTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        SSTemplateEngineCacheBlockTest\TestModel::class
    ];

    public static function getExtraDataObjects()
    {
        $classes = parent::getExtraDataObjects();

        // Add extra classes if versioning is enabled
        if (class_exists(Versioned::class)) {
            $classes[] = SSTemplateEngineCacheBlockTest\VersionedModel::class;
        }
        return $classes;
    }

    /**
     * @var SSTemplateEngineCacheBlockTest\TestModel
     */
    protected $data = null;

    protected function reset($cacheOn = true)
    {
        $this->data = new SSTemplateEngineCacheBlockTest\TestModel();

        $cache = null;
        if ($cacheOn) {
            // cache indefinitely
            $cache = new Psr16Cache(new FilesystemAdapter('cacheblock', 0, TempFolder::getTempFolder(BASE_PATH)));
        } else {
            $cache = new Psr16Cache(new NullAdapter());
        }

        Injector::inst()->registerService($cache, CacheInterface::class . '.cacheblock');
        Injector::inst()->get(CacheInterface::class . '.cacheblock')->clear();
    }

    protected function runtemplate($template, $data = null)
    {
        if ($data === null) {
            $data = $this->data;
        }
        if (is_array($data)) {
            $data = $this->data->customise($data);
        }

        $engine = new SSTemplateEngine();
        return $engine->renderString($template, new ViewLayerData($data));
    }

    public function testParsing()
    {

        // ** Trivial checks **

        // Make sure an empty cached block parses
        $this->reset();
        $this->assertEquals('', $this->runtemplate('<% cached %><% end_cached %>'));

        // Make sure an empty cacheblock block parses
        $this->reset();
        $this->assertEquals('', $this->runtemplate('<% cacheblock %><% end_cacheblock %>'));

        // Make sure an empty uncached block parses
        $this->reset();
        $this->assertEquals('', $this->runtemplate('<% uncached %><% end_uncached %>'));

        // ** Argument checks **

        // Make sure a simple cacheblock parses
        $this->reset();
        $this->assertEquals('Yay', $this->runtemplate('<% cached %>Yay<% end_cached %>'));

        // Make sure a moderately complicated cacheblock parses
        $this->reset();
        $this->assertEquals('Yay', $this->runtemplate('<% cached \'block\', Foo, "jumping" %>Yay<% end_cached %>'));

        // Make sure a complicated cacheblock parses
        $this->reset();
        $this->assertEquals(
            'Yay',
            $this->runtemplate(
                '<% cached \'block\', Foo, Test.Test(4).Test(jumping).Foo %>Yay<% end_cached %>'
            )
        );

        // ** Conditional Checks **

        // Make sure a cacheblock with a simple conditional parses
        $this->reset();
        $this->assertEquals('Yay', $this->runtemplate('<% cached if true %>Yay<% end_cached %>'));

        // Make sure a cacheblock with a complex conditional parses
        $this->reset();
        $this->assertEquals('Yay', $this->runtemplate('<% cached if Test.Test(yank).Foo %>Yay<% end_cached %>'));

        // Make sure a cacheblock with a complex conditional and arguments parses
        $this->reset();
        $this->assertEquals(
            'Yay',
            $this->runtemplate(
                '<% cached Foo, Test.Test(4).Test(jumping).Foo if Test.Test(yank).Foo %>Yay<% end_cached %>'
            )
        );
    }

    /**
     * Test that cacheblocks actually cache
     */
    public function testBlocksCache()
    {
        // First, run twice without caching, to prove we get two different values
        $this->reset(false);

        $this->assertEquals('1', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 1]));
        $this->assertEquals('2', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 2]));

        // Then twice with caching, should get same result each time
        $this->reset(true);

        $this->assertEquals('1', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 1]));
        $this->assertEquals('1', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 2]));
    }

    /**
     * Test that the cacheblocks invalidate when a flush occurs.
     */
    public function testBlocksInvalidateOnFlush()
    {
        Director::test('/?flush=1');
        $this->reset(true);

        // Generate cached value for foo = 1
        $this->assertEquals('1', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 1]));

        // Test without flush
        Injector::inst()->get(Kernel::class)->boot();
        Director::test('/');
        $this->assertEquals('1', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 3]));

        // Test with flush
        Injector::inst()->get(Kernel::class)->boot(true);
        Director::test('/?flush=1');
        $this->assertEquals('2', $this->runtemplate('<% cached %>$Foo<% end_cached %>', ['Foo' => 2]));
    }

    public function testVersionedCache()
    {
        if (!class_exists(Versioned::class)) {
            $this->markTestSkipped('testVersionedCache requires Versioned extension');
        }
        $origReadingMode = Versioned::get_reading_mode();

        // Run without caching in stage to prove data is uncached
        $this->reset(false);
        Versioned::set_stage(Versioned::DRAFT);
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('default');
        $this->assertEquals(
            'default Stage.Stage',
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('first');
        $this->assertEquals(
            'first Stage.Stage',
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );

        // Run without caching in live to prove data is uncached
        $this->reset(false);
        Versioned::set_stage(Versioned::LIVE);
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('default');
        $this->assertEquals(
            'default Stage.Live',
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('first');
        $this->assertEquals(
            'first Stage.Live',
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );

        // Then with caching, initially in draft, and then in live, to prove that
        // changing the versioned reading mode doesn't cache between modes, but it does
        // within them
        $this->reset(true);
        Versioned::set_stage(Versioned::DRAFT);
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('default');
        $this->assertEquals(
            'default Stage.Stage',
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('first');
        $this->assertEquals(
            'default Stage.Stage', // entropy should be ignored due to caching
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );

        Versioned::set_stage(Versioned::LIVE);
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('first');
        $this->assertEquals(
            'first Stage.Live', // First hit in live, so display current entropy
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );
        $data = new SSTemplateEngineCacheBlockTest\VersionedModel();
        $data->setEntropy('second');
        $this->assertEquals(
            'first Stage.Live', // entropy should be ignored due to caching
            $this->runtemplate('<% cached %>$Inspect<% end_cached %>', $data)
        );

        Versioned::set_reading_mode($origReadingMode);
    }

    /**
     * Test that cacheblocks conditionally cache with if
     */
    public function testBlocksConditionallyCacheWithIf()
    {
        // First, run twice with caching
        $this->reset(true);

        $this->assertEquals('1', $this->runtemplate('<% cached if True %>$Foo<% end_cached %>', ['Foo' => 1]));
        $this->assertEquals('1', $this->runtemplate('<% cached if True %>$Foo<% end_cached %>', ['Foo' => 2]));

        // Then twice without caching
        $this->reset(true);

        $this->assertEquals('1', $this->runtemplate('<% cached if False %>$Foo<% end_cached %>', ['Foo' => 1]));
        $this->assertEquals('2', $this->runtemplate('<% cached if False %>$Foo<% end_cached %>', ['Foo' => 2]));

        // Then once cached, once not (and the opposite)
        $this->reset(true);

        $this->assertEquals(
            '1',
            $this->runtemplate(
                '<% cached if Cache %>$Foo<% end_cached %>',
                ['Foo' => 1, 'Cache' => true ]
            )
        );
        $this->assertEquals(
            '2',
            $this->runtemplate(
                '<% cached if Cache %>$Foo<% end_cached %>',
                ['Foo' => 2, 'Cache' => false]
            )
        );

        $this->reset(true);

        $this->assertEquals(
            '1',
            $this->runtemplate(
                '<% cached if Cache %>$Foo<% end_cached %>',
                ['Foo' => 1, 'Cache' => false]
            )
        );
        $this->assertEquals(
            '2',
            $this->runtemplate(
                '<% cached if Cache %>$Foo<% end_cached %>',
                ['Foo' => 2, 'Cache' => true ]
            )
        );
    }

    /**
     * Test that cacheblocks conditionally cache with unless
     */
    public function testBlocksConditionallyCacheWithUnless()
    {
        // First, run twice with caching
        $this->reset(true);

        $this->assertEquals(
            $this->runtemplate(
                '<% cached unless False %>$Foo<% end_cached %>',
                ['Foo' => 1]
            ),
            '1'
        );
        $this->assertEquals(
            $this->runtemplate(
                '<% cached unless False %>$Foo<% end_cached %>',
                ['Foo' => 2]
            ),
            '1'
        );

        // Then twice without caching
        $this->reset(true);

        $this->assertEquals(
            $this->runtemplate(
                '<% cached unless True %>$Foo<% end_cached %>',
                ['Foo' => 1]
            ),
            '1'
        );
        $this->assertEquals(
            $this->runtemplate(
                '<% cached unless True %>$Foo<% end_cached %>',
                ['Foo' => 2]
            ),
            '2'
        );
    }

    /**
     * Test that nested uncached blocks work
     */
    public function testNestedUncachedBlocks()
    {
        // First, run twice with caching, to prove we get the same result back normally
        $this->reset(true);

        $this->assertEquals(
            $this->runtemplate(
                '<% cached %> A $Foo B <% end_cached %>',
                ['Foo' => 1]
            ),
            ' A 1 B '
        );
        $this->assertEquals(
            $this->runtemplate(
                '<% cached %> A $Foo B <% end_cached %>',
                ['Foo' => 2]
            ),
            ' A 1 B '
        );

        // Then add uncached to the nested block
        $this->reset(true);

        $this->assertEquals(
            $this->runtemplate(
                '<% cached %> A <% uncached %>$Foo<% end_uncached %> B <% end_cached %>',
                ['Foo' => 1]
            ),
            ' A 1 B '
        );
        $this->assertEquals(
            $this->runtemplate(
                '<% cached %> A <% uncached %>$Foo<% end_uncached %> B <% end_cached %>',
                ['Foo' => 2]
            ),
            ' A 2 B '
        );
    }

    /**
     * Test that nested blocks with different keys works
     */
    public function testNestedBlocks()
    {
        $this->reset(true);

        $template = '<% cached Foo %> $Fooa <% cached Bar %>$Bara<% end_cached %> $Foob <% end_cached %>';

        // Do it the first time to load the cache
        $this->assertEquals(
            $this->runtemplate(
                $template,
                ['Foo' => 1, 'Fooa' => 1, 'Foob' => 3, 'Bar' => 1, 'Bara' => 2]
            ),
            ' 1 2 3 '
        );

        // Do it again, the input values are ignored as the cache is hit for both elements
        $this->assertEquals(
            $this->runtemplate(
                $template,
                ['Foo' => 1, 'Fooa' => 9, 'Foob' => 9, 'Bar' => 1, 'Bara' => 9]
            ),
            ' 1 2 3 '
        );

        // Do it again with a new key for Bar, Bara is picked up, Fooa and Foob are not
        $this->assertEquals(
            $this->runtemplate(
                $template,
                ['Foo' => 1, 'Fooa' => 9, 'Foob' => 9, 'Bar' => 2, 'Bara' => 9]
            ),
            ' 1 9 3 '
        );

        // Do it again with a new key for Foo, Fooa and Foob are picked up, Bara are not
        $this->assertEquals(
            $this->runtemplate(
                $template,
                ['Foo' => 2, 'Fooa' => 9, 'Foob' => 9, 'Bar' => 2, 'Bara' => 1]
            ),
            ' 9 9 9 '
        );
    }

    public function testNoErrorMessageForControlWithinCached()
    {
        $this->reset(true);
        $this->assertNotNull($this->runtemplate('<% cached %><% with Foo %>$Bar<% end_with %><% end_cached %>'));
    }

    public function testErrorMessageForCachedWithinControlWithinCached()
    {
        $this->expectException(SSTemplateParseException::class);
        $this->reset(true);
        $this->runtemplate(
            '<% cached %><% with Foo %><% cached %>$Bar<% end_cached %><% end_with %><% end_cached %>'
        );
    }

    public function testNoErrorMessageForCachedWithinControlWithinUncached()
    {
        $this->reset(true);
        $this->assertNotNull(
            $this->runtemplate(
                '<% uncached %><% with Foo %><% cached %>$Bar<% end_cached %><% end_with %><% end_uncached %>'
            )
        );
    }

    public function testErrorMessageForCachedWithinIf()
    {
        $this->expectException(SSTemplateParseException::class);
        $this->reset(true);
        $this->runtemplate('<% cached %><% if Foo %><% cached %>$Bar<% end_cached %><% end_if %><% end_cached %>');
    }

    public function testErrorMessageForInvalidConditional()
    {
        $this->expectException(SSTemplateParseException::class);
        $this->reset(true);
        $this->runtemplate('<% cached Foo if %>$Bar<% end_cached %>');
    }
}

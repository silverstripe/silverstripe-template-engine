<?php

namespace SilverStripe\TemplateEngine\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ArrayData;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;

class SSTemplateArgumentEscapingTest extends SapphireTest
{
    /**
     * A string argument to a template method call compiles to a single-quoted PHP literal. A
     * backslash in the author text must be escaped, otherwise a backslash before the closing quote
     * ends the literal early and the trailing author text runs as PHP - a remote code execution.
     * The test global echoes each argument back between "z" markers, so a safe render returns the
     * argument text rather than executing it.
     */
    public static function provideMethodCallArgumentsDoNotEvaluatePhp(): array
    {
        return [
            'quoted argument with backslash before quote' => [
                'template' => '$SSTemplateEngineTest_GlobalThatTakesArguments(\'a\\\'b\')',
                'expected' => 'za\\\'bz',
            ],
            'free argument with trailing backslash' => [
                'template' => '$SSTemplateEngineTest_GlobalThatTakesArguments(foo\\)',
                'expected' => 'zfoo\\z',
            ],
        ];
    }

    #[DataProvider('provideMethodCallArgumentsDoNotEvaluatePhp')]
    public function testMethodCallArgumentsDoNotEvaluatePhp(string $template, string $expected): void
    {
        $engine = new SSTemplateEngine();
        $result = trim((string) $engine->renderString($template, new ViewLayerData(new ArrayData([]))));
        $this->assertEquals($expected, $result);
    }
}

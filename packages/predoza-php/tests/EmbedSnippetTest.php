<?php

declare(strict_types=1);

namespace Predoza\Tests;

use PHPUnit\Framework\TestCase;
use Predoza\Embed\EmbedSnippet;

final class EmbedSnippetTest extends TestCase
{
    public function testTheScriptLoadsOncePerPageWithTheSignInUrls(): void
    {
        $snippet = new EmbedSnippet('/predoza/handoff', '/login', '/register');

        $first = $snippet->render('sf_CDSofyqv', lang: 'pl');
        $second = $snippet->render('sf_another1');

        self::assertSame(
            "<div data-predoza-feature=\"sf_CDSofyqv\" data-lang=\"pl\"></div>\n<script async src=\"https://play.predoza.com/embed/v1.js\" data-auth-url=\"/predoza/handoff\" data-login-url=\"/login\" data-register-url=\"/register\"></script>",
            $first,
        );
        self::assertSame('<div data-predoza-feature="sf_another1"></div>', $second);
        self::assertStringNotContainsString('integrity', $first);
    }

    public function testTheElementTakesClasses(): void
    {
        self::assertSame('<div class="predoza-game mt-4" data-predoza-feature="x"></div>', (new EmbedSnippet())->element('x', class: 'predoza-game mt-4'));
    }

    public function testValuesAreEscaped(): void
    {
        self::assertSame('<div data-predoza-feature="x" data-title="&quot;&gt;&lt;script&gt;"></div>', (new EmbedSnippet())->element('x', title: '"><script>'));
    }
}

<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\TwigComponent\Tests\Unit\Escaper;

use PHPUnit\Framework\TestCase;
use Symfony\UX\TwigComponent\Escaper\TwigHtmlAttributeEscaper;
use Twig\Runtime\EscaperRuntime;

class TwigHtmlAttributeEscaperTest extends TestCase
{
    /**
     * @dataProvider nameProvider
     */
    public function testEscapeName(string $input, string $expected): void
    {
        $escaper = new TwigHtmlAttributeEscaper(new EscaperRuntime());
        $this->assertSame($expected, $escaper->escapeName($input));
    }

    /**
     * @dataProvider nameProvider
     */
    public function testEscapeNameReturnSameAsTwig(string $input, string $expected): void
    {
        $runtime = new EscaperRuntime();
        $escaper = new TwigHtmlAttributeEscaper($runtime);
        $this->assertSame($runtime->escape($input, 'html_attr'), $escaper->escapeName($input));
    }

    /**
     * @dataProvider valueProvider
     */
    public function testEscapeValue(string $input, string $expected): void
    {
        $escaper = new TwigHtmlAttributeEscaper(new EscaperRuntime());
        $this->assertSame($expected, $escaper->escapeValue($input));
    }

    /**
     * @dataProvider valueProvider
     */
    public function testEscapeValueReturnSameAsTwig(string $input): void
    {
        $runtime = new EscaperRuntime();
        $escaper = new TwigHtmlAttributeEscaper($runtime);
        $this->assertSame($runtime->escape($input, 'html'), $escaper->escapeValue($input));
    }

    public static function nameProvider(): iterable
    {
        // Should not escape
        yield 'basic' => ['class', 'class'];
        yield 'data-' => ['data-user', 'data-user'];
        yield 'aria' => ['aria-label', 'aria-label'];
        yield 'alnum' => ['attr123', 'attr123'];
        // Should escape
        yield 'double quote' => ['"', '&quot;'];
        yield 'ampersand' => ['&', '&amp;'];
        yield 'less than' => ['<', '&lt;'];
        yield 'greater than' => ['>', '&gt;'];
        // Twig strict escaping
        yield 'scripts' => ['><script>', '&gt;&lt;script&gt;'];
        yield 'single quote' => ["'", '&#x27;'];
        yield 'unicode' => ['data-🚀', 'data-&#x1F680;'];
    }

    public static function valueProvider(): iterable
    {
        // Should not escape
        yield 'plain text' => ['Hello', 'Hello'];
        yield 'numeric value' => ['42', '42'];
        yield 'js url' => ['javascript:alert(1)', 'javascript:alert(1)'];
        // Should escape
        yield 'ampersand' => ['Hello & Welcome', 'Hello &amp; Welcome'];
        yield 'single quote' => ["O'Reilly", 'O&#039;Reilly'];
        yield 'double quotes' => ['"Hello"', '&quot;Hello&quot;'];
        yield 'less than' => ['<', '&lt;'];
        yield 'greater than' => ['>', '&gt;'];
        yield 'script' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'];
        yield 'inline xss' => ['<img src=x onerror=alert(1)>', '&lt;img src=x onerror=alert(1)&gt;'];
        yield 'malicious attr' => ['foo="bar"', 'foo=&quot;bar&quot;'];
        yield 'sql injection' => ["' OR 1=1 --", '&#039; OR 1=1 --'];
        yield 'url encoded xss' => ['%3Cscript%3Ealert(1)%3C/script%3E', '%3Cscript%3Ealert(1)%3C/script%3E'];
    }
}

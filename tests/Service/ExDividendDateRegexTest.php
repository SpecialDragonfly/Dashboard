<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

class ExDividendDateRegexTest extends TestCase
{
    private const PATTERN = '/Ex-[Dd]ividend [Dd]ate\s*<\/span>\s*<span[^>]*>([^<]+)/i';

    private function match(string $html): ?string
    {
        preg_match(self::PATTERN, $html, $m);
        return isset($m[1]) ? trim($m[1]) : null;
    }

    public function testStandardSpanWithClass(): void
    {
        $html = '<span>Ex-Dividend Date</span><span class="value svelte-t2kx6t">Mar 07, 2025</span>';
        $this->assertSame('Mar 07, 2025', $this->match($html));
    }

    public function testLowercaseLabel(): void
    {
        $html = '<span>Ex-dividend date</span><span class="value">Feb 05, 2025</span>';
        $this->assertSame('Feb 05, 2025', $this->match($html));
    }

    public function testWhitespaceBetweenSpans(): void
    {
        $html = '<span>Ex-Dividend Date</span>   <span class="value">Jan 31, 2025</span>';
        $this->assertSame('Jan 31, 2025', $this->match($html));
    }

    public function testNewlineBetweenSpans(): void
    {
        $html = "<span>Ex-Dividend Date</span>\n<span>Apr 04, 2025</span>";
        $this->assertSame('Apr 04, 2025', $this->match($html));
    }

    public function testSpanWithNoClass(): void
    {
        $html = '<span>Ex-Dividend Date</span><span>Dec 19, 2024</span>';
        $this->assertSame('Dec 19, 2024', $this->match($html));
    }

    public function testSurroundedByOtherContent(): void
    {
        $html = '<div><span>Market Cap</span><span>1.2T</span></div>'
              . '<div><span>Ex-Dividend Date</span><span class="value">Jun 14, 2025</span></div>'
              . '<div><span>Volume</span><span>42M</span></div>';
        $this->assertSame('Jun 14, 2025', $this->match($html));
    }

    public function testActualYahooHtml(): void
    {
        $html = '<li class=" yf-6myrf1">'
              . '<span class="label yf-6myrf1" title="Ex-dividend date">Ex-dividend date </span> '
              . '<span class="value yf-6myrf1">9 Oct 2025</span> '
              . '</li>';
        $this->assertSame('9 Oct 2025', $this->match($html));
    }

    public function testNoExDividendLabel(): void
    {
        $html = '<span>Market Cap</span><span>1.2T</span>';
        $this->assertNull($this->match($html));
    }

    public function testLabelWithNoFollowingSpan(): void
    {
        $html = '<span>Ex-Dividend Date</span><div>Mar 07, 2025</div>';
        $this->assertNull($this->match($html));
    }

    public function testNAValueIsRecognisable(): void
    {
        $html = '<span>Ex-Dividend Date</span><span class="value">N/A</span>';
        $this->assertSame('N/A', $this->match($html));
        // Confirm that the caller's N/A guard would reject it
        $this->assertFalse((bool) strtotime('N/A'));
    }
}

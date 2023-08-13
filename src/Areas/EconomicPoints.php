<?php
namespace Dashboard\Areas;

class EconomicPoints implements AreaInterface
{
    private $economicPoints = [
        "Interest rate (price of money): if interest rates are low it's good for the stock market.",
        "Inflation: High inflation is bad for the economy. Deflation is bad as well. Most sectors will do badly.",
        "GDP: steadily rising gdp is good for corporate earnings. 2 consecutive periods of falling gdp is called a recession.",
        "unemployment rate",
        "consumer confidence (the less consumer confidence the less spending happens)",
        "us house prices",
        "Most imporant indicator: ISM/PMI Index (very good predictor of how the economy will be in 3 - 6 months): above 50 it is expanding, below 50 it is contracting.",
        "It's long been said that manufacturing is the first sector to wobble, then the transmission into services is seen a quarter or so later. Lower inflation and slowing manufacturing hits the industrial services side of the economy first and this is eventually felt in retail facing areas."
    ];

    public function toHtml() : string {
        $html = "<h4>Key Economic Indicators</h4>";

        $html .= "<ol>";
        foreach ($this->economicPoints as $economicPoint) {
            $html .= "<li>".$economicPoint."</li>";
        }
        $html .= "</ol>";

        return $html;
    }

    public function htmlId(): string
    {
        return 'economic-knowledge';
    }

    public function areaTitle(): string
    {
        return 'Economic Points';
    }
}
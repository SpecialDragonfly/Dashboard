<?php
namespace Dashboard\Areas;

class TradingPoints implements AreaInterface
{
    private $tradingPoints = [
        "Volume is the cause for price",
        "Breakouts have a bigger volume and need confirmation on the second day with more than average volume afterwards",
        "Volume and overlayed MA20 (because there are 20 trading days /month)",
        "Keep it simple with charts, price and volume are the most important",
        "Concentrate on current investments, not past or future ones",
        "Only look at the daily chart, not 30 min, 5 min or anything. It'll make you crazy.",
        "Breaking resistance needs a follow through day, i.e. another day up.",
        "In a bull market a normal pullback from the highs is a 50% retracement",
        "Always look at daily, weekly, monthly to spot resistance/support points",
        "Watch volume on the daily chart to compare it better with other volumes on other days.",
        "Read news and find trading ideas 6:00 - 7:30am (Europe), for sure before the trading day starts",
        "Always keep some cash for short term opportunities",
        "You don't need to trade every day! You don't need to trade every day!",
        "If a company publishes earnings and the stock doesn't move much it might be that most people already own the stock, it could go down.",
        "Make sure you know who publishes the information, who's behind the news",
        "Stay away from penny stocks",
        "Use a stock scanner the day before trading to find potential candidates for trading"
    ];

    public function toHtml() : string {
        $html = "<p>Accumulated knowledge from blogs, people, magazines, etc. over the last year.</p>";
        $html .= "<h4>Most importantly</h4>";
        $html .= "<ol>";
        foreach ($this->tradingPoints as $point) {
            $html .= "<li>".$point."</li>";
        }
        $html .= "</ol>";

        return $html;
    }

    public function htmlId(): string
    {
        return 'trading-knowledge';
    }

    public function areaTitle(): string
    {
        return 'Trading Knowledge';
    }
}
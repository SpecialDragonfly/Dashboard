<?php
namespace Dashboard;

class Body
{
    private $trading;
    private $economic;
    private $serverDebugging;

    public function __construct(TradingPoints $trading, EconomicPoints $economic, ServerDebugging $serverDebugging) {
        $this->trading = $trading;
        $this->economic = $economic;
        $this->serverDebugging = $serverDebugging;
    }

    public function toString() {

        $aspects = ['Try to Understand', 'Try to be Kind', 'Try to be Brave', "Don't be a Dick"];

        $html = "<body>";
        $html .= "<header>";
        $html .= '<div id="time-remaining"></div>';
        $html .= '<div id="time-so-far"></div>';
        $html .= "</header>";
        $html .= '<div class="container">';
        $html .= '<div class="widget double-width" id="nasa-apod">';
        $html .= '<div class="heading">Nasa APOD</div>';
        $html .= '<div class="content scrolling"></div>';
        $html .= '</div>';
        $html .= '<div class="widget" id="way-of-paul">';
        $html .= '<div class="heading">Way of Paul</div>';
        $html .= '<div class="content">';
        $html .= '<ul>';
        foreach ($aspects as $aspect) {
            $html .= "<li>".$aspect."</li>";
        }
        $html .= '</ul>';
        $html .= '<div> - The Way of Paul </div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="widget double-width" id="trading-knowlegde">';
        $html .= '<div class="heading">Trading Knowledge</div><!-- https://thinkery.me/nader/53172b621cb6025b0a0127c7 -->';
        $html .= '<div class="content scrolling">';
        $html .= $this->trading->toHtml();
        $html .= $this->economic->toHtml();
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="widget" id="server-debugging">';
        $html .= '<div class="heading">Server Debugging</div>';
        $html .= '<div class="content scrolling">';
        $html .= $this->serverDebugging->toHtml();
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</body>";

        return $html;
    }
}
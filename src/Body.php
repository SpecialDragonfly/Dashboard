<?php
namespace Dashboard;

use Dashboard\Areas\AreaInterface;

class Body
{
    /**
     * @var AreaInterface[]
     */
    private $areas;

    public function __construct(AreaInterface ...$areas) {
        $this->areas = $areas;
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
        foreach ($this->areas as $area) {
            $html .= '<div class="widget double-width" id="'.$area->htmlId().'">';
            $html .= '<div class="heading">'.$area->areaTitle().'</div><!-- https://thinkery.me/nader/53172b621cb6025b0a0127c7 -->';
            $html .= '<div class="content scrolling">';
            $html .= $area->toHtml();
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= "</div>";
        $html .= "</body>";

        return $html;
    }
}
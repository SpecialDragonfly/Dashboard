<?php
namespace Dashboard;

class Head
{
    private $styles;
    private $scripts;
    private $prefetch;

    public function __construct($styles, $scripts, $prefetch) {
        $this->styles = $styles;
        $this->scripts = $scripts;
        $this->prefetch = $prefetch;
    }
    public function toString() {
        $html = "<head>";
        $html .= "<title>NotQuiteHuman</title>";
        foreach ($this->styles as $style) {
            $html .= '<link rel="stylesheet" href="'.$style.'" />';
        }
        foreach ($this->scripts as $script) {
            $html .= '<script type="text/javascript" src="'.$script.'"></script>';
        }
        foreach ($this->prefetch as $link) {
            $html .= '<link rel="prefetch" href="'.$link.'" />';
        }
        $html .= "</head>";
        return $html;
    }
}
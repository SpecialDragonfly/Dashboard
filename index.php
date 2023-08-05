<?php
require_once __DIR__.'/vendor/autoload.php';

use Dashboard\Body;
use Dashboard\EconomicPoints;
use Dashboard\Head;
use Dashboard\Html;
use Dashboard\ServerDebugging;
use Dashboard\TradingPoints;

$head = (new Head(
    ["assets/style.css"],
    ["https://code.jquery.com/jquery-latest.min.js", "assets/scripts.js"],
    [
        "http://www.cad-comic.com/", 
        "http://www.questionablecontent.net/", 
        "http://xkcd.com/", 
        "http://www.girlswithslingshots.com/",
        "http://365tomorrows.com/", 
        "http://thecodelesscode.com/contents"
    ]
))->toString();
$body = (new Body(new TradingPoints(), new EconomicPoints(), new ServerDebugging()))->toString();

$html = new Html();
$html->out($head.$body);

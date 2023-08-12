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
        "https://www.cad-comic.com/", 
        "https://www.questionablecontent.net/", 
        "https://xkcd.com/", 
        "https://www.girlswithslingshots.com/",
        "https://365tomorrows.com/", 
	"https://thecodelesscode.com/contents",
	"https://www.dailycodingproblem.com/"
    ]
))->toString();
$body = (new Body(new TradingPoints(), new EconomicPoints(), new ServerDebugging()))->toString();

$html = new Html();
$html->out($head.$body);

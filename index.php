<?php
require_once __DIR__.'/../src/dashboard.php';

$head = (new Head(
    ["style.css"], 
    ["https://code.jquery.com/jquery-latest.min.js", "scripts.js"], 
    [
        "http://www.cad-comic.com/", 
        "http://www.questionablecontent.net/", 
        "http://xkcd.com/", 
        "http://www.girlswithslingshots.com/", 
        "http://dilbert.com/", 
        "http://365tomorrows.com/", 
        "http://thecodelesscode.com/contents"
    ]
))->toString();
$body = (new Body(new TradingPoints(), new EconomicPoints()))->toString();

$html = new Html();
$html->out($head.$body);

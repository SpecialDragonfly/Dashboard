<?php
require_once __DIR__.'/vendor/autoload.php';

use Dashboard\Areas\BlogRoll;
use Dashboard\Areas\EconomicPoints;
use Dashboard\Areas\ServerDebugging;
use Dashboard\Areas\TradingPoints;
use Dashboard\Body;
use Dashboard\Head;
use Dashboard\Html;

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
$blogRoll = new BlogRoll(
    [
        'Constanze' => 'http://constanzethegreatandhersexyleukaemia.blogspot.co.uk/',
        'Dan Summers' => 'http://www.glasshalfempty.co.uk/',
        'Nik Barham' => 'http://www.brokencube.co.uk/',
        'CraigK' => 'http://www.craigk.org/',
        'Will Tomlinson' => 'http://chronos.diskstation.me/',
        'John Haynes' => 'https://joehaynes98.wordpress.com/'
    ]
);
$body = (new Body(new TradingPoints(), new EconomicPoints(), new ServerDebugging(), $blogRoll))->toString();

$html = new Html();
$html->out($head.$body);

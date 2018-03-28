<?php
class Output {
	public function out($msg) {
		echo $msg;
	}
}

class Html extends Output {
	public function out($msg) {
		parent::out("<html>".$msg."</html>");
	}
}

class Head {
  public function __construct($styles, $scripts, $prefetch) {
    $this->styles = $styles;
    $this->scripts = $scripts;
    $this->prefetch = $prefetch;
  }
  public function toString() {
    $html = "<head>";
    foreach ($this->styles as $style) {
        $html .= '<link rel="stylesheet" href="'.$style.'" />';
    }
    foreach ($this->scripts as $script) {
        $html .= '<script type="text/javascript" src="'.$script.'"></script>';
    }
    foreach ($this->prefetch as $link) {
        $html .= '<link rel="prefetch" href="'.$link.'" />';
    }
    $html .= "<title>NotQuiteHuman</title>";
    $html .= "</head>";
    return $html;
  }
};

class TradingPoints {
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

    public function toHtml() {
	    $html = "<p>Accumulated knowledge from blogs, people, magazines, etc. over the last year.</p>";
	    $html .= "<h4>Most importantly</h4>";
	    $html .= "<ol>";
	    foreach ($this->tradingPoints as $point) {
	        $html .= "<li>".$point."</li>";
	    }
	    $html .= "</ol>";

	    return $html;
    }
};

class EconomicPoints {
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

    public function toHtml() {
	    $html = "<h4>Key Economic Indicators</h4>";

	    $html .= "<ol>";
	    foreach ($this->economicPoints as $economicPoint) {
	        $html .= "<li>".$economicPoint."</li>";
	    }
	    $html .= "</ol>";

	    return $html;
    }
};

class ServerDebugging {
	private $checkPoints = [
		"uptime # uptime and CPU stress",
		"w # or better yet:last |head # who is/has been in",
		"netstat -tlpn # find server role",
		"df -h # out of disk space?",
		"df -hi #checking for inode availability",
		"grep kill /var/log/messages # out of memory?",
		"ps auxf # what's running",
		"htop # stressed? , look out for D (waiting on I/O typically) processes",
		"history # what has changed recently",
		"tail /var/log/application.log # anything interesting logged?"
	];
	public function toHtml() {
	    $html = "<p>My goto for initially troubleshooting a server is:</p>";
	    $html .= "<ol>";
	    foreach ($this->checkPoints as $point) {
	        $html .= "<li>".$point."</li>";
	    }
	    $html .= "</ol>";
	}
}

class Body {
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
    $html .= $this->serverDebugging->toHtml();
    $html .= '</div>';
    $html .= '</div>';
    $html .= "</div>";
    $html .= "</body>";
    
    return $html;
  }
}

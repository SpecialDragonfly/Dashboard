// no conflict fix
(function($) {
    class Time {
        static since(time) {
            var now = Date.now();
            var seconds = parseInt((now - time) / 1000);

            var days = parseInt(seconds / 86400);
            seconds = seconds % 86400;

            var hours = parseInt(seconds / 3600);
            seconds = seconds % 3600;

            var minutes = parseInt(seconds / 60);
            seconds = parseInt(seconds % 60);

            return {'days': days, 'hours' : hours, 'minutes' : minutes, 'seconds' : seconds};
        }

        static until(time) {
            var now = Date.now();
            var seconds = parseInt((time - now) / 1000);

            var days = parseInt(seconds / 86400);
            seconds = seconds % 86400;

            var hours = parseInt(seconds / 3600);
            seconds = seconds % 3600;

            var minutes = parseInt(seconds / 60);
            seconds = parseInt(seconds % 60);

            return {'days': days, 'hours' : hours, 'minutes' : minutes, 'seconds' : seconds};
        }
    }

    class Strategy {
        constructor() {
            this.urls = {
                'blog':[
                    {name:'Constanze', src:'http://constanzethegreatandhersexyleukaemia.blogspot.co.uk/'},
                    {name:'Dan Summers', src:'http://www.glasshalfempty.co.uk/'},
                    {name:'Nik Barham', src:'http://www.brokencube.co.uk/'},
                    {name:'CraigK', src:'http://www.craigk.org/'},
                    {name:'Will Tomlinson', src:'http://chronos.diskstation.me/'},
                    {name:'John Haynes', src:'https://joehaynes98.wordpress.com/'}
                ],
                'comic':[
                    {name:'Ctrl-Alt-Del', src:'http://www.cad-comic.com/'},
                    {name:'QC', src:'http://www.questionablecontent.net/'},
                    {name:'XKCD', src:'http://xkcd.com/', mobile:'http://m.xkcd.com'},
                    {name:'Girls With Slingshots', src:'http://www.girlswithslingshots.com/'},
                    {name:'Dilbert', src:'http://dilbert.com/'},
                    {name:'365 tomorrows', src:'http://365tomorrows.com/'},
                    {name:'The Codeless Code', src:'http://thecodelesscode.com/contents'}
                ]
            };
        }

        attachListeners() {
        }

        startTimers() {
            var timeSoFar = $("#time-so-far"); 
            var before = new Date(2014, 10, 24, 10, 0, 0);
            var soFar = Time.since(before);
            timeSoFar.html(soFar.days + " days " + soFar.hours + " hours " + soFar.minutes + " minutes " + soFar.seconds + " seconds");

           setInterval(function() {
               var soFar = Time.since(before);
               timeSoFar.html(soFar.days + " days " + soFar.hours + " hours " + soFar.minutes + " minutes " + soFar.seconds + " seconds");
           }, 30000);

           var timeUntilElement = $('#time-remaining');
           var until = new Date(2018, 3, 21, 1, 0, 0);
           var untilTime = Time.until(until);
           timeUntilElement.html(untilTime.days + " days " + untilTime.hours + " hours " + untilTime.minutes + " minutes " + untilTime.seconds + " seconds");
           setInterval(function() {
               var untilTime = Time.until(until);
               timeUntilElement.html(untilTime.days + " days " + untilTime.hours + " hours " + untilTime.minutes + " minutes " + untilTime.seconds + " seconds");
           }, 30000);
        } 

        callNasa(event) {
            $.get(
                "https://api.nasa.gov/planetary/apod?api_key=DEMO_KEY"
            ).success(
                event.data.success
            ).error(
                function(e, xhr){
                    $("#nasa-apod").append(
                        $("<div>").addClass("nasa-error").append(
                            $("<h3>").html(e.responseJSON.error.code)
                        ).append(
                            $("<div>").html(e.responseJSON.error.message)
                        )
                    )
                }
            )
        }
      
        render() {

        }
    }
     
    class MobileStrategy extends Strategy {
        constructor() {
            super();
        }

        startTimers() {
            super.startTimers();
        }

        attachListeners() {
            super.attachListeners();
            $("#load-nasa").on("click", {"success": this.renderNasa}, this.callNasa);
        }

        renderNasa(data) {
            var e = $("<div>").append(
                $("<img>").attr({'src':data.url, 'width': $("#nasa-apod").width()})
            ).append(
                $("<div>").attr({"id": "apod-mobile-explanation"}).append(
                    $("<h3>").html(data.title)
                ).append(
                    $("<div>").html(data.explanation)
                )
            );
            $("#nasa-apod").append(e);
        }

        render() {
            $("#trading-knowledge").hide();
            $("body").addClass('mobile');
            $("#display-type").html("Displaying as a mobile");
            $("#nasa-apod").append($("<button>").attr("id", "load-nasa").html("Load Nasa"));
            var ul = $(".main");
            $.each(this.urls, function(category, sites) {
                $(document.body).append($("<h3>").html(category));
                $(document.body).append(
                    $("<ul>").attr('data-category', category).addClass(category).addClass("category")
                );
                var ul = $("ul[data-category='" + category + "']");
                $.each(sites, function(index, site) {
                    var src = site.mobile === undefined ? site.src : site.mobile;
                    ul.append(
                        $("<li>").append(
                            $("<a>").html(site.name).attr('href', src)
                        )
                    )
                });
            });
            super.render();
        }
    }

    class DesktopStrategy extends Strategy {
        constructor() {
            super();
        }

        startTimers() {
            super.startTimers();
        }

        attachListeners() {
            super.attachListeners();
            $("#load-nasa").on("click", {"success": this.renderNasa}, this.callNasa);
        }

        renderNasa(data) {
            $("#nasa-apod .content").append(
                $("<div>").attr({"id": "apod-explanation"}).css({'width': "25%"}).append(
                    $("<h3>").html(data.title)
                ).append(
                    $("<div>").html(data.explanation)
                )
            ).append(
                $("<img>").attr({'src':data.url, 'width': "74%"})
            );
            $("#nasa-apod .heading").append(
                $("<a>").attr('href', data.hdurl).attr('style', 'float:right').html("HD")
            );
            $("#nasa-apod .content button").remove();
        }

        render() { 
            $("body").addClass('desktop'); 
            $("#display-type").html("Displaying as a desktop");
            $("#trading-knowledge-title").on('click', function() {
                if ($("#trading-knowledge").height() > 60) {
                    $("#trading-knowledge").animate({'height':60});
                } else {
                    $("#trading-knowledge").animate({'height':681});
                }
            });
            $("#nasa-apod .content").append($("<button>").attr("id", "load-nasa").html("Load Nasa"));
            super.render();
            var container = $(".container");
            var ul = $(".main");
            $.each(this.urls, function(category, sites) {
                $(container).append($("<h3>").html(category));
                $(container).append(
                    $("<ul>").attr('data-category', category).addClass(category).addClass("category")
                );
                var ul = $("ul[data-category='" + category + "']");
                $.each(sites, function(index, site) {
                    ul.append(
                        $("<li>").append(
                            $("<a>").html(site.name).attr('href', site.src).attr('target', '_blank')
                        ) 
                    )
                });
            });
        } 
    }
 
    class StrategyFactory {
        static create() {
            var strategy = new DesktopStrategy();
            if (this.mobile()) {
                strategy = new MobileStrategy();
            }

            return strategy;
        }

        static mobile() {
            return navigator.userAgent.match(
                /(iPhone|iPod|iPad|Android|BlackBerry)/
            );
        }
    }  

    // document.ready shorthand
    $(function() {
        function init() {
            var strategy = StrategyFactory.create();
            strategy.render();
            strategy.startTimers();
            strategy.attachListeners();
        }

        $(window).on('orientationchange', init);
        init();
    });
})(jQuery);


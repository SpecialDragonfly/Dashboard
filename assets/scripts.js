// no conflict fix
(function($) {
    class Time {
        static since(time) {
            let now = Date.now();
            let seconds = parseInt((now - time) / 1000);

            let days = parseInt(seconds / 86400);
            seconds = seconds % 86400;

            let hours = parseInt(seconds / 3600);
            seconds = seconds % 3600;

            let minutes = parseInt(seconds / 60);
            seconds = parseInt(seconds % 60);

            return {'days': days, 'hours' : hours, 'minutes' : minutes, 'seconds' : seconds};
        }

        static until(time) {
            let now = Date.now();
            let seconds = parseInt((time - now) / 1000);

            let days = parseInt(seconds / 86400);
            seconds = seconds % 86400;

            let hours = parseInt(seconds / 3600);
            seconds = seconds % 3600;

            let minutes = parseInt(seconds / 60);
            seconds = parseInt(seconds % 60);

            return {'days': days, 'hours' : hours, 'minutes' : minutes, 'seconds' : seconds};
        }
    }

    class Strategy {
        constructor() {
            this.urls = {
                'comic':[
                    {name:'Ctrl-Alt-Del', src:'https://www.cad-comic.com/'},
                    {name:'QC', src:'https://www.questionablecontent.net/'},
                    {name:'XKCD', src:'https://xkcd.com/', mobile:'http://m.xkcd.com'},
                    {name:'Girls With Slingshots', src:'https://www.girlswithslingshots.com/'},
                    {name:'365 tomorrows', src:'https://365tomorrows.com/'},
                    {name:'The Codeless Code', src:'https://thecodelesscode.com/contents'},
                    {name:'Daily Coding Problem', src:'https://www.dailycodingproblem.com'}
                ]
            };
        }

        attachListeners() {
        }

        startTimers() {
            let timeSoFar = $("#time-so-far");
            let before = new Date(2022, 3, 25, 10, 0, 0);
            let soFar = Time.since(before);
            timeSoFar.html(soFar.days + " days " + soFar.hours + " hours " + soFar.minutes + " minutes " + soFar.seconds + " seconds");

            setInterval(function() {
                let soFar = Time.since(before);
                timeSoFar.html(soFar.days + " days " + soFar.hours + " hours " + soFar.minutes + " minutes " + soFar.seconds + " seconds");
            }, 30000);

            let timeUntilElement = $('#time-remaining');
            let until = new Date(2023, 11, 24, 1, 0, 0);
            let untilTime = Time.until(until);
            timeUntilElement.html(untilTime.days + " days " + untilTime.hours + " hours " + untilTime.minutes + " minutes " + untilTime.seconds + " seconds");
            setInterval(function() {
                let untilTime = Time.until(until);
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
            let e = $("<div>").append(
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
            let ul = $(".main");
            $.each(this.urls, function(category, sites) {
                $(document.body).append($("<h3>").html(category));
                $(document.body).append(
                    $("<ul>").attr('data-category', category).addClass(category).addClass("category")
                );
                let ul = $("ul[data-category='" + category + "']");
                $.each(sites, function(index, site) {
                    let src = site.mobile === undefined ? site.src : site.mobile;
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
            let container = $(".container");
            let ul = $(".main");
            $.each(this.urls, function(category, sites) {
                $(container).append($("<h3>").html(category));
                $(container).append(
                    $("<ul>").attr('data-category', category).addClass(category).addClass("category")
                );
                let ul = $("ul[data-category='" + category + "']");
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
            let strategy = new DesktopStrategy();
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
            let strategy = StrategyFactory.create();
            strategy.render();
            strategy.startTimers();
            strategy.attachListeners();
        }

        $(window).on('orientationchange', init);
        init();
    });
})(jQuery);

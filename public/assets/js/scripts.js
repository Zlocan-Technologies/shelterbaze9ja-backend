/*================= Custom js file =================*/

/*************************************************
01. Start of use strict
02. Obri Sticky Header Script
03. Obri Toggle Script
04. Obri Parallax And Width Script
05. Obri Parallax Scroll Script
06. Obri Owl Carousel Slider Script
07. Saaspot Boostrap Video Modal Script
08. Obri Player Script
09. Obri Gotop Script
10. Obri Preloader Script
*************************************************/

$(document).ready(function () {
    //01 Start of use strict
    "use strict";

    //02 Obri Sticky Header Script
    $(".obri-header").sticky({
        topSpacing: 0,
        zIndex: 4,
    });

    //03 Obri Toggle Script
    $(".obri-toggle").on("click", function () {
        $(this).toggleClass("active");
    });

    //04 Obri Parallax And Width Script
    $(window).resize(function () {
        if (screen.width <= 991) {
            $(".obri-navigation ul li a").on("click", function () {
                $(".obri-toggle").removeClass("active");
                $(".obri-navigation").slideUp("");
            });
        }
    });
    $(window).trigger("resize");

    //05 Obri Parallax Scroll Script
    $(".obri-navigation ul li a").mPageScroll2id({
        offset: ".sticky-wrapper",
    });

    //06 Obri Owl Carousel Slider Script
    $(".owl-carousel").each(function () {
        var $carousel = $(this);
        var $items =
            $carousel.data("items") !== undefined ? $carousel.data("items") : 1;
        var $items_tablet =
            $carousel.data("items-tablet") !== undefined
                ? $carousel.data("items-tablet")
                : 1;
        var $items_mobile_landscape =
            $carousel.data("items-mobile-landscape") !== undefined
                ? $carousel.data("items-mobile-landscape")
                : 1;
        var $items_mobile_portrait =
            $carousel.data("items-mobile-portrait") !== undefined
                ? $carousel.data("items-mobile-portrait")
                : 1;
        $carousel.owlCarousel({
            loop:
                $carousel.data("loop") !== undefined
                    ? $carousel.data("loop")
                    : true,
            items: $carousel.data("items"),
            margin:
                $carousel.data("margin") !== undefined
                    ? $carousel.data("margin")
                    : 0,
            dots:
                $carousel.data("dots") !== undefined
                    ? $carousel.data("dots")
                    : false,
            nav:
                $carousel.data("nav") !== undefined
                    ? $carousel.data("nav")
                    : false,
            navText: [
                "<div class='slider-no-current'><span class='current-no'></span><span class='total-no'></span></div><span class='current-monials'></span>",
                "<div class='slider-no-next'></div><span class='next-monials'></span>",
            ],
            autoplay:
                $carousel.data("autoplay") !== undefined
                    ? $carousel.data("autoplay")
                    : false,
            autoplayTimeout:
                $carousel.data("autoplay-timeout") !== undefined
                    ? $carousel.data("autoplay-timeout")
                    : 5000,
            animateIn:
                $carousel.data("animatein") !== undefined
                    ? $carousel.data("animatein")
                    : false,
            animateOut:
                $carousel.data("animateout") !== undefined
                    ? $carousel.data("animateout")
                    : false,
            mouseDrag:
                $carousel.data("mouse-drag") !== undefined
                    ? $carousel.data("mouse-drag")
                    : true,
            autoWidth:
                $carousel.data("auto-width") !== undefined
                    ? $carousel.data("auto-width")
                    : false,
            autoHeight:
                $carousel.data("auto-height") !== undefined
                    ? $carousel.data("auto-height")
                    : false,
            center:
                $carousel.data("center") !== undefined
                    ? $carousel.data("center")
                    : false,
            responsiveClass: true,
            dotsEachNumber: true,
            smartSpeed: 600,
            responsive: {
                0: {
                    items: $items_mobile_portrait,
                },
                480: {
                    items: $items_mobile_landscape,
                },
                768: {
                    items: $items_tablet,
                },
                992: {
                    items: $items,
                },
            },
        });
        var totLength = $(".owl-dot", $carousel).length;
        $(".total-no", $carousel).html(totLength);
        $(".current-no", $carousel).html(totLength);
        $carousel.owlCarousel();
        $(".current-no", $carousel).html(1);
        $carousel.on("changed.owl.carousel", function (event) {
            var total_items = event.page.count;
            var currentNum = event.page.index + 1;
            $(".total-no", $carousel).html(total_items);
            $(".current-no", $carousel).html(currentNum);
        });
    });

    //07 Saaspot Boostrap Video Modal Script
    var $videoSrc;
    $('[data-target="#VideoModal"]').on("click", function () {
        $videoSrc = $(this).data("src");
    });
    console.log($videoSrc);
    $("#VideoModal").on("shown.bs.modal", function (e) {
        $("#ModalVideoWrap").attr(
            "src",
            $videoSrc +
                "?rel=0&amp;showinfo=0&amp;modestbranding=1&amp;autoplay=1"
        );
    });
    $("#VideoModal").on("hide.bs.modal", function (e) {
        $("#ModalVideoWrap").attr("src", $videoSrc);
    });

    //08 Obri Player Script
    $("#player")
        .ContainerPlayer({
            youTube: {
                videoId: "9peU958JPZU",
                poster: "assets/images/background1.png",
            },
        })
        .on(
            "video.playing video.paused video.loaded video.ended",
            function (e) {
                console.log(e);
            }
        );

    //09 Obri Gotop Script
    var gotop = $(".obri-gotop");
    var position = gotop.offset().top;
    $(window).on("scroll", function () {
        var windowposition = $(window).scrollTop();
        if (windowposition + $(window).height() == $(document).height()) {
            gotop.removeClass("active");
        } else if (windowposition > 150) {
            gotop.addClass("active");
        } else {
            gotop.removeClass("active");
        }
    });
    jQuery(".obri-gotop a").on("click", function () {
        jQuery("body,html").animate(
            {
                scrollTop: 0,
            },
            1500
        );
        return false;
    });

    //10 Obri Preloader Script
    $(window).on("load", function () {
        $(".obri-preloader").fadeOut(500);
    });
});

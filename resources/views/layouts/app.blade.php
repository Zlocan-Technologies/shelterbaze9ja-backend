<!DOCTYPE html>
<html dir="ltr" lang="en-US">

<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Obri Video</title>
    <link rel="shortcut icon" href="assets/images/icon1@1x.ico" type="image/x-icon">
    <!-- Obri External CSS -->
    <link href="assets/css/player.min.css" rel="stylesheet" type="text/css" media="all">
    <link href="assets/css/owl.carousel.min.css" rel="stylesheet" type="text/css" media="all">
    <link href="assets/css/loaders.min.css" rel="stylesheet" type="text/css" media="all">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" media="all">
    <link href="assets/css/font-awesome.min.css" rel="stylesheet" type="text/css" media="all">
    <link href="assets/css/styles.css" rel="stylesheet" type="text/css" media="all">
    <link href="assets/css/responsive.css" rel="stylesheet" type="text/css" media="all">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700" rel="stylesheet" type="text/css"
        media="all">
</head>

<body>

    @yield('content')


    <!-- Obri Footer -->
    <footer class="obri-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    Copyright &copy; 2025. All Rights Reserved
                </div>
                <div class="col-md-8 textright">
                    <a href="#0">Terms &amp; conditions</a>
                    <a href="#0">Privacy policy</a>
                    <a href="#0">Blog</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Obri Gotop -->
    <div class="obri-gotop">
        <a href="javascript:void(0);"><i class="fa fa-angle-up" aria-hidden="true"></i></a>
    </div>

    <!-- Obri Preloader -->
    <div class="obri-preloader">
        <div class="loader-wrap">
            <div class="loader">
                <div class="loader-inner pacman"></div>
            </div>
        </div>
    </div>

    <!-- Obri Modal, Modal Video, Fade -->
    <div class="modal modal-video fade" id="VideoModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item saaspot-video-iframe" id="ModalVideoWrap"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script type="text/javascript" src="assets/js/jquery.min.js"></script>
    <script type="text/javascript" src="assets/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="assets/js/plugins.js"></script>
    <script type="text/javascript" src="assets/js/scripts.js"></script>
</body>

</html>
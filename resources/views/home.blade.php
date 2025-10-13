@extends('layouts.app')
@section('content')


    @include('inc.header')
    <!-- Obri Banner, Banner Style Two -->
    <section class="obri-banner banner-style-two">
        <div class="container">
            <div class="row">
                <div class="col-lg-7">
                    <div class="banner-caption">
                        <h1 class="caption-title">Find Your Next Apartment with Shelterbaze</h1>
                        <p>Simplifying rentals for tenants, landlords, and agents — from verified listings to seamless
                            payments and rent savings plans. Discover a safer, smarter way to rent and manage properties</p>

                        <div class="app-btn">
                            <a href="#0"><img src="assets/images/icon2@1x.png" alt="Google play"></a>
                            <a href="#0"><img src="assets/images/icon3@1x.png" alt="App Store"></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="banner-image"><img src="assets/images/device1.png" alt="Banner Image"></div>
                </div>
            </div>
        </div>
        <div id="player" class="container-player"></div>
    </section>

    <!-- Obri About -->
    <section class="obri-about" id="section1">
        <div class="container">
            <div class="row align-items-end">
                <div class="col-xl-4 col-lg-6">
                    <div class="about-info">
                        <h2 class="section-title title-two">For Tenants & Landlords</h2>
                        <p>Shelterbaze connects tenants and landlords in one seamless platform. Tenants can discover
                            verified apartments, make secure payments, and manage their rent — while landlords easily list,
                            verify, and track their properties with full transparency.</p>
                    </div>
                </div>
                <div class="col-xl-8 col-lg-6">
                    <div class="about-wrap">
                        <ul class="feature-list">
                            <li><i class="fas fa-building"></i> Browse verified apartments with photos and videos</li>
                            <li><i class="fas fa-comments"></i> Chat directly with landlords or agents</li>
                            <li><i class="fas fa-credit-card"></i> Pay rent securely through Shelterbaze</li>
                            <li><i class="fas fa-piggy-bank"></i> Create a rent savings plan with flexible deposits</li>
                            <li><i class="fas fa-user-check"></i> Verify agents and listings for peace of mind</li>
                            <li><i class="fas fa-chart-line"></i> Manage all your rentals from one dashboard</li>
                        </ul>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Obri Features -->
    <!-- <section class="obri-features" id="section2">
                                                                                                                                <div class="container">
                                                                                                                                    <h2 class="section-title">Awesome Features</h2>
                                                                                                                                    <div class="row">
                                                                                                                                        <div class="col-lg-4 col-md-6">
                                                                                                                                            <div class="feature-item">
                                                                                                                                                <div class="obri-icon"><img src="assets/images/icon10@3x.png" alt="Easy to Use" width="70"></div>
                                                                                                                                                <h2>Easy to Use</h2>
                                                                                                                                                <p>Cum sociis natoque penatibus et magnis dis parturient montes nascetur ridiculus mus. Donec quam
                                                                                                                                                    felis, ultricies nec pellentesque.</p>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                        <div class="col-lg-4 col-md-6">
                                                                                                                                            <div class="feature-item">
                                                                                                                                                <div class="obri-icon"><img src="assets/images/icon11@3x.png" alt="Light Weight" width="70"></div>
                                                                                                                                                <h2>Light Weight</h2>
                                                                                                                                                <p>Cum sociis natoque penatibus et magnis dis parturient montes nascetur ridiculus mus. Donec quam
                                                                                                                                                    felis, ultricies nec pellentesque.</p>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                        <div class="col-lg-4 col-md-6">
                                                                                                                                            <div class="feature-item">
                                                                                                                                                <div class="obri-icon"><img src="assets/images/icon12@3x.png" alt="Advanced System" width="70">
                                                                                                                                                </div>
                                                                                                                                                <h2>Advanced System</h2>
                                                                                                                                                <p>Cum sociis natoque penatibus et magnis dis parturient montes nascetur ridiculus mus. Donec quam
                                                                                                                                                    felis, ultricies nec pellentesque.</p>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    </div>
                                                                                                                                </div>
                                                                                                                            </section> -->

    <!-- Obri Works -->
    <section class="obri-works" id="section3">
        <div class="container">
            <h2 class="section-title">How Does It Work</h2>
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="work-item">
                        <div class="obri-icon">
                            <div class="work-step">Step - 1</div>
                            <div class="obri-table-wrap">
                                <div class="obri-align-wrap">
                                    <img src="assets/images/icon13@3x.png" alt="Download App" width="80">
                                </div>
                            </div>
                        </div>
                        <h2>Download App</h2>
                        <p>Download the app from Playstore and Appstore</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="work-item">
                        <div class="obri-icon">
                            <div class="work-step">Step - 2</div>
                            <div class="obri-table-wrap">
                                <div class="obri-align-wrap">
                                    <img src="assets/images/icon14@3x.png" alt="Create Account" width="80">
                                </div>
                            </div>
                        </div>
                        <h2>Create Account</h2>
                        <p>Register either as a Tenant or Landlord</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="work-item">
                        <div class="obri-icon">
                            <div class="work-step">Step - 3</div>
                            <div class="obri-table-wrap">
                                <div class="obri-align-wrap">
                                    <img src="assets/images/icon15@3x.png" alt="Ready to Work" width="80">
                                </div>
                            </div>
                        </div>
                        <h2>Ready to go</h2>
                        <p>Complete your account setup, and start using the app</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Obri App -->
    <section class="obri-app">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 order-lg-2">
                    <div class="app-info">
                        <h2 class="section-title title-two">Save Towards Your Next Rent</h2>
                        <p>Take control of your rent with a dedicated savings plan designed just for you. Set your target
                            amount and due date, and Shelterbaze safely holds your funds until it’s time to pay your rent —
                            no investments, no risks, just disciplined savings made simple..</p>
                    </div>
                </div>
                <div class="col-lg-7 order-lg-1">
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                            <div class="obri-image"><img src="assets/images/device2.png" alt="App"></div>
                        </div>
                        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                            <div class="obri-image"><img src="assets/images/device2.png" alt="App"></div>
                        </div>
                        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                            <div class="obri-image"><img src="assets/images/device2.png" alt="App"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Obri Testimonials -->
    <section class="obri-testimonials" id="section4">
        <div class="container">
            <h2 class="section-title title-two">What Our Users <br> Say About Us</h2>
            <div class="owl-carousel" data-items="3" data-margin="0" data-loop="true" data-center="true" data-nav="false"
                data-dots="false" data-autoplay="true">
                <div class="item">
                    <div class="testimonial-item">
                        <div class="obri-icon"><img src="assets/images/icon19@3x.png" alt="Quote" width="30"></div>
                        <div class="testimonial-info">
                            <p>Phasellus viverra nulla ut metus varius laoreet quisque rutrum aenean imperdiet.</p>
                            <div class="testimonial-author">
                                <div class="obri-image"><img src="assets/images/author1.png" alt="Ross King"></div>
                                <div class="author-info">
                                    <h6>Ross King</h6>
                                    <p>Project manager</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="item">
                    <div class="testimonial-item">
                        <div class="obri-icon"><img src="assets/images/icon19@3x.png" alt="Quote" width="30"></div>
                        <div class="testimonial-info">
                            <p>Phasellus viverra nulla ut metus varius laoreet quisque rutrum aenean imperdiet.</p>
                            <div class="testimonial-author">
                                <div class="obri-image"><img src="assets/images/author2.png" alt="Emma Paul"></div>
                                <div class="author-info">
                                    <h6>Emma Paul</h6>
                                    <p>Project manager</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="item">
                    <div class="testimonial-item">
                        <div class="obri-icon"><img src="assets/images/icon19@3x.png" alt="Quote" width="30"></div>
                        <div class="testimonial-info">
                            <p>Phasellus viverra nulla ut metus varius laoreet quisque rutrum aenean imperdiet.</p>
                            <div class="testimonial-author">
                                <div class="obri-image"><img src="assets/images/author3.png" alt="Tim  Dunn"></div>
                                <div class="author-info">
                                    <h6>Tim Dunn</h6>
                                    <p>Project manager</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="item">
                    <div class="testimonial-item">
                        <div class="obri-icon"><img src="assets/images/icon19@3x.png" alt="Quote" width="30"></div>
                        <div class="testimonial-info">
                            <p>Phasellus viverra nulla ut metus varius laoreet quisque rutrum aenean imperdiet.</p>
                            <div class="testimonial-author">
                                <div class="obri-image"><img src="assets/images/author2.png" alt="Emma Paul"></div>
                                <div class="author-info">
                                    <h6>Emma Paul</h6>
                                    <p>Project manager</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Obri Support -->
    <section class="obri-support">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="support-info">
                        <h4>Real-time Support</h4>
                        <h2 class="section-title title-two">Having problems? We're here to help!</h2>
                        <p>Our team of support staff and experienced agents are always ready to attend to you</p>
                        <ul>
                            <li>Live chat and phone support</li>
                            <li>Response time in less than 1 hour</li>
                            <li>Email us at support@shelterbaze.com</li>
                        </ul>
                        <a href="#0" class="obri-btn">Contact us</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="obri-icon"><img src="assets/images/icon20@3x.png" alt="Support"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Obri Download -->
    <section class="obri-download" id="section5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="obri-image">
                        <div class="image-wrap">
                            <img src="assets/images/device3.png" alt="App">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="download-info">
                        <h2 class="section-title title-two">Try it for free now</h2>
                        <p>Experience how easy renting can be. Download the Shelterbaze app to explore verified listings,
                            chat with landlords, and start your rent savings journey — all at no cost.</p>
                        <div class="app-btn">
                            <a href="#0"><img src="assets/images/icon2@1x.png" alt="Google play"></a>
                            <a href="#0"><img src="assets/images/icon3@1x.png" alt="App Store"></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Obri Subscribe -->
    <section class="obri-subscribe">
        <div class="container">
            <div class="subscribe-wrap">
                <h2 class="section-title">Join the 500+ customers that are already using our app</h2>
                <!-- <p>Subscribe our newsletter to recive the latest news and exclusive offers every week.</p>
                                        <form>
                                            <div class="form-group">
                                                <input type="email" class="form-control" id="email" placeholder="Type your email here....">
                                            </div>
                                            <input type="submit" class="obri-btn" name="Submit" value="subscribe now!">
                                        </form> -->
            </div>
        </div>
    </section>

    <!-- Obri Contact -->
    <section class="obri-contact" id="section6">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="contact-wrap">
                        <div class="obri-icon"><img src="assets/images/mainlogo.png" alt="Shelterbaze" width="62"></div>
                        <div class="contact-info">
                            <h5>Need help? Contact us at</h5>
                            <ul>
                                <li><i class="fa fa-envelope-o" aria-hidden="true"></i> <a
                                        href="mailto:support@shelterbaze.com">support@shelterbaze.com</a></li>
                                <li><i class="fa fa-headphones" aria-hidden="true"></i> <a href="tel:2347043980460">+234 704
                                        398 0460</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="obri-social">
                        <span>Get social</span>
                        <a href="#0"><i class="fa fa-facebook" aria-hidden="true"></i></a>
                        <a href="#0"><i class="fa fa-twitter" aria-hidden="true"></i></a>
                        <a href="#0"><i class="fa fa-instagram" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
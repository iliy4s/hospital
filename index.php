<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. Kiran Neuro Centre </title>

    <link rel="icon" href="img/klogo-.png" type="image/x-icon">


    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <style>
        :root {
            --bs-primary: #1e3c72;
            --bs-secondary: #88bbcc;
        }

        body {
            font-family: 'Poppins', sans-serif;
            padding-top: 0;
            margin: 0;
            overflow-x: hidden;
        }

        /* Enhanced Navbar Styles */
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.98) !important;
            padding: 0.75rem 0;
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            margin: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar.scrolled {
            padding: 0.5rem 0;
            background: rgba(255, 255, 255, 0.98) !important;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .navbar-brand img {
            height: 55px;
            transition: all 0.3s ease;
        }

        .nav-link {
            color: var(--bs-primary) !important;
            font-weight: 500;
            padding: 1rem !important;
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--bs-secondary) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0.7rem;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background: var(--bs-secondary);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .nav-link:hover::after {
            transform: scaleX(1);
        }

        .top-contact {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .top-contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .top-contact-item i {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .top-social {
            display: flex;
            gap: 1rem;
        }

        .top-social a {
            color: white;
            opacity: 0.9;
            transition: all 0.3s ease;
        }

        .top-social a:hover {
            opacity: 1;
            transform: translateY(-2px);
        }

        .emergency-btn {
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .emergency-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: white;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            padding-top: 90px;
        }

        .service-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-10px);
        }

        .service-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
        }

        .stats-section {
            background-image: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('img/backgrnd.jpg');
            background-size: cover;
            background-position: center;
        }

        .stat-circle {
            width: 120px;
            height: 120px;
            background: var(--bs-secondary);
        }

        .gradient-bg {
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
        }

        footer {
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
        }

        @media (max-width: 991.98px) {
            .navbar {
                padding: 0.5rem 0;
            }

            .hero {
                padding-top: 76px;
            }

            .nav-link {
                padding: 0.5rem 1rem !important;
            }

            .nav-link::after {
                bottom: 0.3rem;
            }
        }
    </style>
</head>
<body>
   
    <!-- Main Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/klogo-.png" alt="Dr. Kiran" class="d-inline-block align-top">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                   
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <!-- <a href="admin_login.php" class="btn btn-outline-primary ms-3 d-none d-lg-inline-block">
                        <i class="fas fa-user-shield me-2"></i>Admin Login
                    </a> -->
                    <a href="slot-booking.php" class="btn emergency-btn ms-3 d-none d-lg-inline-block">
                        Book Appointment
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero d-flex align-items-center" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center text-white">
                    
                    <h1 class="display-3 fw-bold mb-4 animate__animated animate__fadeInUp">
                       Welcome To <br> Dr. Kiran Neuro Centre
                    </h1>
                    <p class="lead mb-5 animate__animated animate__fadeInUp animate__delay-1s">
                        At Dr. Kiran Neuro Centre, we combine advanced medical expertise with compassionate care.
                        Our commitment to your health is reflected in every aspect of our service.
                    </p>
                    <div class="d-flex justify-content-center gap-3 animate__animated animate__fadeInUp animate__delay-2s">
                        <a href="slot-booking.php" class="btn btn-light btn-lg rounded-pill px-4">
                            Book Appointment <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="#contact" class="btn btn-outline-light btn-lg rounded-pill px-4">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5 bg-light" id="services">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold text-primary">Our Services</h2>
                <p class="text-muted">Comprehensive Neurological Care</p>
            </div>
            <div class="row g-4">
                <!-- Service Cards -->
                <div class="col-md-4">
                    <div class="card service-card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="service-icon rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-head-side-virus text-white fa-2x"></i>
                            </div>
                            <h4 class="h5 mb-3">HEADACHE</h4>
                            <p class="text-muted mb-0">
                                • Advanced headache diagnosis<br>
                                • Treatment for migraines<br>
                                • Stress management<br>
                                • Neurological evaluation
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="service-icon rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-brain text-white fa-2x"></i>
                            </div>
                            <h4 class="h5 mb-3">FITS (Seizures)</h4>
                            <p class="text-muted mb-0">
                                • Epilepsy screening & diagnosis<br>
                                • EEG & brain imaging tests<br>
                                • Medication & seizure control management<br>
                                • Emergency care for acute episodes
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="service-icon rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-person-falling text-white fa-2x"></i>
                            </div>
                            <h4 class="h5 mb-3">GIDDINESS</h4>
                            <p class="text-muted mb-0">
                                • Vertigo & balance disorder diagnosis<br>
                                • Inner ear & neurological assessments<br>
                                • Personalized treatment plans<br>
                                • Rehabilitation & therapy sessions
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="service-icon rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-wheelchair-move text-white fa-2x"></i>
                            </div>
                            <h4 class="h5 mb-3">PARALYSIS</h4>
                            <p class="text-muted mb-0">
                                • Stroke rehabilitation programs<br>
                                • Physiotherapy & mobility assistance<br>
                                • Neurotherapy for recovery<br>
                                • Assistive devices & long-term care
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="service-icon rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-temperature-high text-white fa-2x"></i>
                            </div>
                            <h4 class="h5 mb-3">BRAIN FEVER</h4>
                            <p class="text-muted mb-0">
                                • Rapid diagnosis & critical care<br>
                                • Treatment for meningitis & encephalitis<br>
                                • Specialized fever management<br>
                                • Intensive monitoring & infection control
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card border-0 shadow-sm h-100">
                        <div class="card-body p-4 text-center">
                            <div class="service-icon rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center">
                                <i class="fa-solid fa-brain text-white fa-2x"></i>
                            </div>
                            <h4 class="h5 mb-3">MEMORY LOSS</h4>
                            <p class="text-muted mb-0">
                                • Early diagnosis of dementia & Alzheimer's<br>
                                • Cognitive therapy & brain exercises<br>
                                • Medication & lifestyle adjustments<br>
                                • Family counseling & support
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Working Hours Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row row-cols-1 row-cols-lg-3 g-4">
                <div class="col">
                    <div class="card gradient-bg text-white border-0 h-100">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <h4 class="text-center border-bottom border-white border-opacity-25 pb-3 mb-4">Working Hours</h4>
                                <div class="d-flex justify-content-between mb-3 pb-3 border-bottom border-white border-opacity-25">
                                    <div>Monday - Saturday</div>
                                    <div>
                                        <div>9 AM - 1 PM</div>
                                        <div>6 PM - 8 PM</div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mb-4">
                                    <div>Sunday</div>
                                    <div class="text-warning">Closed</div>
                                </div>
                            </div>
                            <div class="text-center">
                                <h6 class="fs-5 mb-3">Need an Appointment?</h6>
                                <div class="d-grid gap-3">
                                    <a href="slot-booking.php" class="btn btn-light rounded-pill fw-bold">ONLINE APPOINTMENT</a>
                                    <a href="#" class="btn btn-outline-light rounded-pill fw-bold">EMERGENCY CALL</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 h-100">
                        <img src="img/hospital-interir.jpg" alt="working hours" class="img-fluid h-100 w-100 object-fit-cover rounded">
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 h-100">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <h1 class="text-primary position-relative pb-2 mb-4">Introduction</h1>
                                <p class="text-muted">Dr. Kiran Neuro Centre is committed to delivering world-class, compassionate care for all neurological needs. We specialize in advanced diagnostics, personalized treatments, and innovative neurorehabilitation, ensuring the best outcomes with a patient-first approach. Your health is our priority—experience expert care with us.</p>

                                <ul class="list-unstyled my-4">
                                    <li class="position-relative ps-4 mb-2 text-muted custom-dash">20+ Years of Experience</li>
                                    <li class="position-relative ps-4 mb-2 text-muted custom-dash">Best Neurology Hospital In Bhimavaram</li>
                                </ul>
                            </div>
                            <div>
                                <h6 class="fs-5 mb-2 text-primary">Dr. Kiran Neuro Centre</h6>
                                <p class="text-muted mb-0">Team of Dr. Kiran Neuro Centre</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
   

    <!-- Contact Section -->
    <section class="py-5 bg-light" id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h2 class="h3 fw-bold mb-4">Get in Touch</h2>
                            <form action="mailto:kiranneurocentre@gmail.com" method="post" enctype="text/plain">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <input type="text" name="name" class="form-control" placeholder="Your Name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="email" name="email" class="form-control" placeholder="Your Email" required>
                                    </div>
                                    <div class="col-12">
                                        <input type="text" name="subject" class="form-control" placeholder="Subject" required>
                                    </div>
                                    <div class="col-12">
                                        <textarea name="message" class="form-control" rows="5" placeholder="Your Message" required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                                            Send Message <i class="fas fa-paper-plane ms-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card gradient-bg text-white border-0 h-100">
                        <div class="card-body p-4">
                            <h2 class="h3 fw-bold mb-4">Contact Information</h2>
                            <div class="d-flex mb-4">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-map-marker-alt fa-2x"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Address</h6>
                                    <p class="mb-0">Juvvalapalem Road, Vantena, near Adda, Bhimavaram, Andhra Pradesh 534202</p>
                                </div>
                            </div>
                            <div class="d-flex mb-4">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-phone fa-2x"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Phone</h6>
                                    <p class="mb-0">+814-385-2529/814-385-2528</p>
                                </div>
                            </div>
                            <div class="d-flex mb-4">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-envelope fa-2x"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Email</h6>
                                    <p class="mb-0">kiranneurocentre@gmail.com</p>
                                </div>
                            </div>
                            <div class="d-flex mb-4">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Working Hours</h6>
                                    <pre class="mb-0">
Mon - Sat: 9:00 AM - 1:00 PM  
           6:00 PM - 8:00 PM</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-5 pb-3 text-white">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <img src="img/klogo-.png" alt="Dr. Kiran" height="60" class="mb-4">
                    <p class="opacity-75">Dr. Kiran Neuro Centre provides expert neurological care with a focus on patient well-being and advanced treatment methods.</p>
                </div>
                <!-- Quick Links -->
                <div class="col-lg-4 col-md-6">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-white text-decoration-none opacity-75">Home</a></li>
                        <li class="mb-2"><a href="#services" class="text-white text-decoration-none opacity-75">Services</a></li>
                        <li class="mb-2"><a href="#contact" class="text-white text-decoration-none opacity-75">Contact</a></li>
                    </ul>
                </div>
                <!-- Services -->
                <div class="col-lg-4 col-md-6">
                    <h5 class="mb-4">Our Services</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none opacity-75">Headache Treatment</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none opacity-75">Seizure Management</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none opacity-75">Paralysis Care</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none opacity-75">Memory Loss Treatment</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 opacity-25">
            <p class="text-center mb-0 opacity-75">&copy; 2024 Dr. Kiran Neuro Centre. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaxCare | Vaccination Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navbar -->
     <div class="main">
    <header>
        <div class="logo">
            <img src="images/final bg logo.png" alt="VaxCare Logo">
            
        </div>
          <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
        <nav>
            <div class="links">
            <a href="index.php">Home</a>
            <a href="#about">About</a>
            <a href="#vaccine">Vaccines</a>
             <a href="#FAQs">FAQs</a>
            <a href="#contact">Contact</a>
            </div>
            <div class="btns">
            <a href="authentication/login.php" class="login-btn">Login</a>
            <a href="authentication/register.php" class="register-btn">Register</a>
            </div>
        </nav>
    </header>
    <section class="hero">
        <div class="hero-content">
            <h1>
                Protecting Lives Through
                <span>Smart Vaccination</span>
            </h1>
            <div class="hero-buttons">
                <a href="authentication/register.php?role=parent" class="primary-btn">
                    Get Started
                </a>
                <a href="#about" class="secondary-btn">
                    Learn More
                </a>
            </div>
        </div>
    </section>
    </div>
    <section class="about-us" id="about">
  <div class="about-head">
    <h2>About VaxCare</h2>
    <p>
      VaxCare is a vaccination management system that brings parents, hospitals
      and administrators together, so every vaccine is booked, recorded and
      tracked in one simple place.
    </p>
  </div>

  <div class="about-grid">

    <!-- LEFT -->
    <div class="about-col about-left">
      <div class="about-point">
        <span class="about-icon"><i class="fa-solid fa-notes-medical " style="color: #e08f00"></i></span>
        <h3>Digital Records</h3>
        <p>Keep every vaccination record safe and easy to find.</p>
      </div>
      <div class="about-point">
        <span class="about-icon"><i class="fa-regular fa-bell " style="color: #e08f00"></i></span>
        <h3>Timely Reminders</h3>
        <p>Never miss a due date for your child's vaccines.</p>
      </div>
    </div>

    <!-- CENTER IMAGE -->
    <div class="about-photo">
      <img src="images/baby.jpg" alt="VaxCare vaccination care">
    </div>

    <!-- RIGHT -->
    <div class="about-col about-right">
      <div class="about-point">
        <span class="about-icon"><i class="fa-solid fa-hospital"style="color: #e08f00"></i></span>
        <h3>Trusted Hospitals</h3>
        <p>Book appointments with registered healthcare providers.</p>
      </div>
      <div class="about-point">
        <span class="about-icon"><i class="fa-solid fa-shield-halved" style="color: #e08f00"></i></span>
        <h3>Safe &amp; Secure</h3>
        <p>Your health data stays private and protected.</p>
      </div>
    </div>

  </div>

  <!-- STATS -->
  <div class="about-stats">
    <div><h3>500+</h3><p>Vaccinations</p></div>
    <div><h3>25+</h3><p>Vaccine Types</p></div>
    <div><h3>1000+</h3><p>Registered Users</p></div>
  </div>
</section>

  <section class="features" id="vaccine">
    <h2>Everything You Need for Vaccination Care</h2>

    <div class="cards">
      <div class="card" tabindex="0">
        <div class="card-img"><img src="images/parents.jpg" alt="Parent Portal"></div>
        <div class="card-body">
          <h3>Parent Portal</h3>
          <p>Manage your child's vaccination details, dates, bookings and vaccination reports.</p>
          <a href="authentication/register.php?role=parent" class="btn ">Register as Parent</a>
        </div>
      </div>

      <div class="card" tabindex="0">
        <div class="card-img"><img src="images/covid.jpg" alt="Hospital Portal"></div>
        <div class="card-body">
          <h3>Hospital Portal</h3>
          <p>Manage appointments and update vaccination status efficiently.</p>
             <a href="authentication/register.php?role=hospital" class="btn">Register as Hospital</a>
        </div>
      </div>

      <div class="card" tabindex="0">
        <div class="card-img"><img src="images/admin.jpg" alt="Admin Portal"></div>
        <div class="card-body">
          <h3>Admin Portal</h3>
          <p>Manage children, hospitals, vaccines, requests and vaccination reports.</p>
          <a href="authentication/login.php" class="btn">Admin Login</a>
        </div>
      </div>
    </div>
  </section>
  <section class="faq-section" id="FAQs">
      <h1>Frequently asked questions</h1>
      <p>Stuck on something?We are here to help will all your questions and answers.</p>
        <div class="faq-list">

            <details class="faq-item" open>
                <summary>Who can register? <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="faq-answer">Parents and hospitals. Choose your role on the registration page.</div>
            </details>

            <details class="faq-item">
                <summary>Is registration free? <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="faq-answer">Yes, creating an account on VaxCare is free for both parents and hospitals.</div>
            </details>

            <details class="faq-item">
                <summary>How do I book a vaccine? <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="faq-answer">Log in to your parent account, select your child, choose a vaccine and a registered hospital, then pick an available date to book.</div>
            </details>

            <details class="faq-item">
                <summary>Where is my child's record? <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="faq-answer">After logging in, open your dashboard to see your child's complete vaccination history, upcoming doses and reports.</div>
            </details>

            <details class="faq-item">
                <summary>What if my child missed a dose? <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="faq-answer">You can rebook the missed dose from your dashboard. Please consult your doctor or hospital for the correct schedule to follow.</div>
            </details>

            <details class="faq-item">
                <summary>Is this medical advice? <i class="fa-solid fa-chevron-down"></i></summary>
                <div class="faq-answer">No. VaxCare helps you book, record and track vaccinations. For medical advice, always speak to a qualified healthcare professional.</div>
            </details>

        </div>
    </section>
    <?php
// Contact form handling (server-side validation)
$name = $email = $message = '';
$status = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // TODO: save to database (includes/config.php) or send with mail()
        $status = 'Thank you! Your message has been sent.';
        $name = $email = $message = '';
    }
}
?>

    <section class="contact-section" id="contact">
       <div class="contact-info">
                <h2>Get in touch</h2>
                <ul>
                    <li>  <i class="fa-solid fa-clock"></i></i><strong>SCHEDULE</strong>Sun-Fri:9AM-6PM</li>
                    <li> <i class="fa-solid fa-location-dot"></i><strong>LOCATION</strong>Karachi,Pakistan</li>
                    <li> <i class="fa-solid fa-envelope"></i><strong>OUR MAIL</strong>Support@gmail.com</li>
                </ul>
            </div>
        <div class="contact-wrap">
          <div><img src="images/left 2.jpg" alt=""></div>
            <div class="contact-card">
                <?php if ($status): ?>
                    <div class="form-alert success"><?= htmlspecialchars($status) ?></div>
                <?php elseif ($error): ?>
                    <div class="form-alert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="index.php#contact">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>

                    <label for="message">Message</label>
                    <textarea id="message" name="message" required><?= htmlspecialchars($message) ?></textarea>

                    <button type="submit">Send message</button>
                </form>
            </div>

        </div>
    </section>


 
<footer class="vax-footer">

    <div class="footer-content">

        <!-- LOGO -->
        <div class="footer-brand">

            <img src="images/final bg logo.png"
                 alt="VaxCare Logo">

            <p>Vaccination Management System</p>

        </div>


        <!-- QUICK LINKS -->
        <div class="footer-column">

            <h3>QUICK LINKS</h3>
            <span class="footer-line"></span>

            <a href="index.php">Home</a>
            <a href="pages/about.php">About</a>
            <a href="pages/vaccines.php">Vaccines</a>
            <a href="pages/contact.php">Contact</a>

        </div>


        <!-- VACCINES -->
        <div class="footer-column vaccines-column">

            <h3>VACCINES</h3>
            <span class="footer-line"></span>

            <a href="#">Child Immunization</a>
            <a href="#">Adult Vaccine</a>
            <a href="#">COVID-19</a>
            <a href="#">Flu Shot</a>
            <a href="#">Hepatitis B</a>
            <a href="#">Polio</a>

        </div>
        <div class="footer-column">

            <h3>ACCOUNT </h3>
            <span class="footer-line"></span>

            <a href="authentication/login.php">Login</a>
            <a href="authentication/register.php">Register</a>
             </div>
        
<div class="footer-social">

    <h3>FOLLOW US</h3>

    <span class="footer-line"></span>

    <div class="social-icons">

        <a href="#" aria-label="Facebook">
            <i class="fa-brands fa-facebook-f"></i>
        </a>

        <a href="#" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
        </a>

        <a href="#" aria-label="YouTube">
            <i class="fa-brands fa-youtube"></i>
        </a>

        <a href="#" aria-label="LinkedIn">
            <i class="fa-brands fa-linkedin-in"></i>
        </a>

    </div>

</div>
    </div>   
    <div class="footer-bottom">

        <p>
            © 2026 VaxCare. All rights reserved.
        </p>

    </div>

</footer>


            
 
 <script src="js/script.js"></script>   
</body>
</html>
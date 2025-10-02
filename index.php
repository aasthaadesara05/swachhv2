<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swachh - Smart Waste Segregation</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <header class="hero">
        <nav class="nav">
            <div class="brand">
                <div class="logo">♻️</div>
                <div class="brand-text">
                    <h1>Swachh</h1>
                    <span>Smart Waste Segregation</span>
                </div>
            </div>
            <div class="nav-actions">
                <a href="login.php" class="btn btn-ghost">Log in</a>
                <a href="signup.php" class="btn btn-cta">Sign up</a>
            </div>
        </nav>

        <div class="hero-content">
            <h2 class="headline">Turn everyday waste into community progress</h2>
            <p class="subhead">Track segregation, earn credits, reduce penalties, and build a cleaner city together. Real-time monitoring for residents, workers, and administrators.</p>
            <div class="cta-row">
                <a href="signup.php" class="btn btn-cta btn-lg">Get started</a>
                <a href="login.php" class="btn btn-outline btn-lg">I already have an account</a>
            </div>
            <div class="trust-row">
                <div class="trust-badge">ISO 14001 Inspired</div>
                <div class="trust-badge">Privacy-first</div>
                <div class="trust-badge">Open Data Ready</div>
            </div>
        </div>

        <div class="hero-stats">
            <div class="stat">
                <div class="stat-value">95%</div>
                <div class="stat-label">Segregation Accuracy</div>
            </div>
            <div class="stat">
                <div class="stat-value">1.2k</div>
                <div class="stat-label">Active Households</div>
            </div>
            <div class="stat">
                <div class="stat-value">64t</div>
                <div class="stat-label">Waste Diverted</div>
            </div>
        </div>
    </header>

    <main>
        <section class="features">
            <div class="section-header">
                <h3>Why Swachh?</h3>
                <p>Designed with people in mind — simple, transparent, and rewarding.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h4>See your impact</h4>
                    <p>Personal dashboards for households and workers with weekly insights and easy-to-read trends.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h4>Credits, not confusion</h4>
                    <p>Earn green credits for consistent segregation. Clear rules, no guesswork, fair outcomes.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h4>Stay in the loop</h4>
                    <p>Friendly alerts and reminders — from collection schedules to policy updates — right when you need them.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h4>Privacy by default</h4>
                    <p>Your data stays yours. We use only what’s necessary to make the system work for you.</p>
                </div>
            </div>
        </section>

        <section class="how-it-works">
            <div class="section-header">
                <h3>How it works</h3>
                <p>Three simple steps to cleaner neighborhoods.</p>
            </div>
            <ol class="steps">
                <li>
                    <span class="step-num">1</span>
                    <div class="step-body">
                        <h5>Create your account</h5>
                        <p>Sign up as a resident or worker. It takes less than two minutes.</p>
                    </div>
                </li>
                <li>
                    <span class="step-num">2</span>
                    <div class="step-body">
                        <h5>Segregate and report</h5>
                        <p>Follow simple segregation guidelines. The system logs pickups and quality checks.</p>
                    </div>
                </li>
                <li>
                    <span class="step-num">3</span>
                    <div class="step-body">
                        <h5>Earn and improve</h5>
                        <p>See credits, tips, and community impact. Small steps, big change.</p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="audiences">
            <div class="audience-grid">
                <div class="aud-card">
                    <h4>For Residents</h4>
                    <ul>
                        <li>Track your weekly segregation score</li>
                        <li>Get reminders before collection</li>
                        <li>Redeem credits with local partners</li>
                    </ul>
                    <a href="signup.php" class="btn btn-outline">Start as Resident</a>
                </div>
                <div class="aud-card">
                    <h4>For Workers</h4>
                    <ul>
                        <li>Smarter routes and pickup logs</li>
                        <li>Less paperwork, more clarity</li>
                        <li>Recognition for consistent service</li>
                    </ul>
                    <a href="signup.php" class="btn btn-outline">Start as Worker</a>
                </div>
                <!-- <div class="aud-card">
                    <h4>For Admins</h4>
                    <ul>
                        <li>Ward-level analytics</li>
                        <li>Policy and notification tools</li>
                        <li>Compliance tracking</li>
                    </ul>
                    <a href="admin/index.php" class="btn btn-outline">Go to Admin</a>
                </div> -->
            </div>
        </section>

        <!-- <section class="cta-banner">
            <h3>Ready to make your area Swachh?</h3>
            <p>Join thousands building cleaner, greener communities every day.</p>
            <div class="cta-row">
                <a href="signup.php" class="btn btn-cta btn-lg">Create account</a>
                <a href="login.php" class="btn btn-ghost btn-lg">Log in</a>
            </div>
        </section> -->
    </main>

    <footer class="site-footer">
        <div class="footer-grid">
            <div>
                <div class="brand small">
                    <div class="logo">♻️</div>
                    <div class="brand-text">
                        <h1>Swachh</h1>
                        <span>Waste segregation made simple</span>
                    </div>
                </div>
                <p class="fineprint">Built for real people and real cities. No jargon, just cleaner streets.</p>
            </div>
            <div>
                <h5>Links</h5>
                <ul class="footer-links">
                    <li><a href="signup.php">Sign up</a></li>
                    <li><a href="login.php">Log in</a></li>
                    <li><a href="admin/index.php">Admin</a></li>
                </ul>
            </div>
            <div>
                <h5>Contact</h5>
                <ul class="footer-links">
                    <li><a href="#">support@swachh.local</a></li>
                    <li><a href="#">Report an issue</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">© <?php echo date('Y'); ?> Swachh. All rights reserved.</div>
    </footer>
    <script>
        // Smooth scroll for same-page anchors if added later
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                const target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>

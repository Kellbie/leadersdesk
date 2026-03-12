<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<?php $page_title = "Welcome"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeaderDesk - MLM Team Management Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #1a1a1a;
            line-height: 1.5;
            overflow-x: hidden;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navigation */
        .navbar {
            padding: 24px 0;
            border-bottom: 1px solid #eaeaea;
            background: white;
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #1a1a1a;
            text-decoration: none;
        }

        .logo span {
            color: #000;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 8px;
            margin-left: 4px;
        }

        .nav-links {
            display: flex;
            gap: 32px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #4a4a4a;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #000;
        }

        .nav-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid #eaeaea;
            color: #1a1a1a;
        }

        .btn-outline:hover {
            background: #f5f5f5;
            border-color: #d0d0d0;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        /* Hero Section */
        .hero {
            padding: 80px 0 60px;
            background: linear-gradient(to bottom, #ffffff, #fafafa);
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-left h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -2px;
            margin-bottom: 24px;
            color: #1a1a1a;
        }

        .hero-left p {
            font-size: 18px;
            color: #666;
            margin-bottom: 32px;
            max-width: 500px;
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            margin: 40px 0;
        }

        .stat-item h3 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .stat-item p {
            font-size: 14px;
            color: #888;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
        }

        .btn-large {
            padding: 16px 32px;
            font-size: 16px;
        }

        .hero-right {
            position: relative;
        }

        .hero-image {
            background: #f5f5f5;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
            border: 1px solid #eaeaea;
        }

        .dashboard-preview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .preview-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -2px rgba(0,0,0,0.05);
        }

        .preview-card .label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .preview-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .preview-card .trend {
            font-size: 12px;
            color: #10b981;
            margin-top: 4px;
        }

        /* Trust Bar */
        .trust-bar {
            padding: 40px 0;
            border-top: 1px solid #eaeaea;
            border-bottom: 1px solid #eaeaea;
            background: white;
        }

        .trust-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            opacity: 0.6;
        }

        .trust-logos span {
            font-size: 18px;
            font-weight: 600;
            color: #4a4a4a;
            letter-spacing: -0.5px;
        }

        /* Features Section */
        .features {
            padding: 80px 0;
            background: white;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 60px;
        }

        .section-header h2 {
            font-size: 40px;
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 16px;
            color: #1a1a1a;
        }

        .section-header p {
            font-size: 18px;
            color: #666;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .feature-card {
            background: #fafafa;
            padding: 40px 30px;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
            background: white;
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 24px;
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a1a1a;
        }

        .feature-card p {
            color: #666;
            font-size: 15px;
            line-height: 1.6;
        }

        /* How It Works */
        .how-it-works {
            padding: 80px 0;
            background: #fafafa;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: white;
            border: 2px solid #eaeaea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .step h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .step p {
            color: #666;
            font-size: 15px;
            max-width: 250px;
            margin: 0 auto;
        }

        /* Testimonials */
        .testimonials {
            padding: 80px 0;
            background: white;
        }

        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        .testimonial-card {
            background: #fafafa;
            padding: 40px;
            border-radius: 24px;
            border: 1px solid #eaeaea;
        }

        .testimonial-card p {
            font-size: 18px;
            line-height: 1.6;
            color: #1a1a1a;
            margin: 20px 0;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            background: #eaeaea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #4a4a4a;
        }

        .author-info h4 {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .author-info p {
            font-size: 14px;
            color: #888;
            margin: 0;
        }

        /* Pricing */
        .pricing {
            padding: 80px 0;
            background: #fafafa;
        }

        .pricing-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            max-width: 800px;
            margin: 40px auto 0;
        }

        .pricing-card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            border: 1px solid #eaeaea;
            position: relative;
        }

        .pricing-card.featured {
            border: 2px solid #1a1a1a;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
        }

        .popular-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a1a;
            color: white;
            padding: 4px 16px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .pricing-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .pricing-header h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .price {
            font-size: 48px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .price span {
            font-size: 16px;
            color: #888;
            font-weight: 400;
        }

        .pricing-features {
            list-style: none;
            margin: 30px 0;
        }

        .pricing-features li {
            padding: 12px 0;
            border-bottom: 1px solid #eaeaea;
            color: #4a4a4a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pricing-features li:last-child {
            border-bottom: none;
        }

        .pricing-features li::before {
            content: "✓";
            color: #10b981;
            font-weight: 600;
        }

        .btn-block {
            width: 100%;
            text-align: center;
            display: block;
        }

        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            background: #1a1a1a;
            color: white;
        }

        .cta-content {
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .cta-content h2 {
            font-size: 40px;
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 20px;
        }

        .cta-content p {
            font-size: 18px;
            opacity: 0.8;
            margin-bottom: 40px;
        }

        .cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        .btn-white {
            background: white;
            color: #1a1a1a;
        }

        .btn-white:hover {
            background: #f5f5f5;
        }

        .btn-outline-white {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-outline-white:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Footer */
        .footer {
            padding: 60px 0 30px;
            background: white;
            border-top: 1px solid #eaeaea;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-about p {
            color: #666;
            margin: 20px 0;
            font-size: 14px;
            max-width: 300px;
        }

        .social-links {
            display: flex;
            gap: 16px;
        }

        .social-links a {
            color: #4a4a4a;
            text-decoration: none;
            font-size: 20px;
        }

        .footer-links h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #1a1a1a;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #eaeaea;
            color: #888;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-left p {
                margin: 0 auto 32px;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-buttons {
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-content {
                flex-direction: column;
                gap: 20px;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
            }

            .nav-buttons {
                width: 100%;
                flex-direction: column;
            }

            .hero-left h1 {
                font-size: 40px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .steps {
                grid-template-columns: 1fr;
            }

            .testimonial-grid {
                grid-template-columns: 1fr;
            }

            .pricing-cards {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .hero-stats {
                flex-direction: column;
                gap: 20px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="/" class="logo">
                    LeaderDesk<span>.co</span>
                </a>
                
                <div class="nav-links">
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How it Works</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#testimonials">Testimonials</a>
                    <div class="nav-buttons">
                        <a href="login.php" class="btn btn-outline">Sign In</a>
                        <a href="register.php" class="btn btn-primary">Start Free</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-left fade-in">
                    <h1>Finally -Team Management Platform for Network Marketing Leaders.</h1>
                    <p>Stop running your team on scattered WhatsApp chats, Organise your downlines, track prospects, train recruits, and grow faster with LeaderDesk.</p>
                    
                    <div class="hero-stats">
                        <div class="stat-item">
                            <h3>10,000+</h3>
                            <p>Active Leaders</p>
                        </div>
                        <div class="stat-item">
                            <h3>250K+</h3>
                            <p>Team Members</p>
                        </div>
                        <div class="stat-item">
                            <h3>2 Months</h3>
                            <p>Free Trial</p>
                        </div>
                    </div>
                    
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-primary btn-large">Start Free</a>
                    </div>
                </div>
                
                <div class="hero-right fade-in">
                    <div class="hero-image">
                        <div class="dashboard-preview">
                            <div class="preview-card">
                                <div class="label">Total Members</div>
                                <div class="value">128</div>
                                <div class="trend">↑ 12% this month</div>
                            </div>
                            <div class="preview-card">
                                <div class="label">Active Now</div>
                                <div class="value">85</div>
                                <div class="trend">67% engagement</div>
                            </div>
                            <div class="preview-card">
                                <div class="label">Prospects</div>
                                <div class="value">34</div>
                                <div class="trend">8 new today</div>
                            </div>
                            <div class="preview-card">
                                <div class="label">Sales Volume</div>
                                <div class="value">₦2.4M</div>
                                <div class="trend">↑ 23%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Bar -->
    <div class="trust-bar">
        <div class="container">
            <div class="trust-logos">
                <span>Amway</span>
                <span>Herbalife</span>
                <span>Forever Living</span>
                <span>Mary Kay</span>
                <span>Avon</span>
                <span>Tupperware</span>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <h2>Everything you need to lead</h2>
                <p>Replace 5 different tools with one simple platform designed for MLM leaders</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Team Management</h3>
                    <p>Visualize your entire downline in an interactive tree. Track performance, ranks, and activity at a glance.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Prospect Pipeline</h3>
                    <p>Drag-and-drop pipeline to move prospects through stages. Never lose track of a lead again.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">✅</div>
                    <h3>Task Management</h3>
                    <p>Assign tasks to team members with deadlines and points. Keep everyone accountable and motivated.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Real-time Analytics</h3>
                    <p>See exactly how your team is performing with live dashboards, leaderboards, and progress tracking.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Training Center</h3>
                    <p>Upload training materials, create tests, and track completion. Build a learning culture in your team.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🏆</div>
                    <h3>Gamification</h3>
                    <p>Activity scores, badges, and leaderboards make growth fun. Motivate your team through friendly competition.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Get started in minutes</h2>
                <p>No technical skills required. Just sign up and invite your team.</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create your team</h3>
                    <p>Sign up for a free 2-month trial. Set up your team name and branding.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Invite members</h3>
                    <p>Add your existing team members or send them an invite link to join.</p>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Start leading</h3>
                    <p>Create tasks, track prospects, upload training, and watch your team grow.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>Trusted by MLM leaders</h2>
                <p>Join thousands of leaders who've transformed how they manage their teams</p>
            </div>
            
            <div class="testimonial-grid">
                <div class="testimonial-card">
                    <p>"LeaderDesk replaced 4 different tools I was using. Now I manage my entire team of 200+ from my phone. The prospect pipeline alone saved me hours every week."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">MJ</div>
                        <div class="author-info">
                            <h4>Mary Johnson</h4>
                            <p>Diamond Director • 3 years in MLM</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial-card">
                    <p>"The gamification features transformed my team's motivation. Activity scores and leaderboards created healthy competition. Our sales went up 40% in 2 months."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">DS</div>
                        <div class="author-info">
                            <h4>David Smith</h4>
                            <p>Regional VP • Team of 500+</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="pricing">
        <div class="container">
            <div class="section-header">
                <h2>Simple, transparent pricing</h2>
                <p>Start with a 2-month free trial. No credit card required.</p>
            </div>
            
            <div class="pricing-cards">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Free Trial</h3>
                        <div class="price">$0<span>/2 months</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li>Full access to all features</li>
                        <li>Up to 50 team members</li>
                        <li>Unlimited prospects</li>
                        <li>Basic analytics</li>
                        <li>Email support</li>
                    </ul>
                    <a href="register.php" class="btn btn-outline btn-block">Start Trial</a>
                </div>
                
                <div class="pricing-card featured">
                    <div class="popular-badge">MOST POPULAR</div>
                    <div class="pricing-header">
                        <h3>Pro</h3>
                        <div class="price">$29<span>/month</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li>Everything in Free</li>
                        <li>Unlimited team members</li>
                        <li>Advanced analytics</li>
                        <li>Priority support</li>
                        <li>Custom branding</li>
                        <li>API access</li>
                    </ul>
                    <a href="register.php" class="btn btn-primary btn-block">Start Free Trial</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to transform your MLM business?</h2>
                <p>Join thousands of leaders who've already made the switch from spreadsheets to LeaderDesk.</p>
                <div class="cta-buttons">
                    <a href="register.php" class="btn btn-white btn-large">Start 2-Month Free Trial →</a>
                    <a href="#demo" class="btn btn-outline-white btn-large">Schedule Demo</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <a href="/" class="logo">LeaderDesk<span>.co</span></a>
                    <p>The modern operating system for MLM leaders. Built by leaders, for leaders.</p>
                    <div class="social-links">
                        <a href="#">𝕏</a>
                        <a href="#">in</a>
                        <a href="#">📱</a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Product</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#pricing">Pricing</a></li>
                        <li><a href="#demo">Demo</a></li>
                        <li><a href="#security">Security</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="#about">About</a></li>
                        <li><a href="#blog">Blog</a></li>
                        <li><a href="#careers">Careers</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="#help">Help Center</a></li>
                        <li><a href="#guides">Guides</a></li>
                        <li><a href="#community">Community</a></li>
                        <li><a href="#status">Status</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>© 2024 LeaderDesk. All rights reserved. Made with ❤️ for MLM leaders.</p>
            </div>
        </div>
    </footer>

    <!-- Smooth Scroll -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
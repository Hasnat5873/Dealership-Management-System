<?php
// No server-side PHP logic is required as per the request, so this file simply outputs the HTML content
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealership Management System - Home</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- GSAP CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #F8F1F1, #E6ECEF);
            overflow-x: hidden;
            min-width: 1440px;
            margin: 0;
        }
          /* Tailwind Custom Animations */
@layer utilities {
    @keyframes fadeInUp {
        0% { opacity: 0; transform: translateY(20px);}
        100% { opacity: 1; transform: translateY(0);}
    }
    .animate-fadeInUp { animation: fadeInUp 1s ease-out forwards; }

    @keyframes fadeInRight {
        0% { opacity: 0; transform: translateX(30px);}
        100% { opacity: 1; transform: translateX(0);}
    }
    .animate-fadeInRight { animation: fadeInRight 1s ease-out forwards; }

    @keyframes blob {
        0%, 100% { transform: translate(0, 0) scale(1);}
        33% { transform: translate(30px, -50px) scale(1.1);}
        66% { transform: translate(-20px, 20px) scale(0.9);}
    }
    .animate-blob { animation: blob 8s infinite; }
    .animate-pulse-slow { animation: pulse 4s infinite; }
    .animation-delay-2000 { animation-delay: 2s; }
}

        /* Navbar Glassy Premium Look */
        .navbar {
            background: #0A4D68; 
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            position: sticky;
            top: 0;
            width: 100%;
            z-index: 50;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }
        /* Logo */
        .navbar-logo img {
            transition: transform 0.4s ease, filter 0.4s ease;
            border-radius: 0.75rem;
        }
        .navbar-logo img:hover {
            transform: scale(1.1) rotate(2deg);
            filter: brightness(1.2) drop-shadow(0 0 8px rgba(255,255,255,0.6));
        }
        /* Navigation Links */
        .nav-link {
            position: relative;
            font-size: 1.1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            color: #f1f5f9;
            transition: all 0.35s ease;
            padding: 0.25rem 0;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0%;
            height: 2px;
            background: white;
            bottom: -4px;
            left: 0;
            transition: width 0.35s ease;
            border-radius: 5px;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .nav-link:hover {
            color: #FFD700;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.6);
        }
        /* Premium Login Button */
        .btn-login {
            padding: 0.5rem 1.2rem;
            border-radius: 999px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid white;
            backdrop-filter: blur(6px);
        }
        .btn-login:hover {
            background: rgba(255, 215, 0, 0.15);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
            transform: translateY(-2px);
        }
        /* Premium Signup Button */
        .btn-signup {
            padding: 0.5rem 1.4rem;
            border-radius: 999px;
            font-weight: 600;
            background: white;
            color: #0A192F;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(255, 165, 0, 0.4);
        }
        .btn-signup:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 0 20px rgba(255, 165, 0, 0.7);
        }
        /* Banner Section */
        .banner {
            background: #fff;
            padding: 4rem 0;
            border-radius: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .banner-content {
            max-width: 50%;
            padding: 2rem;
        }
        .banner-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            color: #0A4D68;
            text-shadow: 1px 1px 5px rgba(0,0,0,0.2);
        }
        .banner-content p {
            font-size: 1.5rem;
            color: #4A4A4A;
            margin-top: 1rem;
        }
     
        /* About Section */
        .about-section {
            background: #F8F1F1;
            padding: 5rem 0;
        }
        .about-image img {
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 100%;
            height: auto;
        }
        .about-content h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0A4D68;
        }
        .about-content p {
            font-size: 1.25rem;
            color: #4A4A4A;
            margin-top: 1.5rem;
        }
        .about-btn {
            background: #0A4D68;
            color: #F8F1F1;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 9999px;
            margin-top: 2rem;
            transition: background 0.3s ease, transform 0.3s ease;
        }
        .about-btn:hover {
            background: #088395;
            transform: translateY(-2px);
        }
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: #F8F1F1;
            border-radius: 1.5rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            color: #0A4D68;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .modal-close:hover {
            transform: scale(1.2);
        }
        /* What is Dealership Section */
        .dealership-section {
            background: linear-gradient(135deg, #0A4D68, #088395);
            color: #F8F1F1;
            padding: 5rem 0;
           
            margin: 2rem 0;
        }
        .dealership-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
        }
        .dealership-section p {
            font-size: 1.25rem;
            max-width: 800px;
            margin: 2rem auto;
            text-align: center;
        }
        /* What We Provide Section */
        .features-section {
            padding: 5rem 0;
            background: #fff;
            border-radius: 2rem;
        }
        .feature-card {
            background: #F8F1F1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        .feature-card:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 30px rgba(0,0,0,0.25);
        }
        .feature-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0A4D68;
        }
        .feature-card i {
            font-size: 1.8rem;
            color: #D4A017;
            margin-bottom: 0.5rem;
        }
        /* Reviews Section */
        .reviews-section {
            padding: 5rem 0;
             background: linear-gradient(90deg, #0A4D68, #088395);
           
        }
        .review-card {
            background: #fff;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .review-card:hover {
            transform: translateY(-5px);
        }
        .review-card img {
            border-radius: 50%;
            width: 60px;
            height: 60px;
        }
        .review-stars i {
            color: #D4A017;
            font-size: 1.2rem;
        }
        .features-section {
        position: relative;
        overflow: hidden;
    }
    .feature-card {
        position: relative;
        z-index: 1;
    }
    .animate-fade-in {
        animation: fadeIn 1s ease-out;
    }
    .animate-slide-up {
        animation: slideUp 0.5s ease-out forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        to { opacity: 1; transform: translateY(0); }
    }
    /* Responsive Adjustments */
    @media (max-width: 640px) {
        .feature-card div {
           width:40px;
        height:40px;       
     }
        .feature-card i {
            font-size: 3xl;
        }
        .feature-card h3 {
            font-size: lg;
        }
    }
    /* Custom Animations */
@layer utilities {
    @keyframes fadeInLeft {
        0% { opacity: 0; transform: translateX(-30px); }
        100% { opacity: 1; transform: translateX(0); }
    }
    .animate-fadeInLeft { animation: fadeInLeft 1s ease-out forwards; }

    @keyframes fadeInRight {
        0% { opacity: 0; transform: translateX(30px); }
        100% { opacity: 1; transform: translateX(0); }
    }
    .animate-fadeInRight { animation: fadeInRight 1s ease-out forwards; }

    @keyframes blob {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(30px, -50px) scale(1.1); }
        66% { transform: translate(-20px, 20px) scale(0.9); }
    }
    .animate-blob { animation: blob 8s infinite; }
    .animate-pulse-slow { animation: pulse 4s infinite; }
}

        /* Footer */
        .footer {
            background: linear-gradient(90deg, #0A4D68, #088395);
            color: #F8F1F1;
            padding: 4rem 2rem;
            border-top-left-radius: 2rem;
            border-top-right-radius: 2rem;
        }
        .footer a {
            color: #D4A017;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: #b38712;
        }
        .footer-social i {
            font-size: 1.75rem;
            margin: 0 0.75rem;
            transition: transform 0.3s ease;
        }
        .footer-social i:hover {
            transform: scale(1.2);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <!-- Logo -->
            <div class="navbar-logo  bg-white rounded-full ">
                <a href="#"><img src="./assets/Gemini_Generated_Image_p9aeb5p9aeb5p9ae-removebg-preview.png" alt="DMS Logo" class="h-10 w-10"></a>
            </div>
            <!-- Links -->
            <div class="flex space-x-8 items-center">
                <a href="#" class="nav-link">Home</a>
                <a href="#faq" class="nav-link">FAQ</a>
                <a href="login.php" class="btn-login">Login</a>
                <a href="signup.php" class="btn-signup">Signup</a>
            </div>
        </div>
    </nav>

    <!-- Banner Section -->
  <section class="banner relative bg-white overflow-hidden">
    <div class="container mx-auto px-6 py-20 flex flex-col-reverse md:flex-row items-center relative z-10">
        
        <!-- Banner Text Content -->
        <div class="banner-content text-center md:text-left md:w-1/2 animate-fadeInUp">
            <h1 class="text-5xl md:text-6xl font-bold mb-6 leading-tight text-[#088395]">
                Welcome to DMS
            </h1>
            <p class="text-lg md:text-xl text-gray-600 mb-8">
                Transform your dealership with our <span class="font-semibold text-gray-800">powerful management tools</span>.
            </p>
            <div class="flex justify-center md:justify-start space-x-4">
    <!-- Get Started Button -->
    <a href="signup.php"
       class="px-6 py-3 bg-[#088395] text-white font-bold rounded-full shadow-lg transition transform hover:scale-105 hover:shadow-2xl">
        Get Started
    </a>

    <!-- Learn More Button -->
    <a href="#faq"
       class="px-6 py-3 bg-[#088395] text-white font-semibold rounded-full shadow-lg transition transform hover:scale-105 hover:shadow-2xl">
        Learn More
    </a>
</div>

        </div>

        <!-- Banner Image -->
        <div class="banner-image md:w-1/2 relative animate-fadeInRight mb-10 md:mb-0">
            <div class="relative w-full h-96 md:h-[28rem] lg:h-[32rem] rounded-3xl  shadow-2xl">
                <img src="./assets/variety-people-multitasking-3d-cartoon-scene.jpg" alt="Dealership" class="w-full h-full object-cover rounded-l-3xl object-center transform hover:scale-105 transition duration-500">
                <!-- Fancy Shapes Overlay -->
                <div class="absolute -top-16 -left-16 w-40 h-40 bg-gradient-to-tr from-blue-600 to-blue-400 rounded-full opacity-30 animate-pulse-slow"></div>
                <div class="absolute -bottom-16 -right-16 w-56 h-56 bg-gradient-to-tr from-blue-500 to-teal-300 rounded-full opacity-20 animate-pulse-slow"></div>
            </div>
        </div>

    </div>

    <!-- Background Fancy Shapes -->
    <div class="absolute top-0 left-0 w-full h-full pointer-events-none overflow-hidden">
        <div class="absolute -top-20 -left-20 w-72 h-72 bg-gradient-to-tr from-blue-500 to-teal-400 rounded-full opacity-10 animate-blob"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-gradient-to-tr from-blue-400 to-teal-200 rounded-full opacity-10 animate-blob animation-delay-2000"></div>
    </div>
</section>


    <!-- About Section -->
    <section class="about-section">
        <div class="container mx-auto px-4 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <div class="about-image animate-about-image">
                <img src="./assets/3d-character-emerging-from-smartphone.jpg" alt="About DMS">
            </div>
            <div class="about-content animate-about-content">
                <h2>About Our System</h2>
                <p>
                    The Dealership Management System (DMS) is a modern solution crafted to streamline dealership operations. It simplifies inventory management, sales tracking, and financial reporting, empowering businesses with efficiency and precision.
                </p>
                <button class="about-btn" onclick="openModal()">Learn More</button>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <div id="aboutModal" class="modal">
        <div class="modal-content animate-modal">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2 class="text-2xl font-semibold text-[#0A4D68] mb-4">Discover DMS Benefits</h2>
            <p class="text-gray-600 mb-4">
                Our system offers unmatched efficiency for dealerships, with features designed to save time and boost productivity.
            </p>
            <ul class="list-disc list-inside text-gray-600 space-y-2">
                <li>Real-time inventory updates for accurate tracking.</li>
                <li>Secure access with user roles for data protection.</li>
                <li>Custom reports to analyze business performance.</li>
                <li>User-friendly interface for quick onboarding.</li>
            </ul>
            <button class="mt-6 bg-[#0A4D68] text-[#F8F1F1] px-4 py-2 rounded-full hover:bg-[#088395]" onclick="closeModal()">Close</button>
        </div>
    </div>

    <!-- What is Dealership Section -->
   <section class="dealership-section py-20  relative overflow-hidden">
    <div class="container mx-auto px-6 flex flex-col md:flex-row items-center gap-12">
        
        <!-- Left Image -->
        <div class="dealership-image md:w-1/2 relative animate-fadeInLeft">
            <div class="relative w-full h-96 rounded-3xl overflow-hidden shadow-2xl">
                <img src="./assets/3d-graph-computer-illustration.jpg" alt="Dealership" 
                     class="w-full h-full object-cover object-center transform hover:scale-105 transition duration-500 rounded-3xl">
                <!-- Fancy Shapes -->
                <div class="absolute -top-10 -left-10 w-32 h-32 bg-gradient-to-tr from-blue-500 to-teal-400 rounded-full opacity-30 animate-pulse-slow"></div>
                <div class="absolute -bottom-10 -right-10 w-48 h-48 bg-gradient-to-tr from-yellow-400 to-orange-400 rounded-full opacity-20 animate-pulse-slow"></div>
            </div>
        </div>

        <!-- Right Text Content -->
        <div class="dealership-text md:w-1/2 animate-fadeInRight">
            <h2 class="text-4xl md:text-5xl font-bold mb-6 leading-tight text-white">
                What is Dealership Management?</span>
            </h2>
            <p class="text-white text-lg md:text-xl mb-6 leading-relaxed">
                Dealership Management involves overseeing inventory, sales, and finances for vehicle or equipment dealerships. Our <span class="font-semibold text-[#0A4D68]">DMS</span> integrates these tasks into a single platform, reducing manual effort, eliminating errors, and providing insights to drive growth.
            </p>
        </div>

    </div>

    <!-- Background Fancy Blobs -->
    <div class="absolute top-0 left-0 w-full h-full pointer-events-none overflow-hidden">
        <div class="absolute -top-20 -left-20 w-72 h-72 bg-gradient-to-tr from-blue-500 to-teal-400 rounded-full opacity-10 animate-blob"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-gradient-to-tr from-yellow-400 to-orange-400 rounded-full opacity-10 animate-blob animation-delay-2000"></div>
    </div>
</section>


    <!-- What We Provide Section -->
<section id="features" class="features-section py-20 bg-gradient-to-br from-[#F8F1F1] to-[#E6ECEF]">
    <div class="container mx-auto px-4">
        <h2 class="text-4xl font-bold text-center text-[#0A4D68] mb-16 animate-fade-in">
            Our Key Features
        </h2>
        <div class="relative flex justify-center items-start">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-12">

                <!-- Feature Card Template -->
                <div class="feature-card group animate-slide-up" style="animation-delay: 0.1s;">
                    <div class="flex flex-col items-center justify-center w-65 h-65 gap-3 rounded-3xl bg-white bg-opacity-60 backdrop-blur-lg shadow-2xl transform transition-all duration-500 hover:scale-105 hover:shadow-3xl hover:rotate-1 p-6">
                        <div class="w-16 h-16 flex items-center justify-center mb-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 text-white text-2xl shadow-lg">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[#0A4D68] text-center">
                            Inventory Tracking
                        </h3>
                    </div>
                </div>

                <div class="feature-card group animate-slide-up" style="animation-delay: 0.2s;">
                    <div class="flex flex-col items-center justify-center w-56 h-56 rounded-3xl bg-white bg-opacity-60 backdrop-blur-lg shadow-2xl transform transition-all duration-500 hover:scale-105 hover:shadow-3xl hover:rotate-1 p-6">
                        <div class="w-16 h-16 flex items-center justify-center mb-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 text-white text-2xl shadow-lg">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[#0A4D68] text-center">
                            Sales Automation
                        </h3>
                    </div>
                </div>

                <div class="feature-card group animate-slide-up" style="animation-delay: 0.3s;">
                    <div class="flex flex-col items-center justify-center w-56 h-56 rounded-3xl bg-white bg-opacity-60 backdrop-blur-lg shadow-2xl transform transition-all duration-500 hover:scale-105 hover:shadow-3xl hover:rotate-1 p-6">
                        <div class="w-16 h-16 flex items-center justify-center mb-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 text-white text-2xl shadow-lg">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[#0A4D68] text-center">
                            Financial Reports
                        </h3>
                    </div>
                </div>

                <div class="feature-card group animate-slide-up" style="animation-delay: 0.4s;">
                    <div class="flex flex-col items-center justify-center w-56 h-56 rounded-3xl bg-white bg-opacity-60 backdrop-blur-lg shadow-2xl transform transition-all duration-500 hover:scale-105 hover:shadow-3xl hover:rotate-1 p-6">
                        <div class="w-16 h-16 flex items-center justify-center mb-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 text-white text-2xl shadow-lg">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[#0A4D68] text-center">
                            Secure Access
                        </h3>
                    </div>
                </div>

                <div class="feature-card group animate-slide-up" style="animation-delay: 0.5s;">
                    <div class="flex flex-col items-center justify-center w-56 h-56 rounded-3xl bg-white bg-opacity-60 backdrop-blur-lg shadow-2xl transform transition-all duration-500 hover:scale-105 hover:shadow-3xl hover:rotate-1 p-6">
                        <div class="w-16 h-16 flex items-center justify-center mb-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 text-white text-2xl shadow-lg">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[#0A4D68] text-center">
                            Payment Tracking
                        </h3>
                    </div>
                </div>

                <div class="feature-card group animate-slide-up" style="animation-delay: 0.6s;">
                    <div class="flex flex-col items-center justify-center w-56 h-56 rounded-3xl bg-white bg-opacity-60 backdrop-blur-lg shadow-2xl transform transition-all duration-500 hover:scale-105 hover:shadow-3xl hover:rotate-1 p-6">
                        <div class="w-16 h-16 flex items-center justify-center mb-4 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 text-white text-2xl shadow-lg">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-[#0A4D68] text-center">
                            Expense Management
                        </h3>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>


    <!-- Reviews Section -->
    <section class="reviews-section ">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-white mb-12 animate-reviews-title">User Testimonials</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="review-card animate-review">
                    <div class="flex items-center mb-4">
                        <img src="./assets/Screenshot 2025-08-18 222148.png" alt="User 1" class="mr-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0A4D68]">John Carter</h3>
                            <div class="review-stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600">"DMS has revolutionized our inventory management. It's intuitive and saves us hours every day!"</p>
                </div>
                <div class="review-card animate-review">
                    <div class="flex items-center mb-4">
                        <img src="./assets/Screenshot 2025-08-18 222148.png" alt="User 2" class="mr-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0A4D68]">Emma Wilson</h3>
                            <div class="review-stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600">"The sales and payment tracking features are fantastic. Highly recommend for any dealership."</p>
                </div>
                <div class="review-card animate-review">
                    <div class="flex items-center mb-4">
                        <img src="./assets/Screenshot 2025-08-18 222148.png" alt="User 3" class="mr-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0A4D68]">Liam Brown</h3>
                            <div class="review-stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600">"An excellent tool for managing our finances and operations. Support is top-notch!"</p>
                </div>
            </div>
        </div>
    </section>
    <section id="faq" class="faq-section py-20 bg-gradient-to-br from-[#F8F1F1] to-[#E6ECEF]">
    <div class="container mx-auto px-6">
        <h2 class="text-4xl md:text-5xl font-bold text-center text-[#0A4D68] mb-16">
            Frequently Asked Questions
        </h2>

        <div class="max-w-4xl mx-auto space-y-4">

            <!-- FAQ Item 1 -->
            <div class="faq-item bg-white bg-opacity-60 backdrop-blur-lg rounded-2xl shadow-lg overflow-hidden transition-all duration-300">
                <button class="faq-question w-full text-left px-6 py-4 flex justify-between items-center font-semibold text-[#0A4D68] text-lg focus:outline-none">
                    What is Dealership Management System?
                    <span class="faq-icon text-2xl transition-transform duration-300">+</span>
                </button>
                <div class="faq-answer px-6 pb-4 text-gray-700 hidden">
                    A Dealership Management System (DMS) helps dealerships manage inventory, sales, finances, and customer relations in a single integrated platform.
                </div>
            </div>

            <!-- FAQ Item 2 -->
            <div class="faq-item bg-white bg-opacity-60 backdrop-blur-lg rounded-2xl shadow-lg overflow-hidden transition-all duration-300">
                <button class="faq-question w-full text-left px-6 py-4 flex justify-between items-center font-semibold text-[#0A4D68] text-lg focus:outline-none">
                    How can I track sales using DMS?
                    <span class="faq-icon text-2xl transition-transform duration-300">+</span>
                </button>
                <div class="faq-answer px-6 pb-4 text-gray-700 hidden">
                    Our DMS provides detailed sales dashboards, automated reports, and real-time updates to help you track and optimize your sales process.
                </div>
            </div>

            <!-- FAQ Item 3 -->
            <div class="faq-item bg-white bg-opacity-60 backdrop-blur-lg rounded-2xl shadow-lg overflow-hidden transition-all duration-300">
                <button class="faq-question w-full text-left px-6 py-4 flex justify-between items-center font-semibold text-[#0A4D68] text-lg focus:outline-none">
                    Is my data secure in the system?
                    <span class="faq-icon text-2xl transition-transform duration-300">+</span>
                </button>
                <div class="faq-answer px-6 pb-4 text-gray-700 hidden">
                    Yes! Our platform uses advanced security protocols, role-based access, and regular backups to ensure your data is safe and protected.
                </div>
            </div>

            <!-- FAQ Item 4 -->
            <div class="faq-item bg-white bg-opacity-60 backdrop-blur-lg rounded-2xl shadow-lg overflow-hidden transition-all duration-300">
                <button class="faq-question w-full text-left px-6 py-4 flex justify-between items-center font-semibold text-[#0A4D68] text-lg focus:outline-none">
                    Can I manage payments and expenses?
                    <span class="faq-icon text-2xl transition-transform duration-300">+</span>
                </button>
                <div class="faq-answer px-6 pb-4 text-gray-700 hidden">
                    Yes, our DMS includes modules to track payments, generate invoices, manage expenses, and keep your financial records organized.
                </div>
            </div>

            <!-- FAQ Item 5 -->
            <div class="faq-item bg-white bg-opacity-60 backdrop-blur-lg rounded-2xl shadow-lg overflow-hidden transition-all duration-300">
                <button class="faq-question w-full text-left px-6 py-4 flex justify-between items-center font-semibold text-[#0A4D68] text-lg focus:outline-none">
                    How can I get started with DMS?
                    <span class="faq-icon text-2xl transition-transform duration-300">+</span>
                </button>
                <div class="faq-answer px-6 pb-4 text-gray-700 hidden">
                    Simply sign up for an account, set up your dealership information, and start managing your operations efficiently through the platform.
                </div>
            </div>

        </div>
    </div>
</section>




    <!-- Footer -->
    <footer class="footer">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div>
                    <h3 class="text-2xl font-semibold mb-4">Dealership Management System</h3>
                    <p class="text-gray-200">Empowering dealerships with smart, efficient solutions.</p>
                </div>
                <div>
                    <h3 class="text-2xl font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="hover:underline">Home</a></li>
                        <li><a href="#faq" class="hover:underline">FAQ</a></li>
                        <li><a href="login.php" class="hover:underline">Login</a></li>
                        <li><a href="signup.php" class="hover:underline">Signup</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-2xl font-semibold mb-4">Contact Us</h3>
                    <p class="text-gray-200">Email: support@dms.com</p>
                    <p class="text-gray-200">Phone: +1 (555) 123-4567</p>
                    <div class="footer-social mt-4 flex">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
            <div class="mt-8 text-center text-gray-300">
                &copy; 2025 Dealership Management System. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
         const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const button = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        const icon = item.querySelector('.faq-icon');

        button.addEventListener('click', () => {
            const isOpen = !answer.classList.contains('hidden');
            if(isOpen){
                answer.classList.add('hidden');
                icon.textContent = '+';
            } else {
                answer.classList.remove('hidden');
                icon.textContent = '−';
            }
        });
    });
        // Modal Functionality
        function openModal() {
            document.getElementById('aboutModal').style.display = 'flex';
            gsap.from(".animate-modal", {
                opacity: 0,
                scale: 0.8,
                duration: 0.5,
                ease: "power3.out"
            });
        }
        function closeModal() {
            gsap.to(".animate-modal", {
                opacity: 0,
                scale: 0.8,
                duration: 0.5,
                ease: "power3.in",
                onComplete: () => {
                    document.getElementById('aboutModal').style.display = 'none';
                }
            });
        }

        // GSAP Animations
        gsap.from(".animate-banner-content", {
            opacity: 0,
            x: -100,
            duration: 1,
            ease: "power3.out"
        });
        gsap.from(".animate-banner-image", {
            opacity: 0,
            x: 100,
            duration: 1,
            delay: 0.2,
            ease: "power3.out"
        });
        gsap.from(".animate-about-image", {
            opacity: 0,
            x: -100,
            duration: 1,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-about-image",
                start: "top 80%"
            }
        });
        gsap.from(".animate-about-content", {
            opacity: 0,
            x: 100,
            duration: 1,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-about-content",
                start: "top 80%"
            }
        });
        gsap.from(".animate-dealership-title", {
            opacity: 0,
            y: 50,
            duration: 1,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-dealership-title",
                start: "top 80%"
            }
        });
        gsap.from(".animate-dealership-text", {
            opacity: 0,
            y: 50,
            duration: 1,
            delay: 0.2,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-dealership-text",
                start: "top 80%"
            }
        });
        gsap.from(".animate-features-title", {
            opacity: 0,
            y: 50,
            duration: 1,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-features-title",
                start: "top 80%"
            }
        });
        gsap.from(".animate-feature", {
            opacity: 0,
            scale: 0.8,
            duration: 1,
            stagger: 0.2,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-feature",
                start: "top 80%"
            }
        });
        gsap.from(".animate-reviews-title", {
            opacity: 0,
            y: 50,
            duration: 1,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-reviews-title",
                start: "top 80%"
            }
        });
        gsap.from(".animate-review", {
            opacity: 0,
            y: 50,
            duration: 1,
            stagger: 0.2,
            ease: "power3.out",
            scrollTrigger: {
                trigger: ".animate-review",
                start: "top 80%"
            }
        });
    </script>
</body>
</html>
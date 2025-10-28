<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AmpController extends Controller
{
    public function index(Request $request)
    {
        $host = $request->getSchemeAndHttpHost();

        $html = <<<HTML
<!doctype html>
<html ⚡ lang="en">
<head>
  <meta charset="utf-8" />
  <title>Welcome to Our Platform</title>
  <link rel="canonical" href="$host" />
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />
  
  <!-- AMP Core -->
  <script async src="https://cdn.ampproject.org/v0.js"></script>
  <style amp-boilerplate>
    body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;
         -moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;
         -ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;
         animation:-amp-start 8s steps(1,end) 0s 1 normal both}
    @-webkit-keyframes -amp-start {from{visibility:hidden} to{visibility:visible}}
    @-moz-keyframes -amp-start {from{visibility:hidden} to{visibility:visible}}
    @-ms-keyframes -amp-start {from{visibility:hidden} to{visibility:visible}}
    @-o-keyframes -amp-start {from{visibility:hidden} to{visibility:visible}}
    @keyframes -amp-start {from{visibility:hidden} to{visibility:visible}}
  </style>
  <noscript>
    <style amp-boilerplate>
      body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}
    </style>
  </noscript>

  <!-- AMP Carousel -->
  <script async custom-element="amp-carousel" src="https://cdn.ampproject.org/v0/amp-carousel-0.1.js"></script>

  <style amp-custom>
    body {
      margin:0; padding:0;
      font-family: 'Arial', sans-serif;
      background: #f5f7fa;
      color: #333;
    }

    .hero {
      position: relative;
      width: 100%;
      height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      background: linear-gradient(135deg, #6a11cb, #2575fc);
      color: #fff;
      overflow: hidden;
    }

    .hero svg.bg {
      position: absolute;
      top:0; left:0; width:100%; height:100%;
      z-index:0;
      opacity: 0.1;
    }

    .hero h1 {
      font-size: 3rem;
      margin: 0.5rem 0;
      z-index:1;
    }

    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      z-index:1;
    }

    .hero a.btn {
      display: inline-block;
      padding: 1rem 2rem;
      background: linear-gradient(90deg, #ff512f, #dd2476);
      color: #fff;
      text-decoration: none;
      font-weight: bold;
      border-radius: 30px;
      z-index:1;
    }

    .features {
      padding: 3rem 1rem;
      max-width: 900px;
      margin: 0 auto;
    }

    .features h2 {
      text-align: center;
      margin-bottom: 2rem;
      font-size: 2rem;
    }

    .features amp-carousel {
      margin: 0 auto;
      max-width: 800px;
    }

    .feature-slide {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 1rem;
    }

    .feature-slide svg.icon {
      width: 120px;
      height: 120px;
      margin-bottom: 1rem;
      fill: #6a11cb;
    }

    .feature-slide h3 {
      margin: 0.5rem 0;
    }

    .feature-slide p {
      text-align: center;
      max-width: 400px;
    }
  </style>
</head>
<body>
  <section class="hero">
    <!-- Optional SVG background pattern -->
    <svg class="bg" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice">
      <circle cx="400" cy="300" r="300" fill="#ffffff"/>
    </svg>

    <h1>Welcome to Our Platform</h1>
    <p>Fast, Secure & Fully AMP-Compatible</p>
    <a href="/" class="btn">Get Started</a>
  </section>

  <section class="features">
    <h2>Features</h2>
    <amp-carousel width="800" height="400" layout="responsive" type="slides" autoplay delay="3000">
      
      <div class="feature-slide">
        <!-- SVG Icon 1 -->
        <svg class="icon" viewBox="0 0 64 64">
          <circle cx="32" cy="32" r="30" stroke="#6a11cb" stroke-width="4" fill="none"/>
          <path d="M20 32l8 8 16-16" stroke="#6a11cb" stroke-width="4" fill="none"/>
        </svg>
        <h3>Fast Performance</h3>
        <p>Our AMP page loads instantly for your users.</p>
      </div>

      <div class="feature-slide">
        <!-- SVG Icon 2 -->
        <svg class="icon" viewBox="0 0 64 64">
          <rect x="12" y="12" width="40" height="40" stroke="#6a11cb" stroke-width="4" fill="none"/>
          <path d="M20 32h24" stroke="#6a11cb" stroke-width="4"/>
        </svg>
        <h3>Secure</h3>
        <p>We prioritize security and privacy across our platform.</p>
      </div>

      <div class="feature-slide">
        <!-- SVG Icon 3 -->
        <svg class="icon" viewBox="0 0 64 64">
          <polygon points="32,8 8,56 56,56" stroke="#6a11cb" stroke-width="4" fill="none"/>
        </svg>
        <h3>Mobile Friendly</h3>
        <p>Optimized for all mobile devices using AMP components.</p>
      </div>

    </amp-carousel>
  </section>
</body>
</html>
HTML;

        return Response::make($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }
}

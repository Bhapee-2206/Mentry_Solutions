<?php
/*
 * Mentry Solutions — PWA Splash Screen
 * Single-file PHP version.
 *
 * The logo is embedded as an SVG so no external image file is required.
 */

// Optional: redirect after the splash.
// Default to home index.php after 1800ms if visited directly.
$redirect_url = '/index.php?source=pwa';
$redirect_delay = 1800; // milliseconds
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>Mentry Solutions</title>

<style>
:root{
  --navy:#102a63;
  --navy-deep:#071f55;
  --blue:#087cff;
  --cyan:#12bff3;
  --bg:#f8fbff;
}

*{
  box-sizing:border-box;
  -webkit-tap-highlight-color:transparent;
}

html,body{
  margin:0;
  width:100%;
  height:100%;
  overflow:hidden;
  font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}

body{
  background:var(--bg);
}

.splash{
  position:relative;
  width:100%;
  min-height:100svh;
  overflow:hidden;
  display:flex;
  align-items:center;
  justify-content:center;
  background:
    radial-gradient(circle at 52% 43%,rgba(220,239,255,.78) 0 17%,transparent 43%),
    linear-gradient(145deg,#f7fbff 0%,#fff 52%,#f2f9ff 100%);
}

/* Decorative background */
.orb{
  position:absolute;
  border-radius:50%;
  pointer-events:none;
}

.orb.one{
  width:42svh;
  height:42svh;
  left:-19svh;
  top:-12svh;
  background:radial-gradient(
    circle at 60% 60%,
    #bfe1ff,
    #e9f5ff 68%,
    transparent 69%
  );
}

.orb.two{
  width:28svh;
  height:28svh;
  right:-10svh;
  bottom:22svh;
  background:radial-gradient(
    circle,
    #dff2ff 0 55%,
    transparent 56%
  );
}

.dots{
  position:absolute;
  width:78px;
  height:78px;
  background-image:radial-gradient(#74baff 2px,transparent 2.5px);
  background-size:18px 18px;
  opacity:.45;
}

.dots.left{
  left:22px;
  top:21%;
  transform:rotate(5deg);
}

.dots.right{
  right:26px;
  bottom:20%;
  opacity:.3;
}

/* Main content */
.content{
  position:relative;
  z-index:3;
  width:min(92vw,520px);
  text-align:center;
  transform:translateY(-2%);
}

.logo-wrap{
  width:min(58vw,330px);
  aspect-ratio:1;
  margin:0 auto -4px;
  display:flex;
  align-items:center;
  justify-content:center;
}

/* Embedded Mentry logo */
.logo{
  width:100%;
  height:100%;
  display:block;
  filter:drop-shadow(0 12px 25px rgba(15,82,160,.08));
}

.brand{
  margin-top:0;
  color:var(--navy);
  font-size:clamp(2.15rem,9vw,4rem);
  line-height:.95;
  font-weight:800;
  letter-spacing:-.055em;
}

.brand span{
  color:#1189e8;
}

.tagline{
  margin:20px auto 0;
  max-width:390px;
  color:#38557f;
  font-size:clamp(.9rem,3.5vw,1.15rem);
  line-height:1.55;
  letter-spacing:.09em;
  font-weight:500;
}

.tagline:before,
.tagline:after{
  content:"";
  display:inline-block;
  vertical-align:middle;
  width:54px;
  height:2px;
  margin:0 14px;
  background:#a9c8e8;
}

/* Loader */
.loader{
  width:62px;
  height:62px;
  margin:58px auto 0;
  border-radius:50%;
  border:9px solid #dbeeff;
  border-top-color:var(--blue);
  border-right-color:#55c8f4;
  animation:spin 1.05s linear infinite;
}

.loading-text{
  margin-top:17px;
  color:#48688f;
  font-size:1rem;
  letter-spacing:.22em;
}

/* Bottom waves */
.footer{
  position:absolute;
  z-index:2;
  left:-5%;
  bottom:-1px;
  width:110%;
  height:26svh;
  min-height:170px;
}

.wave{
  position:absolute;
  left:0;
  width:100%;
  height:100%;
  border-radius:50% 50% 0 0 / 28% 28% 0 0;
}

.wave.back{
  bottom:8%;
  background:#d9efff;
  transform:rotate(-4deg) scale(1.1);
}

.wave.mid{
  bottom:1%;
  background:linear-gradient(135deg,#0a4da9,#0a8df1);
  transform:rotate(2deg) scale(1.08);
}

.wave.front{
  bottom:-9%;
  background:linear-gradient(135deg,#06265d,#0a4ca8);
  transform:rotate(-3deg) scale(1.08);
}

.footer-copy{
  position:absolute;
  z-index:5;
  bottom:8%;
  left:0;
  width:100%;
  text-align:center;
  color:#fff;
  font-size:.82rem;
  letter-spacing:.32em;
  font-weight:600;
}

@keyframes spin{
  to{
    transform:rotate(360deg);
  }
}

@media (min-width:700px){
  .content{
    width:620px;
  }

  .logo-wrap{
    width:360px;
  }

  .brand{
    font-size:4rem;
  }

  .footer{
    height:25vh;
  }
}

@media (max-height:700px){
  .logo-wrap{
    width:min(45vw,250px);
  }

  .loader{
    margin-top:28px;
  }

  .tagline{
    margin-top:12px;
  }

  .footer{
    min-height:125px;
  }
}
</style>
</head>

<body>

<main class="splash" aria-label="Mentry Solutions loading screen">

  <span class="orb one"></span>
  <span class="orb two"></span>
  <span class="dots left"></span>
  <span class="dots right"></span>

  <section class="content">

    <div class="logo-wrap">
      <!-- Logo is embedded directly in this PHP file -->
      <svg class="logo"
           viewBox="0 0 600 600"
           xmlns="http://www.w3.org/2000/svg"
           role="img"
           aria-label="Mentry Solutions logo">

        <defs>
          <linearGradient id="mBlue" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#0752a7"/>
            <stop offset="52%" stop-color="#087cff"/>
            <stop offset="100%" stop-color="#13c1f2"/>
          </linearGradient>

          <linearGradient id="mDark" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#071f55"/>
            <stop offset="100%" stop-color="#0752a7"/>
          </linearGradient>
        </defs>

        <!-- M / human symbol -->
        <path fill="url(#mDark)"
          d="M116 190 L116 407 L190 407 L190 292 L116 190 Z"/>
        <path fill="url(#mDark)"
          d="M484 190 L484 407 L410 407 L410 292 L484 190 Z"/>

        <path fill="url(#mBlue)"
          d="M116 190
             C175 217 221 253 300 325
             C379 253 425 217 484 190
             C442 239 397 293 350 346
             C332 366 317 389 300 420
             C283 389 268 366 250 346
             C203 293 158 239 116 190 Z"/>

        <circle cx="300" cy="174" r="43" fill="url(#mBlue)"/>

        <!-- Rising arm -->
        <path d="M300 420 C335 344 392 279 454 222"
              fill="none"
              stroke="#0dbcf0"
              stroke-width="18"
              stroke-linecap="round"/>

        <!-- Star -->
        <path fill="#087cff"
          d="M484 108
             L495 135
             L524 137
             L501 156
             L508 185
             L484 169
             L460 185
             L467 156
             L444 137
             L473 135 Z"/>
      </svg>
    </div>

    <div class="brand">
      Mentry <span>Solutions</span>
    </div>

    <div class="tagline">
      Managed Trainer Network &amp;<br>
      Professional Training Services
    </div>

    <div class="loader" aria-hidden="true"></div>

    <div class="loading-text">
      Loading...
    </div>

  </section>

  <footer class="footer" aria-hidden="true">
    <div class="wave back"></div>
    <div class="wave mid"></div>
    <div class="wave front"></div>

    <div class="footer-copy">
      LEARN &nbsp; • &nbsp; TEACH &nbsp; • &nbsp; GROW TOGETHER
    </div>
  </footer>

</main>

<?php if ($redirect_url !== ''): ?>
<script>
setTimeout(function(){
  window.location.href = <?= json_encode($redirect_url) ?>;
}, <?= (int)$redirect_delay ?>);
</script>
<?php endif; ?>

</body>
</html>

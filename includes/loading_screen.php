<?php
// includes/loading_screen.php - Premium Branded Mentry Loading Screen & Transition Preloader
// STRICTLY active for Standalone/Downloaded PWA users only. Regular browser visitors will not see this loading screen.
?>
<style>
#mentryGlobalLoader {
  --navy: #102a63;
  --navy-deep: #071f55;
  --blue: #087cff;
  --cyan: #12bff3;
  --bg: #f8fbff;
  background: var(--bg);
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

#mentryGlobalLoader .splash {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 100svh;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    radial-gradient(circle at 52% 43%, rgba(220, 239, 255, .78) 0 17%, transparent 43%),
    linear-gradient(145deg, #f7fbff 0%, #fff 52%, #f2f9ff 100%);
}

#mentryGlobalLoader .orb {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}

#mentryGlobalLoader .orb.one {
  width: 42svh;
  height: 42svh;
  left: -19svh;
  top: -12svh;
  background: radial-gradient(
    circle at 60% 60%,
    #bfe1ff,
    #e9f5ff 68%,
    transparent 69%
  );
}

#mentryGlobalLoader .orb.two {
  width: 28svh;
  height: 28svh;
  right: -10svh;
  bottom: 22svh;
  background: radial-gradient(
    circle,
    #dff2ff 0 55%,
    transparent 56%
  );
}

#mentryGlobalLoader .dots {
  position: absolute;
  width: 78px;
  height: 78px;
  background-image: radial-gradient(#74baff 2px, transparent 2.5px);
  background-size: 18px 18px;
  opacity: .45;
}

#mentryGlobalLoader .dots.left {
  left: 22px;
  top: 21%;
  transform: rotate(5deg);
}

#mentryGlobalLoader .dots.right {
  right: 26px;
  bottom: 20%;
  opacity: .3;
}

#mentryGlobalLoader .content {
  position: relative;
  z-index: 3;
  width: min(92vw, 520px);
  text-align: center;
  transform: translateY(-2%);
}

#mentryGlobalLoader .logo-wrap {
  width: min(58vw, 330px);
  aspect-ratio: 1;
  margin: 0 auto -4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

#mentryGlobalLoader .logo {
  width: 100%;
  height: 100%;
  display: block;
  filter: drop-shadow(0 12px 25px rgba(15, 82, 160, .08));
}

#mentryGlobalLoader .brand {
  margin-top: 0;
  color: var(--navy);
  font-size: clamp(2.15rem, 9vw, 4rem);
  line-height: .95;
  font-weight: 800;
  letter-spacing: -.055em;
}

#mentryGlobalLoader .brand span {
  color: #1189e8;
}

#mentryGlobalLoader .tagline {
  margin: 20px auto 0;
  max-width: 390px;
  color: #38557f;
  font-size: clamp(.9rem, 3.5vw, 1.15rem);
  line-height: 1.55;
  letter-spacing: .09em;
  font-weight: 500;
}

#mentryGlobalLoader .tagline:before,
#mentryGlobalLoader .tagline:after {
  content: "";
  display: inline-block;
  vertical-align: middle;
  width: 54px;
  height: 2px;
  margin: 0 14px;
  background: #a9c8e8;
}

#mentryGlobalLoader .loader {
  width: 62px;
  height: 62px;
  margin: 48px auto 0;
  border-radius: 50%;
  border: 9px solid #dbeeff;
  border-top-color: var(--blue);
  border-right-color: #55c8f4;
  animation: mentrySplashSpin 1.05s linear infinite;
}

#mentryGlobalLoader .loading-text {
  margin-top: 17px;
  color: #48688f;
  font-size: 1rem;
  letter-spacing: .22em;
}

#mentryGlobalLoader .footer {
  position: absolute;
  z-index: 2;
  left: -5%;
  bottom: -1px;
  width: 110%;
  height: 26svh;
  min-height: 170px;
}

#mentryGlobalLoader .wave {
  position: absolute;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50% 50% 0 0 / 28% 28% 0 0;
}

#mentryGlobalLoader .wave.back {
  bottom: 8%;
  background: #d9efff;
  transform: rotate(-4deg) scale(1.1);
}

#mentryGlobalLoader .wave.mid {
  bottom: 1%;
  background: linear-gradient(135deg, #0a4da9, #0a8df1);
  transform: rotate(2deg) scale(1.08);
}

#mentryGlobalLoader .wave.front {
  bottom: -9%;
  background: linear-gradient(135deg, #06265d, #0a4ca8);
  transform: rotate(-3deg) scale(1.08);
}

#mentryGlobalLoader .footer-copy {
  position: absolute;
  z-index: 5;
  bottom: 8%;
  left: 0;
  width: 100%;
  text-align: center;
  color: #fff;
  font-size: .82rem;
  letter-spacing: .32em;
  font-weight: 600;
}

@keyframes mentrySplashSpin {
  to {
    transform: rotate(360deg);
  }
}

@media (min-width: 700px) {
  #mentryGlobalLoader .content {
    width: 620px;
  }
  #mentryGlobalLoader .logo-wrap {
    width: 360px;
  }
  #mentryGlobalLoader .brand {
    font-size: 4rem;
  }
  #mentryGlobalLoader .footer {
    height: 25vh;
  }
}

@media (max-height: 700px) {
  #mentryGlobalLoader .logo-wrap {
    width: min(45vw, 250px);
  }
  #mentryGlobalLoader .loader {
    margin-top: 24px;
  }
  #mentryGlobalLoader .tagline {
    margin-top: 12px;
  }
  #mentryGlobalLoader .footer {
    min-height: 125px;
  }
}
</style>

<!-- Mentry Global Loading Screen (Strictly for standalone/downloaded PWA users) -->
<div id="mentryGlobalLoader" class="fixed inset-0 z-[99999] overflow-hidden opacity-0 pointer-events-none transition-opacity duration-300 select-none" style="display: none;">
  <div class="splash" aria-label="Mentry Solutions loading screen">
    <span class="orb one"></span>
    <span class="orb two"></span>
    <span class="dots left"></span>
    <span class="dots right"></span>

    <section class="content">
      <div class="logo-wrap">
        <!-- Logo is embedded directly as vector SVG -->
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

      <div id="mentryLoaderMessage" class="loading-text">
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
  </div>
</div>

<script>
(function() {
    // 1. Strict PWA detection: Only users who installed/downloaded the app will see this loading screen
    let isPwa = false;
    try {
        const isStandaloneMatch = window.matchMedia && (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: window-controls-overlay)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.matchMedia('(display-mode: minimal-ui)').matches
        );
        const isIosStandalone = window.navigator && window.navigator.standalone === true;
        const isUrlPwa = new URLSearchParams(window.location.search).get('source') === 'pwa';
        const isReferrerPwa = document.referrer && document.referrer.indexOf('android-app://') !== -1;
        const isSessionPwa = sessionStorage.getItem('mentry_app_installed_pwa') === '1';

        if (isStandaloneMatch || isIosStandalone || isUrlPwa || isReferrerPwa || isSessionPwa) {
            isPwa = true;
            sessionStorage.setItem('mentry_app_installed_pwa', '1');
        }
    } catch(e) {
        isPwa = false;
    }

    const loader = document.getElementById('mentryGlobalLoader');
    const msgEl = document.getElementById('mentryLoaderMessage');

    // If NOT in standalone/installed PWA mode, completely deactivate loader and exit
    if (!isPwa) {
        window.showMentryLoader = function() {};
        window.hideMentryLoader = function() {
            if (loader) loader.style.display = 'none';
        };
        if (loader) loader.style.display = 'none';
        return; // Regular browser users will have completely instant zero-loader browsing!
    }

    // --- PWA MODE ONLY BELOW ---
    window.showMentryLoader = function(msg) {
        if (!loader) return;
        if (msg && msgEl) msgEl.textContent = msg;
        loader.style.display = 'block';
        requestAnimationFrame(() => {
            loader.classList.remove('opacity-0', 'pointer-events-none');
            loader.classList.add('opacity-100', 'pointer-events-auto');
        });
    };

    window.hideMentryLoader = function() {
        if (!loader) return;
        loader.classList.remove('opacity-100', 'pointer-events-auto');
        loader.classList.add('opacity-0', 'pointer-events-none');
        setTimeout(() => {
            if (loader && loader.classList.contains('opacity-0')) {
                loader.style.display = 'none';
            }
        }, 280);
    };

    // Show initial splash loader in PWA on fresh page loads
    loader.style.display = 'block';
    loader.classList.remove('opacity-0', 'pointer-events-none');
    loader.classList.add('opacity-100', 'pointer-events-auto');

    // Auto-hide when DOM is ready in PWA
    if (document.readyState === 'complete') {
        setTimeout(window.hideMentryLoader, 200);
    } else {
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(window.hideMentryLoader, 250);
        });
        window.addEventListener('load', () => {
            setTimeout(window.hideMentryLoader, 300);
        });
        // Strict safety fallback
        setTimeout(window.hideMentryLoader, 1200);
    }

    // Auto-hide on bfcache restore
    window.addEventListener('pageshow', () => {
        window.hideMentryLoader();
    });

    // PWA transitions for links
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        const target = link.getAttribute('target');
        const download = link.hasAttribute('download');

        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:') || target === '_blank' || download) {
            return;
        }
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;

        setTimeout(() => {
            window.showMentryLoader("Loading...");
        }, 60);
    });

    // PWA transitions on form submissions
    document.addEventListener('submit', function(e) {
        if (e.target.getAttribute('target') === '_blank') return;
        window.showMentryLoader("Processing...");
    });
})();
</script>

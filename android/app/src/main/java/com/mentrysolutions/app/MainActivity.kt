package com.mentrysolutions.app

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.view.View
import android.webkit.*
import android.widget.ProgressBar
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout

class MainActivity : AppCompatActivity() {

    companion object {
        const val TAG = "MentryMainActivity"
        const val EXTRA_URL = "extra_mentry_url"
        const val EXTRA_NOTIF_ID = "extra_notif_id"
        const val EXTRA_NOTIF_TYPE = "extra_notif_type"
        const val HOME_URL = "https://mentry-solutions.vercel.app"
    }

    private lateinit var webView: WebView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var progressBar: ProgressBar

    // Android 13+ (API 33) Runtime Notification Permission Launcher
    private val notificationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        if (isGranted) {
            Log.d(TAG, "POST_NOTIFICATIONS permission granted by user")
            FcmTokenManager.retrieveCurrentToken(this) { token ->
                FcmTokenManager.syncTokenWithServer(this)
            }
        } else {
            Log.w(TAG, "POST_NOTIFICATIONS permission denied by user")
            Toast.makeText(
                this,
                "Notification permission is recommended for receiving instant training alerts.",
                Toast.LENGTH_LONG
            ).show()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        webView = findViewById(R.id.webView)
        swipeRefresh = findViewById(R.id.swipeRefresh)
        progressBar = findViewById(R.id.progressBar)

        setupWebView()
        setupSwipeRefresh()
        setupBackNavigation()
        requestNotificationPermission()

        // Initialize and sync FCM token
        FcmTokenManager.retrieveCurrentToken(this) { token ->
            FcmTokenManager.syncTokenWithServer(this)
        }

        // Handle target URL if opened via notification click or deep link
        val initialUrl = resolveTargetUrl(intent)
        webView.loadUrl(initialUrl)
    }

    override fun onNewIntent(intent: Intent?) {
        super.onNewIntent(intent)
        setIntent(intent)
        intent?.let {
            val targetUrl = resolveTargetUrl(it)
            if (targetUrl != HOME_URL) {
                Log.d(TAG, "Navigating to deep link URL: $targetUrl")
                webView.loadUrl(targetUrl)
            }
        }
    }

    private fun resolveTargetUrl(intent: Intent?): String {
        val extraUrl = intent?.getStringExtra(EXTRA_URL)
        if (!extraUrl.isNullOrEmpty()) {
            return toAbsoluteUrl(extraUrl)
        }

        // Handle standard Android VIEW intent data
        val dataUri = intent?.data
        if (dataUri != null && dataUri.toString().isNotEmpty()) {
            return dataUri.toString()
        }

        return HOME_URL
    }

    private fun toAbsoluteUrl(relativeOrAbsolute: String): String {
        return if (relativeOrAbsolute.startsWith("http://") || relativeOrAbsolute.startsWith("https://")) {
            relativeOrAbsolute
        } else {
            HOME_URL.trimEnd('/') + "/" + relativeOrAbsolute.trimStart('/')
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        val settings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.cacheMode = WebSettings.LOAD_DEFAULT
        settings.useWideViewPort = true
        settings.loadWithOverviewMode = true
        settings.setSupportZoom(false)
        settings.displayZoomControls = false

        // Custom User-Agent tag to allow server to identify Mentry Native Android Shell
        val defaultUa = settings.userAgentString
        settings.userAgentString = "$defaultUa MentryAndroidApp/1.0.0"

        // Enable third-party cookies for cross-domain session persistence
        val cookieManager = CookieManager.getInstance()
        cookieManager.setAcceptCookie(true)
        cookieManager.setAcceptThirdPartyCookies(webView, true)

        // Inject Native Bridge into JavaScript window.MentryAndroid
        webView.addJavascriptInterface(MentryNativeBridge(), "MentryAndroid")

        webView.webViewClient = object : WebViewClient() {
            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                progressBar.visibility = View.VISIBLE
                if (url != null && url.contains("logout.php")) {
                    Log.d(TAG, "User logout detected via URL in onPageStarted, unlinking FCM token")
                    FcmTokenManager.unlinkSession(this@MainActivity)
                }
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                progressBar.visibility = View.GONE
                swipeRefresh.isRefreshing = false

                // Synchronize cookie state
                CookieManager.getInstance().flush()

                // If user logged out, ensure session is unlinked
                if (url != null && url.contains("logout.php")) {
                    Log.d(TAG, "User logout confirmed via URL in onPageFinished, unlinking FCM token")
                    FcmTokenManager.unlinkSession(this@MainActivity)
                }

                // If user reached a logged-in page (trainer/admin/vendor), eagerly sync token
                if (url != null && (url.contains("/trainer/") || url.contains("/admin/") || url.contains("/vendor/"))) {
                    FcmTokenManager.syncTokenWithServer(this@MainActivity)
                }
            }

            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString() ?: return false
                val uri = Uri.parse(url)

                // Handle external tel:, mailto:, whatsapp: links
                if (url.startsWith("tel:") || url.startsWith("mailto:") || url.startsWith("whatsapp:") || url.startsWith("intent:")) {
                    try {
                        val intent = Intent(Intent.ACTION_VIEW, uri)
                        startActivity(intent)
                        return true
                    } catch (e: Exception) {
                        Log.w(TAG, "Cannot launch external scheme: ${e.message}")
                        return true
                    }
                }

                // Keep Mentry URLs inside WebView
                if (uri.host == Uri.parse(HOME_URL).host) {
                    return false
                }

                // Open third-party external links in system browser
                try {
                    val browserIntent = Intent(Intent.ACTION_VIEW, uri)
                    startActivity(browserIntent)
                    return true
                } catch (e: Exception) {
                    return false
                }
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                if (newProgress < 100) {
                    progressBar.visibility = View.VISIBLE
                    progressBar.progress = newProgress
                } else {
                    progressBar.visibility = View.GONE
                }
            }
        }
    }

    private fun setupSwipeRefresh() {
        swipeRefresh.setColorSchemeResources(R.color.mentry_orange)
        swipeRefresh.setOnRefreshListener {
            webView.reload()
        }
    }

    private fun setupBackNavigation() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })
    }

    private fun requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            val permission = Manifest.permission.POST_NOTIFICATIONS
            if (ContextCompat.checkSelfPermission(this, permission) != PackageManager.PERMISSION_GRANTED) {
                notificationPermissionLauncher.launch(permission)
            }
        }
    }

    /**
     * JavaScript Interface exposed to Mentry Web App via `window.MentryAndroid`.
     */
    inner class MentryNativeBridge {
        @JavascriptInterface
        fun isNativeApp(): Boolean = true

        @JavascriptInterface
        fun getFcmToken(): String {
            return FcmTokenManager.getCachedToken(this@MainActivity) ?: ""
        }

        @JavascriptInterface
        fun getInstallationId(): String {
            return FcmTokenManager.getInstallationId(this@MainActivity)
        }

        @JavascriptInterface
        fun syncUserSession(userId: String) {
            Log.d(TAG, "Bridge: syncUserSession called for userId: $userId")
            FcmTokenManager.syncTokenWithServer(this@MainActivity, explicitUserId = userId)
        }

        @JavascriptInterface
        fun onUserLogout() {
            Log.d(TAG, "Bridge: onUserLogout called - unlinking device token")
            FcmTokenManager.unlinkSession(this@MainActivity)
        }
    }
}

package com.mentrysolutions.app

import android.content.Context
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.webkit.CookieManager
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest
import java.util.UUID
import kotlin.concurrent.thread

object FcmTokenManager {
    private const val TAG = "MentryFcmTokenManager"
    private const val PREFS_NAME = "mentry_fcm_prefs"
    private const val KEY_FCM_TOKEN = "key_fcm_token"
    private const val KEY_INSTALLATION_ID = "key_installation_id"
    private const val KEY_LAST_SYNCED_USER = "key_last_synced_user"

    const val DEFAULT_BASE_URL = "https://mentry-solutions.vercel.app"

    fun getInstallationId(context: Context): String {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        var id = prefs.getString(KEY_INSTALLATION_ID, null)
        if (id == null) {
            id = UUID.randomUUID().toString()
            prefs.edit().putString(KEY_INSTALLATION_ID, id).apply()
        }
        return id
    }

    fun getCachedToken(context: Context): String? {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        return prefs.getString(KEY_FCM_TOKEN, null)
    }

    fun saveToken(context: Context, token: String) {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        prefs.edit().putString(KEY_FCM_TOKEN, token).apply()
    }

    private fun maskToken(token: String): String {
        if (token.isEmpty()) return "[EMPTY]"
        return try {
            val md = MessageDigest.getInstance("SHA-256")
            val digest = md.digest(token.toByteArray(Charsets.UTF_8))
            val hashStr = digest.joinToString("") { "%02x".format(it) }
            hashStr.take(12) + "..."
        } catch (e: Exception) {
            if (token.length > 12) "${token.take(4)}...${token.takeLast(4)}" else "***"
        }
    }

    /**
     * Fetch current FCM registration token and persist locally.
     */
    fun retrieveCurrentToken(context: Context, onTokenReady: ((String) -> Unit)? = null) {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (!task.isSuccessful || task.result.isNullOrEmpty()) {
                Log.w(TAG, "Fetching FCM registration token failed", task.exception)
                return@addOnCompleteListener
            }
            val token = task.result
            Log.d(TAG, "Current FCM Token retrieved successfully (${maskToken(token)})")
            saveToken(context, token)
            onTokenReady?.invoke(token)
        }
    }

    /**
     * Safely flush and capture cookies from CookieManager on the current thread.
     */
    private fun captureCookies(baseUrl: String): Pair<String?, Boolean> {
        return try {
            val cm = CookieManager.getInstance()
            cm.flush()
            val cookies = cm.getCookie(baseUrl)
            val hasAuth = !cookies.isNullOrEmpty() && (cookies.contains("PHPSESSID") || cookies.contains("mentry"))
            Pair(cookies, hasAuth)
        } catch (e: Exception) {
            Log.w(TAG, "Unable to capture cookies: ${e.message}")
            Pair(null, false)
        }
    }

    /**
     * Synchronize FCM token with Mentry server with automatic retry logic.
     * Retries with small intervals (1s, 2s, 4s) if session cookies are not yet ready or request is pending auth.
     */
    fun syncTokenWithServer(
        context: Context,
        baseUrl: String = DEFAULT_BASE_URL,
        explicitUserId: String? = null,
        attempt: Int = 1
    ) {
        // Capture cookies on the current (usually main) thread before dispatching network request
        val (cookies, hasAuthCookie) = captureCookies(baseUrl)
        val token = getCachedToken(context)

        // If no cached token exists, retrieve it first instead of silently returning
        if (token.isNullOrEmpty()) {
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful && !task.result.isNullOrEmpty()) {
                    val freshToken = task.result
                    saveToken(context, freshToken)
                    performSyncRequest(context, freshToken, cookies, hasAuthCookie, baseUrl, explicitUserId, attempt)
                } else {
                    Log.w(TAG, "FCM token not yet ready on attempt $attempt, scheduling retry")
                    if (attempt <= 3) {
                        scheduleRetry(context, baseUrl, explicitUserId, attempt)
                    }
                }
            }
            return
        }

        performSyncRequest(context, token, cookies, hasAuthCookie, baseUrl, explicitUserId, attempt)
    }

    private fun performSyncRequest(
        context: Context,
        token: String,
        cookies: String?,
        hasAuthCookie: Boolean,
        baseUrl: String,
        explicitUserId: String?,
        attempt: Int
    ) {
        val installationId = getInstallationId(context)
        val deviceModel = "${Build.MANUFACTURER} ${Build.MODEL}".trim()
        val androidVersion = "Android ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT})"
        val appVersion = "1.0.0"

        thread {
            try {
                val endpointUrl = URL("${baseUrl.trimEnd('/')}/actions/push/register-fcm-token.php")
                val conn = endpointUrl.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.connectTimeout = 10000
                conn.readTimeout = 10000
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                conn.setRequestProperty("Accept", "application/json")

                if (!cookies.isNullOrEmpty()) {
                    conn.setRequestProperty("Cookie", cookies)
                }

                val payload = JSONObject().apply {
                    put("fcmToken", token)
                    put("installationId", installationId)
                    put("deviceModel", deviceModel)
                    put("androidVersion", androidVersion)
                    put("appVersion", appVersion)
                    if (!explicitUserId.isNullOrEmpty()) {
                        put("clientUserId", explicitUserId)
                    }
                }

                OutputStreamWriter(conn.outputStream).use { writer ->
                    writer.write(payload.toString())
                    writer.flush()
                }

                val responseCode = conn.responseCode
                val responseBody = (if (responseCode in 200..299) conn.inputStream else conn.errorStream)
                    ?.bufferedReader()?.use { it.readText() } ?: ""

                val resJson = try { JSONObject(responseBody) } catch (e: Exception) { JSONObject() }
                val isSuccess = responseCode == 200 && resJson.optBoolean("success")
                val boundUserId = resJson.optString("userId", explicitUserId ?: "")

                // Log ONLY safe diagnostics (no secret tokens or credentials)
                Log.d(
                    TAG,
                    "Token sync [attempt=$attempt] HTTP $responseCode | success=$isSuccess | hasCookie=$hasAuthCookie | installId=$installationId | user=$boundUserId | tokenHash=${maskToken(token)}"
                )

                if (isSuccess) {
                    val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                    prefs.edit().putString(KEY_LAST_SYNCED_USER, boundUserId).apply()
                } else if ((responseCode == 401 || !hasAuthCookie) && attempt <= 3) {
                    // Retry when user session cookie is still settling after login
                    Log.d(TAG, "Session not ready on attempt $attempt, scheduling retry...")
                    scheduleRetry(context, baseUrl, explicitUserId, attempt)
                }

                conn.disconnect()
            } catch (e: Exception) {
                Log.w(TAG, "Network error syncing token on attempt $attempt: ${e.message}")
                if (attempt <= 3) {
                    scheduleRetry(context, baseUrl, explicitUserId, attempt)
                }
            }
        }
    }

    private fun scheduleRetry(context: Context, baseUrl: String, explicitUserId: String?, attempt: Int) {
        val delayMs = when (attempt) {
            1 -> 1000L
            2 -> 2000L
            else -> 4000L
        }
        Handler(Looper.getMainLooper()).postDelayed({
            syncTokenWithServer(context, baseUrl, explicitUserId, attempt + 1)
        }, delayMs)
    }

    /**
     * Unlink device token upon user logout so User B never receives User A's alerts.
     */
    fun unlinkSession(context: Context, baseUrl: String = DEFAULT_BASE_URL) {
        val token = getCachedToken(context)
        val installationId = getInstallationId(context)
        val (cookies, _) = captureCookies(baseUrl)

        thread {
            try {
                val endpointUrl = URL("${baseUrl.trimEnd('/')}/actions/push/unregister-fcm-token.php")
                val conn = endpointUrl.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.connectTimeout = 8000
                conn.readTimeout = 8000
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")

                if (!cookies.isNullOrEmpty()) {
                    conn.setRequestProperty("Cookie", cookies)
                }

                val payload = JSONObject().apply {
                    if (!token.isNullOrEmpty()) put("fcmToken", token)
                    put("installationId", installationId)
                }

                OutputStreamWriter(conn.outputStream).use { writer ->
                    writer.write(payload.toString())
                    writer.flush()
                }

                Log.d(TAG, "Token unlinked upon logout. HTTP ${conn.responseCode} (installId: $installationId)")
                conn.disconnect()

                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                prefs.edit().remove(KEY_LAST_SYNCED_USER).apply()
            } catch (e: Exception) {
                Log.w(TAG, "Error unlinking token on logout: ${e.message}")
            }
        }
    }
}

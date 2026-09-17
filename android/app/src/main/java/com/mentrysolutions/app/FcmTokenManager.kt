package com.mentrysolutions.app

import android.content.Context
import android.os.Build
import android.util.Log
import android.webkit.CookieManager
import com.google.firebase.messaging.FirebaseMessaging
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
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

    /**
     * Fetch current FCM registration token and persist locally.
     */
    fun retrieveCurrentToken(context: Context, onTokenReady: ((String) -> Unit)? = null) {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (!task.isSuccessful) {
                Log.w(TAG, "Fetching FCM registration token failed", task.exception)
                return@addOnCompleteListener
            }
            val token = task.result
            Log.d(TAG, "Current FCM Token retrieved successfully")
            saveToken(context, token)
            onTokenReady?.invoke(token)
        }
    }

    /**
     * Send the registration token to Mentry server using the current WebView session cookies.
     * Guaranteed to bind to the authenticated user on the server.
     */
    fun syncTokenWithServer(context: Context, baseUrl: String = DEFAULT_BASE_URL, explicitUserId: String? = null) {
        val token = getCachedToken(context) ?: return
        val installationId = getInstallationId(context)

        val deviceModel = "${Build.MANUFACTURER} ${Build.MODEL}"
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

                // Forward cookies from WebView session
                val cookies = CookieManager.getInstance().getCookie(baseUrl)
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

                Log.d(TAG, "Token sync HTTP $responseCode: $responseBody")

                if (responseCode == 200) {
                    val resJson = JSONObject(responseBody)
                    if (resJson.optBoolean("success")) {
                        val boundUserId = resJson.optString("userId", explicitUserId ?: "")
                        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                        prefs.edit().putString(KEY_LAST_SYNCED_USER, boundUserId).apply()
                    }
                }
                conn.disconnect()
            } catch (e: Exception) {
                Log.e(TAG, "Error syncing token with server: ${e.message}")
            }
        }
    }

    /**
     * Unlink device token upon user logout so User B never receives User A's alerts.
     */
    fun unlinkSession(context: Context, baseUrl: String = DEFAULT_BASE_URL) {
        val token = getCachedToken(context)
        val installationId = getInstallationId(context)

        thread {
            try {
                val endpointUrl = URL("${baseUrl.trimEnd('/')}/actions/push/unregister-fcm-token.php")
                val conn = endpointUrl.openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.connectTimeout = 8000
                conn.readTimeout = 8000
                conn.doOutput = true
                conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")

                val cookies = CookieManager.getInstance().getCookie(baseUrl)
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

                Log.d(TAG, "Token unlinked upon logout. HTTP ${conn.responseCode}")
                conn.disconnect()

                val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
                prefs.edit().remove(KEY_LAST_SYNCED_USER).apply()
            } catch (e: Exception) {
                Log.w(TAG, "Error unlinking token on logout: ${e.message}")
            }
        }
    }
}

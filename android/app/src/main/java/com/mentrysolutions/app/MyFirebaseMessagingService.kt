package com.mentrysolutions.app

import android.annotation.SuppressLint
import android.app.PendingIntent
import android.content.Intent
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class MyFirebaseMessagingService : FirebaseMessagingService() {

    companion object {
        private const val TAG = "MentryFcmService"
    }

    /**
     * Called when a new FCM registration token is generated (e.g. initial install, app restore, or token rotation).
     */
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d(TAG, "Refreshed FCM registration token received")
        FcmTokenManager.saveToken(applicationContext, token)
        FcmTokenManager.syncTokenWithServer(applicationContext)
    }

    /**
     * Called when an incoming message is received while the app is in the foreground,
     * or when data-only payloads arrive in the background.
     * Note: Pure notification payloads in background are handled directly by the Android system tray.
     */
    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        Log.d(TAG, "FCM Message received from: ${remoteMessage.from}")

        // 1. Resolve Title and Body from notification block or data payload
        val title = remoteMessage.notification?.title
            ?: remoteMessage.data["title"]
            ?: "Mentry Solutions"

        val body = remoteMessage.notification?.body
            ?: remoteMessage.data["body"]
            ?: remoteMessage.data["message"]
            ?: "You have a new training update."

        // 2. Resolve target URL & custom attributes
        val targetUrl = remoteMessage.data["url"]
            ?: remoteMessage.data["link"]
            ?: "/trainer/notifications.php"

        val notificationId = remoteMessage.data["notificationId"]
            ?: remoteMessage.data["id"]
            ?: System.currentTimeMillis().toString()

        val type = remoteMessage.data["type"] ?: "GENERAL"

        showSystemNotification(title, body, targetUrl, notificationId, type)
    }

    @SuppressLint("MissingPermission")
    private fun showSystemNotification(
        title: String,
        body: String,
        targetUrl: String,
        notificationId: String,
        type: String
    ) {
        // Build Intent to launch or bring forward MainActivity
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra(MainActivity.EXTRA_URL, targetUrl)
            putExtra(MainActivity.EXTRA_NOTIF_ID, notificationId)
            putExtra(MainActivity.EXTRA_NOTIF_TYPE, type)
        }

        val requestCode = notificationId.hashCode()
        val pendingIntent = PendingIntent.getActivity(
            this,
            requestCode,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notificationBuilder = NotificationCompat.Builder(this, MentryApp.CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification)
            .setColor(ContextCompat.getColor(this, R.color.mentry_orange))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setAutoCancel(true)
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .setContentIntent(pendingIntent)

        val notificationManager = NotificationManagerCompat.from(this)
        val notifNumericId = Math.abs(notificationId.hashCode())

        try {
            notificationManager.notify(notifNumericId, notificationBuilder.build())
            Log.d(TAG, "Notification posted to system tray (ID: $notifNumericId, URL: $targetUrl)")
        } catch (e: SecurityException) {
            Log.w(TAG, "POST_NOTIFICATIONS permission not granted: ${e.message}")
        }
    }
}

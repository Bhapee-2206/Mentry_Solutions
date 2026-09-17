package com.mentrysolutions.app

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build

class MentryApp : Application() {

    companion object {
        const val CHANNEL_ID = "mentry_alerts"
        const val CHANNEL_NAME = "Mentry Alerts"
        const val CHANNEL_DESC = "Instant notifications for college training opportunities, direct invitations, and assignment updates."
    }

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
    }

    /**
     * Create the authoritative notification channel during application startup.
     * Ensures channel exists before any background FCM notification is delivered.
     */
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val importance = NotificationManager.IMPORTANCE_HIGH
            val channel = NotificationChannel(CHANNEL_ID, CHANNEL_NAME, importance).apply {
                description = CHANNEL_DESC
                enableLights(true)
                enableVibration(true)
                setShowBadge(true)
            }

            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.createNotificationChannel(channel)
        }
    }
}

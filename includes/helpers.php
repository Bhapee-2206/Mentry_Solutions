<?php
// includes/helpers.php

function formatINR($amount) {
    if ($amount === null || $amount === '') return '₹0';
    $amount = (float)$amount;
    return '₹' . number_format($amount, 0, '.', ',');
}

function formatDate($date) {
    if (!$date) return 'N/A';
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        $datetime = $date->toDateTime();
    } elseif (is_numeric($date)) {
        $datetime = new DateTime("@$date");
    } elseif (is_string($date)) {
        try {
            $datetime = new DateTime($date);
        } catch (Exception $e) {
            return $date;
        }
    } else {
        return 'N/A';
    }
    return $datetime->format('M j, Y');
}

function formatRelativeTime($date) {
    if (!$date) return 'recently';
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        $timestamp = $date->toDateTime()->getTimestamp();
    } elseif (is_numeric($date)) {
        $timestamp = (int)$date;
    } elseif (is_string($date)) {
        $timestamp = strtotime($date);
    } else {
        return 'recently';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $timestamp);
}

function getStatusBadge($status) {
    $status = strtoupper($status ?? 'PENDING');
    switch ($status) {
        case 'APPROVED':
        case 'SELECTED':
        case 'PUBLISHED':
        case 'ACTIVE':
        case 'COMPLETED':
            return '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        case 'PENDING_APPROVAL':
        case 'PENDING':
        case 'SUBMITTED':
            return '<span class="bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        case 'SHORTLISTED':
            return '<span class="bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        case 'REJECTED':
        case 'SUSPENDED':
        case 'CANCELLED':
            return '<span class="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
        default:
            return '<span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2.5 py-0.5 rounded-full">' . htmlspecialchars(str_replace('_', ' ', $status)) . '</span>';
    }
}

function getAvailabilityBadge($availabilityStatus, $availableFromDate = null) {
    $status = strtoupper($availabilityStatus ?? 'AVAILABLE_NOW');
    switch ($status) {
        case 'AVAILABLE_NOW':
            return '<span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Available Now</span>';
        case 'FREE_FROM_DATE':
            $dateText = $availableFromDate ? formatDate($availableFromDate) : 'Upcoming Date';
            return '<span class="inline-flex items-center gap-1 bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="material-symbols-outlined text-[13px] text-amber-600">event</span> Free from ' . htmlspecialchars($dateText) . '</span>';
        case 'BUSY_ON_ASSIGNMENT':
            $busyUntilText = $availableFromDate ? ' (until ' . formatDate($availableFromDate) . ')' : '';
            return '<span class="inline-flex items-center gap-1 bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="material-symbols-outlined text-[13px] text-blue-600">school</span> Delivering Workshop' . htmlspecialchars($busyUntilText) . '</span>';
        case 'UNAVAILABLE':
            return '<span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 border border-slate-200 text-[11px] font-medium px-2.5 py-0.5 rounded-full"><span class="material-symbols-outlined text-[13px] text-slate-400">block</span> Unavailable</span>';
        default:
            return '<span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold px-2.5 py-0.5 rounded-full"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Available</span>';
    }
}

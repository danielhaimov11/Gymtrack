<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function initLanguage() {
    // Set default language if not set
    if (!isset($_SESSION['lang'])) {
        $_SESSION['lang'] = 'en';
    }
    
    // Update language if requested via GET
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'he'])) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    
    return $_SESSION['lang'];
}

function getCurrentLanguage() {
    return isset($_SESSION['lang']) ? $_SESSION['lang'] : initLanguage();
}

function translate($key) {
    static $translations = null;
    if ($translations === null) {
        $lang = getCurrentLanguage();
        $translations = require __DIR__ . "/lang/{$lang}.php";
    }
    return $translations[$key] ?? $key;
}

function isRTL() {
    return getCurrentLanguage() === 'he';
}

// Initialize language on include
initLanguage();
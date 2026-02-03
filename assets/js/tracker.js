/**
 * Data Signals - Lightweight tracking script (~400 bytes minified)
 * Privacy-friendly: no cookies, fingerprint-based sessions
 */
(function() {
    'use strict';
    
    var c = window.dsConfig;
    if (!c || !c.url) return;
    
    // Build request URL
    var params = [
        'p=' + encodeURIComponent(c.path || location.pathname),
        'id=' + (c.id || 0)
    ];
    
    // Add referrer if external
    var ref = document.referrer;
    if (ref && ref.indexOf(location.hostname) === -1) {
        params.push('r=' + encodeURIComponent(ref));
    }
    
    // Send beacon (or fallback to image)
    var url = c.url + (c.url.indexOf('?') > -1 ? '&' : '?') + params.join('&');
    
    if (navigator.sendBeacon) {
        navigator.sendBeacon(url);
    } else {
        new Image().src = url;
    }
})();

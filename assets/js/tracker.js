/**
 * Data Signals - Lightweight tracking script
 * Privacy-friendly: no cookies, fingerprint-based sessions
 */
(function() {
    'use strict';
    
    var c = window.dsConfig;
    if (!c || !c.url) return;
    
    var host = location.hostname;
    
    // Track pageview
    function trackPageview() {
        var params = [
            'p=' + encodeURIComponent(c.path || location.pathname),
            'id=' + (c.id || 0),
            'u=' + encodeURIComponent(location.href)
        ];
        
        var ref = document.referrer;
        if (ref && ref.indexOf(host) === -1) {
            params.push('r=' + encodeURIComponent(ref));
        }
        
        send(params);
    }
    
    // Track click event
    function trackClick(type, url) {
        var params = [
            't=click',
            'ct=' + type,
            'cu=' + encodeURIComponent(url)
        ];
        send(params);
    }
    
    // Send beacon
    function send(params) {
        var url = c.url + (c.url.indexOf('?') > -1 ? '&' : '?') + params.join('&');
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url);
        } else {
            new Image().src = url;
        }
    }
    
    // Click handler
    function handleClick(e) {
        var target = e.target;
        
        // Find closest anchor
        while (target && target.tagName !== 'A') {
            target = target.parentElement;
        }
        
        if (!target || !target.href) return;
        
        var href = target.href;
        var type = null;
        
        // Check click type
        if (href.indexOf('mailto:') === 0) {
            type = 'mailto';
        } else if (href.indexOf('tel:') === 0) {
            type = 'tel';
        } else if (isDownload(href, target)) {
            type = 'download';
        } else if (isOutbound(href)) {
            type = 'outbound';
        }
        
        if (type) {
            trackClick(type, href);
        }
    }
    
    // Check if link is outbound
    function isOutbound(url) {
        try {
            var link = new URL(url, location.origin);
            return link.hostname !== host;
        } catch (e) {
            return false;
        }
    }
    
    // Check if link is a download
    function isDownload(url, el) {
        if (el.hasAttribute('download')) return true;
        
        var ext = url.split('?')[0].split('.').pop().toLowerCase();
        var downloadExts = ['pdf', 'zip', 'rar', 'gz', 'tar', 'exe', 'dmg', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'mp3', 'mp4', 'avi', 'mov', 'wmv'];
        
        return downloadExts.indexOf(ext) > -1;
    }
    
    // Initialize
    trackPageview();
    
    // Track clicks if enabled
    if (c.trackClicks !== false) {
        document.addEventListener('click', handleClick, true);
    }
})();

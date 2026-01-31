/**
 * Google Search Console Settings Page JavaScript
 *
 * @package DataSignals
 */

(function($) {
	'use strict';

	const dsGscSettings = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			$('#ds-gsc-authorize').on('click', this.handleAuthorize.bind(this));
			$('#ds-gsc-disconnect').on('click', this.handleDisconnect.bind(this));
			$('#ds-gsc-sync-now').on('click', this.handleSyncNow.bind(this));
		},

		/**
		 * Handle authorize button click
		 */
		handleAuthorize: function(e) {
			e.preventDefault();
			
			const $button = $(e.currentTarget);
			const $status = $('#ds-gsc-status');

			$button.prop('disabled', true).text('Authorizing...');
			$status.html('');

			$.ajax({
				url: window.dsGscSettings.restUrl + 'gsc/authorize',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', window.dsGscSettings.nonce);
				},
				success: function(response) {
					if (response.authorization_url) {
						// Open authorization URL in new window
						const authWindow = window.open(
							response.authorization_url,
							'gsc_auth',
							'width=600,height=700'
						);

						$status.html('<div class="notice notice-info"><p>Please complete authorization in the popup window...</p></div>');

						// Poll for completion
						const pollInterval = setInterval(function() {
							if (authWindow.closed) {
								clearInterval(pollInterval);
								$status.html('<div class="notice notice-info"><p>Checking authorization status...</p></div>');
								
								setTimeout(function() {
									location.reload();
								}, 2000);
							}
						}, 1000);
					}
				},
				error: function(xhr) {
					const error = xhr.responseJSON?.message || 'Authorization failed';
					$status.html('<div class="notice notice-error"><p>' + error + '</p></div>');
				},
				complete: function() {
					$button.prop('disabled', false).text('Connect to Google Search Console');
				}
			});
		},

		/**
		 * Handle disconnect button click
		 */
		handleDisconnect: function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to disconnect from Google Search Console?')) {
				return;
			}

			const $button = $(e.currentTarget);
			const $status = $('#ds-gsc-status');

			$button.prop('disabled', true);
			$status.html('');

			// Delete OAuth tokens (requires backend endpoint - simplified here)
			$.ajax({
				url: window.dsGscSettings.restUrl + 'gsc/disconnect',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', window.dsGscSettings.nonce);
				},
				success: function() {
					$status.html('<div class="notice notice-success"><p>Disconnected successfully.</p></div>');
					setTimeout(function() {
						location.reload();
					}, 1500);
				},
				error: function(xhr) {
					const error = xhr.responseJSON?.message || 'Disconnect failed';
					$status.html('<div class="notice notice-error"><p>' + error + '</p></div>');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle sync now button click
		 */
		handleSyncNow: function(e) {
			e.preventDefault();
			
			const $button = $(e.currentTarget);
			const $status = $('#ds-gsc-status');

			$button.prop('disabled', true).text('Syncing...');
			$status.html('<div class="notice notice-info"><p>Starting keyword sync...</p></div>');

			$.ajax({
				url: window.dsGscSettings.restUrl + 'gsc/sync',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', window.dsGscSettings.nonce);
				},
				success: function(response) {
					$status.html('<div class="notice notice-success"><p>' + response.message + '</p></div>');
					setTimeout(function() {
						location.reload();
					}, 1500);
				},
				error: function(xhr) {
					const error = xhr.responseJSON?.message || 'Sync failed';
					$status.html('<div class="notice notice-error"><p>' + error + '</p></div>');
				},
				complete: function() {
					$button.prop('disabled', false).text('Sync Now');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		dsGscSettings.init();
	});

})(jQuery);

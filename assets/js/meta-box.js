/**
 * ArchivioID Meta Box JavaScript
 *
 * Handles AJAX verification, deletion, and UI interactions for the
 * signature verification meta box.
 *
 * @package ArchivioID
 * @since   1.1.0
 */

/* global jQuery, archivioIdMetaBox */

(function($) {
	'use strict';

	// Configuration from localized script
	const config = archivioIdMetaBox || {};
	const ajax = config.ajaxUrl;
	const nonce = config.nonce;
	const postId = config.postId;
	const strings = config.strings || {};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		initVerifyButton();
		initDeleteButton();
		initCopyHash();
		loadBackendInfo();
	});

	/**
	 * Initialize verify signature button
	 */
	function initVerifyButton() {
		$(document).on('click', '#archivio-verify-btn', function(e) {
			e.preventDefault();
			
			const $btn = $(this);
			const $spinner = $('#archivio-action-spinner');
			const $result = $('#archivio-ajax-result');
			const $badge = $('.archivio-status-badge');

			// Disable button and show spinner
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$result.hide().removeClass();

			// AJAX request
			$.ajax({
				url: ajax,
				type: 'POST',
				data: {
					action: 'archivio_id_verify_signature',
					nonce: nonce,
					post_id: postId
				},
				success: function(response) {
					if (response.success) {
						// Verification successful
						handleVerificationSuccess(response.data, $btn, $result, $badge);
					} else {
						// Verification failed
						handleVerificationFailure(response.data, $btn, $result, $badge);
					}
				},
				error: function(xhr, status, error) {
					// AJAX error
					handleAjaxError($btn, $result, $badge, error);
				},
				complete: function() {
					// Hide spinner
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	/**
	 * Handle successful verification
	 */
	function handleVerificationSuccess(data, $btn, $result, $badge) {
		// Update button
		$btn.text(strings.verified || 'Verified ✓')
			.removeClass('button-primary')
			.addClass('button-secondary');

		// Update badge
		updateBadge($badge, 'verified', strings.verified || 'Verified', '✓');

		// Show result message
		let message = data.message || strings.verified;
		if (data.key_label) {
			message += ' — ' + data.key_label;
		}
		if (data.backend && data.backend.name) {
			message += '<br><small>Backend: ' + escapeHtml(data.backend.name) + '</small>';
		}

		$result
			.addClass('archivio-ajax-result archivio-result-success')
			.html('<span class="dashicons dashicons-yes-alt"></span> ' + message)
			.slideDown(300);

		// Re-enable button after delay
		setTimeout(function() {
			$btn.prop('disabled', false);
		}, 2000);
	}

	/**
	 * Handle verification failure
	 */
	function handleVerificationFailure(data, $btn, $result, $badge) {
		const status = data.status || 'invalid';
		const isError = status === 'error';
		
		// Update button
		$btn.text(isError ? strings.error : strings.invalid)
			.prop('disabled', false);

		// Update badge
		const badgeLabel = isError ? strings.error : strings.invalid;
		const badgeIcon = isError ? '!' : '✗';
		updateBadge($badge, status, badgeLabel, badgeIcon);

		// Show result message
		let message = data.message || strings.requestFailed;
		if (data.backend && data.backend.name) {
			message += '<br><small>Backend: ' + escapeHtml(data.backend.name) + '</small>';
		}

		$result
			.addClass('archivio-ajax-result archivio-result-' + (isError ? 'error' : 'warning'))
			.html('<span class="dashicons dashicons-warning"></span> ' + message)
			.slideDown(300);
	}

	/**
	 * Handle AJAX error
	 */
	function handleAjaxError($btn, $result, $badge, error) {
		$btn.text(strings.error).prop('disabled', false);
		
		updateBadge($badge, 'error', strings.error, '!');
		
		$result
			.addClass('archivio-ajax-result archivio-result-error')
			.html('<span class="dashicons dashicons-warning"></span> ' + strings.requestFailed)
			.slideDown(300);

		// Log error to console
		console.error('ArchivioID verification error:', error);
	}

	/**
	 * Initialize delete signature button
	 */
	function initDeleteButton() {
		$(document).on('click', '#archivio-delete-btn', function(e) {
			e.preventDefault();

			// Confirm deletion
			if (!window.confirm(strings.confirmDelete || 'Remove this signature?')) {
				return;
			}

			const $btn = $(this);
			const $spinner = $('#archivio-action-spinner');

			// Disable button and show spinner
			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			// AJAX request
			$.ajax({
				url: ajax,
				type: 'POST',
				data: {
					action: 'archivio_id_delete_signature',
					nonce: nonce,
					post_id: postId
				},
				success: function(response) {
					if (response.success) {
						// Reload page to show upload form
						window.location.reload();
					} else {
						alert(response.data.message || strings.requestFailed);
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					alert(strings.requestFailed);
					$btn.prop('disabled', false);
				},
				complete: function() {
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	/**
	 * Initialize copy hash button
	 */
	function initCopyHash() {
		$(document).on('click', '.archivio-copy-hash', function(e) {
			e.preventDefault();
			
			const $btn = $(this);
			const hash = $btn.data('hash');

			// Modern clipboard API
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(hash).then(function() {
					showCopyFeedback($btn);
				}).catch(function(err) {
					console.error('Copy failed:', err);
					fallbackCopy(hash, $btn);
				});
			} else {
				// Fallback for older browsers
				fallbackCopy(hash, $btn);
			}
		});
	}

	/**
	 * Fallback copy method for older browsers
	 */
	function fallbackCopy(text, $btn) {
		const $temp = $('<textarea>')
			.val(text)
			.css({
				position: 'fixed',
				top: 0,
				left: 0,
				width: '2em',
				height: '2em',
				padding: 0,
				border: 'none',
				outline: 'none',
				boxShadow: 'none',
				background: 'transparent'
			})
			.appendTo('body');

		$temp[0].select();
		
		try {
			const successful = document.execCommand('copy');
			if (successful) {
				showCopyFeedback($btn);
			}
		} catch (err) {
			console.error('Fallback copy failed:', err);
		}

		$temp.remove();
	}

	/**
	 * Show copy feedback
	 */
	function showCopyFeedback($btn) {
		const originalText = $btn.text();
		$btn.text('✓ Copied!');
		
		setTimeout(function() {
			$btn.text(originalText);
		}, 2000);
	}

	/**
	 * Load and display backend information
	 */
	function loadBackendInfo() {
		$.ajax({
			url: ajax,
			type: 'POST',
			data: {
				action: 'archivio_id_get_backend_info',
				nonce: nonce
			},
			success: function(response) {
				if (response.success && response.data.backend) {
					const backend = response.data.backend;
					const $backendInfo = $('.archivio-backend-info');
					const $backendName = $('#archivio-backend-name');
					
					let statusIcon = '';
					if (backend.status === 'optimal') {
						statusIcon = '<span style="color: #46b450;">●</span> ';
					} else if (backend.status === 'fallback') {
						statusIcon = '<span style="color: #ffb900;">●</span> ';
					} else {
						statusIcon = '<span style="color: #dc3232;">●</span> ';
					}

					$backendName.html(
						statusIcon + 'Backend: ' + 
						escapeHtml(backend.name) + 
						' <code>' + escapeHtml(backend.version) + '</code>'
					);
					
					// Show backend info for verified signatures
					if ($('.archivio-status-verified').length) {
						$backendInfo.show();
					}
				}
			}
		});
	}

	/**
	 * Update status badge
	 */
	function updateBadge($badge, status, label, icon) {
		const statusClasses = {
			'not_signed': 'archivio-status-not-signed',
			'uploaded': 'archivio-status-uploaded',
			'verified': 'archivio-status-verified',
			'invalid': 'archivio-status-invalid',
			'error': 'archivio-status-error'
		};

		// Remove all status classes
		Object.values(statusClasses).forEach(function(cls) {
			$badge.removeClass(cls);
		});

		// Add new status class
		$badge.addClass(statusClasses[status] || statusClasses.not_signed);

		// Update icon and label
		$badge.find('.archivio-status-icon').text(icon);
		$badge.find('.archivio-status-label').text(label);
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

})(jQuery);

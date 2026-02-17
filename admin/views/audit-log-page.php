<?php
/**
 * ArchivioID Audit Log Admin View
 *
 * @package ArchivioID
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap archivio-id-audit-log-page">
	<h1><?php esc_html_e( 'ArchivioID Audit Logs', 'archivio-id' ); ?></h1>
	
	<?php settings_errors( 'archivio_id_audit_log' ); ?>

	<div class="archivio-id-audit-card">
		<div class="archivio-id-audit-card-header">
			<h2><?php esc_html_e( 'Filter & Export', 'archivio-id' ); ?></h2>
		</div>
		
		<div class="archivio-id-audit-card-body">
			<div class="archivio-id-audit-controls">
				<!-- Filter Section -->
				<div class="archivio-id-audit-section">
					<h3><?php esc_html_e( 'Filter Logs', 'archivio-id' ); ?></h3>
					<form method="get" action="" class="archivio-id-filter-form">
						<input type="hidden" name="page" value="archivio-id-audit-logs">
						
						<div class="form-row">
							<label for="filter-status"><?php esc_html_e( 'Status:', 'archivio-id' ); ?></label>
							<select name="filter_status" id="filter-status" class="regular-select">
								<option value=""><?php esc_html_e( 'All Statuses', 'archivio-id' ); ?></option>
								<option value="unverified" <?php selected( $filter_status, 'unverified' ); ?>><?php esc_html_e( 'Unverified', 'archivio-id' ); ?></option>
								<option value="verified" <?php selected( $filter_status, 'verified' ); ?>><?php esc_html_e( 'Verified', 'archivio-id' ); ?></option>
								<option value="invalid" <?php selected( $filter_status, 'invalid' ); ?>><?php esc_html_e( 'Invalid', 'archivio-id' ); ?></option>
								<option value="error" <?php selected( $filter_status, 'error' ); ?>><?php esc_html_e( 'Error', 'archivio-id' ); ?></option>
							</select>
							<button type="submit" class="button button-secondary">
								<?php esc_html_e( 'Apply Filter', 'archivio-id' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Export Section -->
				<div class="archivio-id-audit-section">
					<h3><?php esc_html_e( 'Export to CSV', 'archivio-id' ); ?></h3>
					<form method="get" action="" class="archivio-id-export-form">
						<input type="hidden" name="page" value="archivio-id-audit-logs">
						<input type="hidden" name="archivio_id_export_logs" value="1">
						<?php wp_nonce_field( 'archivio_id_export_logs', '_wpnonce' ); ?>
						
						<div class="form-row">
							<div class="form-field">
								<label for="start-date"><?php esc_html_e( 'From:', 'archivio-id' ); ?></label>
								<input type="date" name="start_date" id="start-date" class="regular-text">
							</div>
							
							<div class="form-field">
								<label for="end-date"><?php esc_html_e( 'To:', 'archivio-id' ); ?></label>
								<input type="date" name="end_date" id="end-date" class="regular-text">
							</div>
							
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export CSV', 'archivio-id' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Cleanup Section -->
				<div class="archivio-id-audit-section">
					<h3><?php esc_html_e( 'Data Retention', 'archivio-id' ); ?></h3>
					<form method="post" action="" class="archivio-id-cleanup-form" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete old log entries? This cannot be undone.', 'archivio-id' ); ?>');">
						<?php wp_nonce_field( 'archivio_id_delete_old_logs', '_wpnonce' ); ?>
						<input type="hidden" name="archivio_id_delete_old_logs" value="1">
						
						<div class="form-row">
							<label for="delete-days"><?php esc_html_e( 'Delete logs older than:', 'archivio-id' ); ?></label>
							<input type="number" name="days" id="delete-days" value="90" min="1" max="365" class="small-text">
							<span class="description"><?php esc_html_e( 'days', 'archivio-id' ); ?></span>
							
							<button type="submit" class="button button-secondary">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Delete Old Logs', 'archivio-id' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<?php if ( empty( $logs ) ) : ?>
		<div class="archivio-id-audit-card">
			<div class="archivio-id-audit-empty">
				<span class="dashicons dashicons-info-outline"></span>
				<p><?php esc_html_e( 'No audit log entries found.', 'archivio-id' ); ?></p>
				<p class="description"><?php esc_html_e( 'Logs will appear here when signatures are uploaded, verified, or deleted.', 'archivio-id' ); ?></p>
			</div>
		</div>
	<?php else : ?>
		<div class="archivio-id-audit-card">
			<div class="archivio-id-audit-card-header">
				<h2><?php esc_html_e( 'Audit Log Entries', 'archivio-id' ); ?></h2>
				<span class="log-count"><?php echo esc_html( sprintf( _n( '%s entry', '%s entries', $total_logs, 'archivio-id' ), number_format_i18n( $total_logs ) ) ); ?></span>
			</div>
			
			<div class="archivio-id-audit-table-wrapper">
				<table class="wp-list-table widefat fixed striped archivio-id-audit-table">
					<thead>
						<tr>
							<th class="column-timestamp"><?php esc_html_e( 'Date & Time', 'archivio-id' ); ?></th>
							<th class="column-post"><?php esc_html_e( 'Post', 'archivio-id' ); ?></th>
							<th class="column-event"><?php esc_html_e( 'Event', 'archivio-id' ); ?></th>
							<th class="column-status"><?php esc_html_e( 'Status', 'archivio-id' ); ?></th>
							<th class="column-details"><?php esc_html_e( 'Details', 'archivio-id' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$post = get_post( $log->post_id );
							$post_title = $post ? $post->post_title : __( '[Deleted]', 'archivio-id' );
							$post_link = $post ? get_edit_post_link( $log->post_id ) : '';
							
							$user = get_userdata( $log->user_id );
							$username = $user ? $user->user_login : __( '[Unknown]', 'archivio-id' );
							
							$status_class = 'status-' . esc_attr( $log->signature_status );
							$event_class = 'event-' . esc_attr( $log->event_type );
							?>
							<tr>
								<td class="column-timestamp" data-colname="<?php esc_attr_e( 'Date & Time', 'archivio-id' ); ?>">
									<div class="timestamp-wrapper">
										<strong class="date"><?php echo esc_html( wp_date( 'M j, Y', strtotime( $log->timestamp_utc . ' UTC' ) ) ); ?></strong>
										<span class="time"><?php echo esc_html( wp_date( 'g:i A', strtotime( $log->timestamp_utc . ' UTC' ) ) ); ?></span>
										<span class="relative-time"><?php echo esc_html( human_time_diff( strtotime( $log->timestamp_utc . ' UTC' ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'archivio-id' ); ?></span>
									</div>
								</td>
								<td class="column-post" data-colname="<?php esc_attr_e( 'Post', 'archivio-id' ); ?>">
									<div class="post-wrapper">
										<?php if ( $post_link ) : ?>
											<a href="<?php echo esc_url( $post_link ); ?>" class="post-title">
												<?php echo esc_html( wp_trim_words( $post_title, 8 ) ); ?>
											</a>
										<?php else : ?>
											<span class="post-title"><?php echo esc_html( wp_trim_words( $post_title, 8 ) ); ?></span>
										<?php endif; ?>
										<div class="post-meta">
											<span class="post-id">ID: <?php echo esc_html( $log->post_id ); ?></span>
										</div>
									</div>
								</td>
								<td class="column-event" data-colname="<?php esc_attr_e( 'Event', 'archivio-id' ); ?>">
									<span class="event-badge <?php echo esc_attr( $event_class ); ?>">
										<?php echo esc_html( ucfirst( $log->event_type ) ); ?>
									</span>
								</td>
								<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'archivio-id' ); ?>">
									<span class="status-badge <?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( ucfirst( $log->signature_status ) ); ?>
									</span>
								</td>
								<td class="column-details" data-colname="<?php esc_attr_e( 'Details', 'archivio-id' ); ?>">
									<div class="details-wrapper">
										<div class="detail-row">
											<span class="detail-label"><?php esc_html_e( 'Algorithm:', 'archivio-id' ); ?></span>
											<code class="detail-value"><?php echo esc_html( strtoupper( $log->hash_algorithm ) ); ?></code>
										</div>
										<div class="detail-row">
											<span class="detail-label"><?php esc_html_e( 'Key:', 'archivio-id' ); ?></span>
											<code class="detail-value fingerprint" title="<?php echo esc_attr( $log->key_fingerprint ); ?>">
												<?php echo esc_html( substr( $log->key_fingerprint, 0, 8 ) ); ?>...<?php echo esc_html( substr( $log->key_fingerprint, -8 ) ); ?>
											</code>
										</div>
										<div class="detail-row">
											<span class="detail-label"><?php esc_html_e( 'User:', 'archivio-id' ); ?></span>
											<span class="detail-value"><?php echo esc_html( $username ); ?></span>
										</div>
									</div>
								</td>
							</tr>
							<?php if ( ! empty( $log->notes ) ) : ?>
								<tr class="log-notes-row">
									<td colspan="5">
										<div class="log-notes">
											<span class="dashicons dashicons-info"></span>
											<strong><?php esc_html_e( 'Notes:', 'archivio-id' ); ?></strong>
											<?php echo esc_html( $log->notes ); ?>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo; Previous', 'archivio-id' ),
							'next_text' => __( 'Next &raquo;', 'archivio-id' ),
							'total'     => $total_pages,
							'current'   => $paged,
							'type'      => 'list',
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="archivio-id-audit-card archivio-id-info-card">
		<div class="archivio-id-audit-card-header">
			<h2><?php esc_html_e( 'About Audit Logs', 'archivio-id' ); ?></h2>
		</div>
		<div class="archivio-id-audit-card-body">
			<div class="info-grid">
				<div class="info-item">
					<span class="dashicons dashicons-shield-alt"></span>
					<div>
						<strong><?php esc_html_e( 'Security', 'archivio-id' ); ?></strong>
						<p><?php esc_html_e( 'Logs contain only metadata. Private keys and raw signatures are never stored.', 'archivio-id' ); ?></p>
					</div>
				</div>
				
				<div class="info-item">
					<span class="dashicons dashicons-analytics"></span>
					<div>
						<strong><?php esc_html_e( 'Compliance', 'archivio-id' ); ?></strong>
						<p><?php esc_html_e( 'Track all signature verification events for audit trails and regulatory compliance.', 'archivio-id' ); ?></p>
					</div>
				</div>
				
				<div class="info-item">
					<span class="dashicons dashicons-calendar-alt"></span>
					<div>
						<strong><?php esc_html_e( 'Data Retention', 'archivio-id' ); ?></strong>
						<p><?php esc_html_e( 'Logs older than 90 days can be deleted to save database space and comply with retention policies.', 'archivio-id' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

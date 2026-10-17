<?php
/**
 * Admin screen: SWAT Operations -> Registration Status.
 *
 * Writes only the swat_child_registration option. Protected by a capability
 * check and a nonce; only keys for listed events (or already-saved keys) are
 * accepted, and every value is sanitised against a whitelist.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability required to edit registration status.
 *
 * @return string
 */
function swat_child_registration_capability() {
	/**
	 * Filter the capability needed to edit registration status/deadlines.
	 *
	 * @param string $capability Default manage_options.
	 */
	return (string) apply_filters( 'swat_child_registration_capability', 'manage_options' );
}

/**
 * Register the submenu under the parent theme's SWAT Operations menu.
 *
 * @return void
 */
function swat_child_registration_menu() {
	add_submenu_page(
		'swat-ops',
		__( 'Registration Status', 'swat-tournament-os-child' ),
		__( 'Registration Status', 'swat-tournament-os-child' ),
		swat_child_registration_capability(),
		'swat-registration-status',
		'swat_child_render_registration_admin'
	);
}
add_action( 'admin_menu', 'swat_child_registration_menu', 20 );

/**
 * Human text for where a resolved status came from.
 *
 * @param string $source Source slug.
 * @return string
 */
function swat_child_source_label( $source ) {
	$labels = array(
		'admin'    => __( 'admin override', 'swat-tournament-os-child' ),
		'playbook' => __( 'Playbook', 'swat-tournament-os-child' ),
		'date'     => __( 'event dates', 'swat-tournament-os-child' ),
		'deadline' => __( 'entered deadline', 'swat-tournament-os-child' ),
		'default'  => __( 'default (no override, nothing else applies)', 'swat-tournament-os-child' ),
	);
	return $labels[ $source ] ?? $source;
}

/**
 * Render the admin page.
 *
 * @return void
 */
function swat_child_render_registration_admin() {
	if ( ! current_user_can( swat_child_registration_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'swat-tournament-os-child' ), '', array( 'response' => 403 ) );
	}

	$today    = current_time( 'Y-m-d' );
	$statuses = swat_child_registration_statuses();
	$records  = swat_child_registration_records();
	$waitlist = swat_child_waitlist_links();
	$listed   = array();
	$rows     = array();

	foreach ( function_exists( 'swat_os_event_directory' ) ? swat_os_event_directory() : array() as $event ) {
		$parts = swat_child_event_parts( $event );
		if ( '' === $parts['key'] ) {
			continue;
		}
		$listed[ $parts['key'] ] = true;
		if ( '' !== $parts['end'] && $parts['end'] < $today ) {
			continue; // Past events need no editing.
		}
		$rows[] = array(
			'event' => $event,
			'parts' => $parts,
		);
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			return strcmp( $a['parts']['start'], $b['parts']['start'] );
		}
	);

	$orphans = array_diff_key( $records, $listed );
	$saved   = isset( $_GET['swat-reg-saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['swat-reg-saved'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Registration Status', 'swat-tournament-os-child' ); ?></h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Registration settings saved. Purge your page cache if the public pages do not update.', 'swat-tournament-os-child' ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Leave a status on Auto to follow Playbook and the event dates. Choose a restrictive status to close an event manually. Open and Limited still respect Playbook availability. Enter a deadline only if you have a real one; with no deadline, nothing is shown on the public pages.', 'swat-tournament-os-child' ); ?></p>
		<details style="margin:0 0 1em">
			<summary><strong><?php esc_html_e( 'How the status is decided', 'swat-tournament-os-child' ); ?></strong></summary>
			<ol>
				<li><?php esc_html_e( 'Manual cancellations and closures remain effective. Open and Limited cannot override Playbook cancellation, sold-out status, event end dates or an expired deadline.', 'swat-tournament-os-child' ); ?></li>
				<li><?php esc_html_e( 'Otherwise, Playbook status Cancelled shows Cancelled.', 'swat-tournament-os-child' ); ?></li>
				<li><?php esc_html_e( 'Otherwise, a finished event shows Complete.', 'swat-tournament-os-child' ); ?></li>
				<li><?php esc_html_e( 'Otherwise, Playbook SOLD OUT detection shows Sold Out.', 'swat-tournament-os-child' ); ?></li>
				<li><?php esc_html_e( 'Otherwise, a deadline that has passed shows Closed.', 'swat-tournament-os-child' ); ?></li>
				<li><?php esc_html_e( 'Otherwise Open. Limited is only ever shown when you choose it.', 'swat-tournament-os-child' ); ?></li>
			</ol>
		</details>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="swat_child_save_registration">
			<?php wp_nonce_field( 'swat_child_save_registration', 'swat_child_nonce' ); ?>

			<h2><?php esc_html_e( 'Waitlist', 'swat-tournament-os-child' ); ?></h2>
			<p><?php esc_html_e( 'Sold Out and Closed events show a JOIN WAITLIST button. It opens the event\'s own link below, otherwise this default link, otherwise your Contact page with the event name.', 'swat-tournament-os-child' ); ?></p>
			<p>
				<label for="swat-waitlist-default"><strong><?php esc_html_e( 'Default waitlist link', 'swat-tournament-os-child' ); ?></strong></label><br>
				<input type="url" class="regular-text" id="swat-waitlist-default" name="swat_waitlist[_default]" value="<?php echo esc_attr( (string) ( $waitlist['_default'] ?? '' ) ); ?>" placeholder="https://forms.gle/…">
				<br><span class="description"><?php esc_html_e( 'For example a Google Form or Playbook waitlist page. Leave blank to use the Contact page.', 'swat-tournament-os-child' ); ?></span>
			</p>

			<table class="widefat striped" role="presentation">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Event', 'swat-tournament-os-child' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Shown to visitors now', 'swat-tournament-os-child' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status override', 'swat-tournament-os-child' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Registration deadline (optional)', 'swat-tournament-os-child' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Waitlist link (optional)', 'swat-tournament-os-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No upcoming events were found in the synchronized event list.', 'swat-tournament-os-child' ); ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $rows as $row ) :
					$parts = $row['parts'];
					$state = swat_child_registration_state( $row['event'], array( 'fetch' => false ) );
					$auto  = swat_child_registration_state(
						$row['event'],
						array(
							'fetch'           => false,
							'ignore_override' => true,
						)
					);
					$key   = $parts['key'];
					$rec   = $state['record'];
					$id    = 'swat-reg-' . md5( $key );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $parts['name'] ); ?></strong><br>
							<span class="description"><?php echo esc_html( $parts['start'] . ( $parts['end'] !== $parts['start'] ? ' – ' . $parts['end'] : '' ) ); ?></span>
						</td>
						<td>
							<strong><?php echo esc_html( $state['label'] ); ?></strong>
							<span class="description">(<?php echo esc_html( swat_child_source_label( $state['source'] ) ); ?>)</span><br>
							<span class="description">
								<?php
								/* translators: %s: status label. */
								echo esc_html( sprintf( __( 'Auto would show: %s', 'swat-tournament-os-child' ), $auto['label'] ) );
								?>
							</span>
							<?php foreach ( $state['notes'] as $note ) : ?>
								<br><span style="color:#b32d2e"><?php echo esc_html( $note ); ?></span>
							<?php endforeach; ?>
						</td>
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-status"><?php echo esc_html( sprintf( /* translators: %s: event name. */ __( 'Status override for %s', 'swat-tournament-os-child' ), $parts['name'] ) ); ?></label>
							<select id="<?php echo esc_attr( $id ); ?>-status" name="<?php echo esc_attr( 'swat_reg[' . $key . '][status]' ); ?>">
								<option value=""><?php esc_html_e( 'Auto (no override)', 'swat-tournament-os-child' ); ?></option>
								<?php foreach ( $statuses as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $rec['status'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-date"><?php echo esc_html( sprintf( /* translators: %s: event name. */ __( 'Deadline date for %s', 'swat-tournament-os-child' ), $parts['name'] ) ); ?></label>
							<input type="date" id="<?php echo esc_attr( $id ); ?>-date" name="<?php echo esc_attr( 'swat_reg[' . $key . '][deadline]' ); ?>" value="<?php echo esc_attr( $rec['deadline'] ); ?>">
							<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-time"><?php echo esc_html( sprintf( /* translators: %s: event name. */ __( 'Deadline time for %s', 'swat-tournament-os-child' ), $parts['name'] ) ); ?></label>
							<input type="time" id="<?php echo esc_attr( $id ); ?>-time" name="<?php echo esc_attr( 'swat_reg[' . $key . '][deadline_time]' ); ?>" value="<?php echo esc_attr( $rec['deadline_time'] ); ?>">
							<br><span class="description"><?php esc_html_e( 'No time = 11:59 PM site time. Clear the date to hide the deadline.', 'swat-tournament-os-child' ); ?></span>
						</td>
						<td>
							<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-waitlist"><?php echo esc_html( sprintf( /* translators: %s: event name. */ __( 'Waitlist link for %s', 'swat-tournament-os-child' ), $parts['name'] ) ); ?></label>
							<input type="url" id="<?php echo esc_attr( $id ); ?>-waitlist" name="<?php echo esc_attr( 'swat_waitlist[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $waitlist[ $key ] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Uses the default', 'swat-tournament-os-child' ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $orphans ) : ?>
				<h2><?php esc_html_e( 'Saved entries for events no longer listed', 'swat-tournament-os-child' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These are kept until you delete them (for example after Playbook changed an event date).', 'swat-tournament-os-child' ); ?></p>
				<table class="widefat striped" role="presentation">
					<tbody>
					<?php
					foreach ( $orphans as $key => $raw ) :
						$rec = swat_child_sanitize_record( $raw );
						?>
						<tr>
							<td><code><?php echo esc_html( (string) $key ); ?></code></td>
							<td><?php echo esc_html( ( $rec['status'] ? $statuses[ $rec['status'] ] : __( 'Auto', 'swat-tournament-os-child' ) ) . ( $rec['deadline'] ? ' · ' . $rec['deadline'] : '' ) ); ?></td>
							<td><label><input type="checkbox" name="swat_reg_delete[]" value="<?php echo esc_attr( (string) $key ); ?>"> <?php esc_html_e( 'Delete', 'swat-tournament-os-child' ); ?></label></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php submit_button( __( 'Save registration settings', 'swat-tournament-os-child' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Handle the save request.
 *
 * @return void
 */
function swat_child_handle_registration_save() {
	if ( ! current_user_can( swat_child_registration_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'swat-tournament-os-child' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'swat_child_save_registration', 'swat_child_nonce' );

	$allowed = array();
	foreach ( function_exists( 'swat_os_event_directory' ) ? swat_os_event_directory() : array() as $event ) {
		$parts = swat_child_event_parts( $event );
		if ( '' !== $parts['key'] ) {
			$allowed[ $parts['key'] ] = true;
		}
	}

	$records = swat_child_registration_records();
	foreach ( array_keys( $records ) as $existing_key ) {
		$allowed[ (string) $existing_key ] = true;
	}

	$posted = ( isset( $_POST['swat_reg'] ) && is_array( $_POST['swat_reg'] ) ) ? wp_unslash( $_POST['swat_reg'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
	foreach ( $posted as $key => $raw ) {
		$key = (string) $key;
		if ( ! isset( $allowed[ $key ] ) || ! is_array( $raw ) ) {
			continue;
		}
		$clean = swat_child_sanitize_record( $raw );
		if ( '' === $clean['status'] && '' === $clean['deadline'] ) {
			unset( $records[ $key ] );
			continue;
		}
		$clean['updated']    = time();
		$clean['updated_by'] = get_current_user_id();
		$records[ $key ]     = $clean;
	}

	$delete = ( isset( $_POST['swat_reg_delete'] ) && is_array( $_POST['swat_reg_delete'] ) ) ? wp_unslash( $_POST['swat_reg_delete'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared to stored keys only.
	foreach ( $delete as $key ) {
		unset( $records[ (string) $key ] );
	}

	update_option( SWAT_CHILD_REG_OPTION, $records, false );

	// Waitlist links: same allowed keys plus the default; blank or invalid URLs are removed.
	$links       = swat_child_waitlist_links();
	$posted_wait = ( isset( $_POST['swat_waitlist'] ) && is_array( $_POST['swat_waitlist'] ) ) ? wp_unslash( $_POST['swat_waitlist'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
	foreach ( $posted_wait as $key => $url ) {
		$key = (string) $key;
		if ( '_default' !== $key && ! isset( $allowed[ $key ] ) ) {
			continue;
		}
		$clean = swat_child_sanitize_waitlist_url( is_string( $url ) ? $url : '' );
		if ( '' === $clean ) {
			unset( $links[ $key ] );
		} else {
			$links[ $key ] = $clean;
		}
	}
	foreach ( $delete as $key ) {
		unset( $links[ (string) $key ] );
	}
	update_option( SWAT_CHILD_WAITLIST_OPTION, $links, false );

	wp_safe_redirect( add_query_arg( 'swat-reg-saved', '1', admin_url( 'admin.php?page=swat-registration-status' ) ) );
	exit;
}
add_action( 'admin_post_swat_child_save_registration', 'swat_child_handle_registration_save' );

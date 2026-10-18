<?php
/**
 * Registration status and deadline layer.
 *
 * Adds an editable status override and registration deadline per tournament and
 * resolves what visitors see. It READS the parent theme's Playbook helpers
 * (swat_os_registration_event / swat_os_registration_sold_out) and never writes
 * to Playbook, swat_game posts, or the sync. Admin entries live in one option.
 *
 * Status precedence (first match wins):
 *  1. Manual cancellation, then Playbook cancellation.
 *  2. Restrictive admin override (Sold Out/Closed/Complete).
 *  3. Ended event, Playbook sold out, then expired deadline.
 *  4. Open/Limited override, otherwise Open.
 *
 * Playbook cancellation and sold-out signals cannot be reopened by a stale Open/Limited choice. Restrictive manual closures remain effective.
 *
 * @package SwatTournamentOSChild
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SWAT_CHILD_REG_OPTION' ) ) {
	define( 'SWAT_CHILD_REG_OPTION', 'swat_child_registration' );
}

/**
 * Supported statuses (slug => label).
 *
 * @return array<string,string>
 */
function swat_child_registration_statuses() {
	return array(
		'open'      => __( 'Open', 'swat-tournament-os-child' ),
		'limited'   => __( 'Limited', 'swat-tournament-os-child' ),
		'sold_out'  => __( 'Sold Out', 'swat-tournament-os-child' ),
		'closed'    => __( 'Closed', 'swat-tournament-os-child' ),
		'complete'  => __( 'Complete', 'swat-tournament-os-child' ),
		'cancelled' => __( 'Cancelled', 'swat-tournament-os-child' ),
	);
}

/**
 * Validate a YYYY-MM-DD calendar date.
 *
 * @param mixed $value Candidate.
 * @return bool
 */
function swat_child_valid_date( $value ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Normalise a parent directory row or a homepage-normalised row.
 *
 * @param mixed $event Event array (event_name/start_date/end_date/slug or name/start/end/slug).
 * @return array{name:string,slug:string,start:string,end:string,key:string}
 */
function swat_child_event_parts( $event ) {
	$event = is_array( $event ) ? $event : array();
	$name  = (string) ( $event['event_name'] ?? $event['name'] ?? '' );
	$slug  = sanitize_title( (string) ( $event['slug'] ?? $name ) );
	$start = (string) ( $event['start_date'] ?? $event['start'] ?? '' );
	$end   = (string) ( $event['end_date'] ?? $event['end'] ?? '' );
	$start = swat_child_valid_date( $start ) ? $start : '';
	$end   = swat_child_valid_date( $end ) ? $end : $start;

	return array(
		'name'  => $name,
		'slug'  => $slug,
		'start' => $start,
		'end'   => $end,
		'key'   => ( '' !== $slug && '' !== $start ) ? $slug . '|' . $start : '',
	);
}

/**
 * Sanitise one stored record.
 *
 * @param mixed $raw Raw record.
 * @return array{status:string,deadline:string,deadline_time:string}
 */
function swat_child_sanitize_record( $raw ) {
	$raw      = is_array( $raw ) ? $raw : array();
	$statuses = swat_child_registration_statuses();

	$status = isset( $raw['status'] ) ? sanitize_key( (string) $raw['status'] ) : '';
	if ( ! isset( $statuses[ $status ] ) ) {
		$status = '';
	}

	$deadline = isset( $raw['deadline'] ) ? trim( (string) $raw['deadline'] ) : '';
	if ( ! swat_child_valid_date( $deadline ) ) {
		$deadline = '';
	}

	$time = isset( $raw['deadline_time'] ) ? trim( (string) $raw['deadline_time'] ) : '';
	if ( '' === $deadline || ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
		$time = '';
	}

	return array(
		'status'        => $status,
		'deadline'      => $deadline,
		'deadline_time' => $time,
	);
}

/**
 * All stored records (key => record).
 *
 * @return array<string,array>
 */
function swat_child_registration_records() {
	$records = get_option( SWAT_CHILD_REG_OPTION, array() );
	return is_array( $records ) ? $records : array();
}

/**
 * One sanitised record by event key.
 *
 * @param string $key slug|start_date.
 * @return array{status:string,deadline:string,deadline_time:string}
 */
function swat_child_registration_record( $key ) {
	$records = swat_child_registration_records();
	return swat_child_sanitize_record( $records[ $key ] ?? array() );
}

/**
 * Deadline as a timestamp in the site timezone (0 when none).
 * With no time entered the deadline is 11:59 PM on the entered date.
 *
 * @param array $record Sanitised record.
 * @return int
 */
function swat_child_deadline_timestamp( $record ) {
	if ( empty( $record['deadline'] ) ) {
		return 0;
	}
	$time = ! empty( $record['deadline_time'] ) ? $record['deadline_time'] : '23:59';
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $record['deadline'] . ' ' . $time, wp_timezone() );
	return $date ? $date->getTimestamp() : 0;
}

/**
 * Pure precedence rule. No WordPress calls, so it can be unit tested.
 *
 * @param array $in {
 *     @type string $override        Admin override slug or ''.
 *     @type bool   $ended           Event end date is before today.
 *     @type bool   $pb_cancelled    Playbook lifecycle status is Cancelled.
 *     @type bool   $pb_sold_out     Playbook public SOLD OUT detected.
 *     @type bool   $deadline_passed Admin deadline exists and has passed.
 * }
 * @return array{0:string,1:string} [ status slug, source ].
 */
function swat_child_resolve_status( $in ) {
	$override = (string) ( $in['override'] ?? '' );

	// Restrictive manual decisions remain; stale availability cannot reopen an official cancellation.
	if ( 'cancelled' === $override ) { return array( 'cancelled', 'admin' ); }
	if ( ! empty( $in['pb_cancelled'] ) ) { return array( 'cancelled', 'playbook' ); }
	if ( in_array( $override, array( 'sold_out', 'closed', 'complete' ), true ) ) { return array( $override, 'admin' ); }
	if ( ! empty( $in['ended'] ) ) { return array( 'complete', 'date' ); }
	if ( ! empty( $in['pb_sold_out'] ) ) { return array( 'sold_out', 'playbook' ); }
	if ( ! empty( $in['deadline_passed'] ) ) { return array( 'closed', 'deadline' ); }
	if ( in_array( $override, array( 'open', 'limited' ), true ) ) { return array( $override, 'admin' ); }
	return array( 'open', 'default' );
}

/**
 * Read the parent's cached SOLD OUT result without making an HTTP request.
 * Depends on the parent's transient key (swat_pb_soldout_ + md5(EventId)).
 *
 * @param array $row Playbook feed row.
 * @return bool
 */
function swat_child_cached_sold_out( $row ) {
	$event_id = sanitize_key( (string) ( $row['EventId'] ?? '' ) );
	if ( '' === $event_id ) {
		return false;
	}
	$cached = get_transient( 'swat_pb_soldout_' . md5( $event_id ) );
	return is_array( $cached ) && ! empty( $cached['sold_out'] );
}

/**
 * Playbook signals for an event (read only).
 *
 * @param array $event Event row.
 * @param bool  $fetch True allows the parent's live SOLD OUT check (cached 10 min by the parent).
 * @return array{matched:bool,cancelled:bool,sold_out:bool}
 */
function swat_child_playbook_signals( $event, $fetch ) {
	$signals = array(
		'matched'   => false,
		'cancelled' => false,
		'sold_out'  => false,
	);

	if ( ! function_exists( 'swat_os_registration_event' ) ) {
		return $signals;
	}

	$row = swat_os_registration_event( $event );
	if ( ! is_array( $row ) || ! $row ) {
		return $signals;
	}

	$signals['matched'] = true;
	$pb_status          = strtoupper( sanitize_text_field( (string) ( $row['Status'] ?? '' ) ) );
	$signals['cancelled'] = in_array( $pb_status, array( 'CANCELLED', 'CANCELED' ), true );

	if ( $fetch && function_exists( 'swat_os_registration_sold_out' ) ) {
		$signals['sold_out'] = (bool) swat_os_registration_sold_out( $event );
	} else {
		$signals['sold_out'] = swat_child_cached_sold_out( $row );
	}

	return $signals;
}

/**
 * Resolve the public registration state for an event.
 *
 * @param array $event Event row.
 * @param array $args  {
 *     @type bool   $fetch           Allow the live SOLD OUT check (default false: cache only).
 *     @type string $end             Override end date (YYYY-MM-DD), e.g. the last posted game date.
 *     @type bool   $ignore_override Ignore the admin record (used to preview Auto).
 * }
 * @return array
 */
function swat_child_registration_state( $event, $args = array() ) {
	$args  = wp_parse_args(
		$args,
		array(
			'fetch'           => false,
			'end'             => '',
			'ignore_override' => false,
		)
	);
	$parts = swat_child_event_parts( $event );
	$end   = swat_child_valid_date( (string) $args['end'] ) ? (string) $args['end'] : $parts['end'];
	$today = current_time( 'Y-m-d' );

	$record = ( '' !== $parts['key'] && ! $args['ignore_override'] )
		? swat_child_registration_record( $parts['key'] )
		: swat_child_sanitize_record( array() );

	$ended           = ( '' !== $end && $end < $today );
	$signals         = swat_child_playbook_signals( $event, (bool) $args['fetch'] );
	$deadline_ts     = swat_child_deadline_timestamp( $record );
	$deadline_passed = ( $deadline_ts > 0 && $deadline_ts < time() );

	list( $status, $source ) = swat_child_resolve_status(
		array(
			'override'        => $record['status'],
			'ended'           => $ended,
			'pb_cancelled'    => $signals['cancelled'],
			'pb_sold_out'     => $signals['sold_out'],
			'deadline_passed' => $deadline_passed,
		)
	);

	$notes = array();
	if ( 'admin' === $source || ( '' !== $record['status'] && 'date' === $source ) ) {
		if ( $signals['cancelled'] && 'cancelled' !== $record['status'] ) {
			$notes[] = __( 'Playbook reports this event as Cancelled; your override is hiding that.', 'swat-tournament-os-child' );
		}
		if ( $signals['sold_out'] && 'sold_out' !== $record['status'] ) {
			$notes[] = __( 'Playbook shows SOLD OUT; your override is hiding that.', 'swat-tournament-os-child' );
		}
		if ( $deadline_passed && in_array( $record['status'], array( 'open', 'limited' ), true ) ) {
			$notes[] = __( 'The deadline has passed but the override keeps registration shown as available.', 'swat-tournament-os-child' );
		}
		if ( $ended && in_array( $record['status'], array( 'open', 'limited' ), true ) ) {
			$notes[] = __( 'The event has ended, so Open/Limited is ignored and Complete is shown.', 'swat-tournament-os-child' );
		}
	}

	$statuses = swat_child_registration_statuses();
	$state    = array(
		'status'          => $status,
		'label'           => $statuses[ $status ] ?? ucfirst( $status ),
		'source'          => $source,
		'can_register'    => in_array( $status, array( 'open', 'limited' ), true ),
		'record'          => $record,
		'deadline_ts'     => $deadline_ts,
		'deadline_passed' => $deadline_passed,
		'deadline_label'  => '',
		'signals'         => $signals,
		'notes'           => $notes,
		'key'             => $parts['key'],
		'name'            => $parts['name'],
	);

	$state['deadline_label'] = swat_child_deadline_label( $state );

	/**
	 * Filter the resolved registration state.
	 *
	 * @param array $state Resolved state.
	 * @param array $event Event row.
	 */
	return apply_filters( 'swat_child_registration_state', $state, $event );
}

/**
 * Deadline sentence, or '' when none was entered / not relevant.
 *
 * @param array $state Resolved state.
 * @return string
 */
function swat_child_deadline_label( $state ) {
	if ( empty( $state['deadline_ts'] ) || in_array( $state['status'], array( 'sold_out', 'complete', 'cancelled' ), true ) ) {
		return '';
	}

	$ts       = (int) $state['deadline_ts'];
	$has_time = ! empty( $state['record']['deadline_time'] );
	$format   = ( wp_date( 'Y', $ts ) !== wp_date( 'Y' ) ) ? 'D, M j, Y' : 'D, M j';
	$text     = wp_date( $format, $ts );
	if ( $has_time ) {
		$text .= ' ' . wp_date( 'g:i A', $ts );
	}

	if ( $ts < time() ) {
		/* translators: %s: formatted deadline. */
		return sprintf( __( 'Registration closed %s', 'swat-tournament-os-child' ), $text );
	}
	/* translators: %s: formatted deadline. */
	return sprintf( __( 'Registration closes %s', 'swat-tournament-os-child' ), $text );
}

/**
 * Status badge markup (text label, never colour alone).
 *
 * @param array $state Resolved state.
 * @return string
 */
function swat_child_status_badge( $state ) {
	return sprintf(
		'<span class="swat-reg-badge swat-reg-badge--%1$s">%2$s</span>',
		esc_attr( str_replace( '_', '-', (string) $state['status'] ) ),
		esc_html( 'sold_out' === $state['status'] ? __( 'Registration closed', 'swat-tournament-os-child' ) : (string) $state['label'] )
	);
}

/**
 * Deadline paragraph, or '' when no deadline applies.
 *
 * @param array $state Resolved state.
 * @return string
 */
function swat_child_deadline_html( $state ) {
	if ( '' === (string) $state['deadline_label'] ) {
		return '';
	}
	return '<p class="swat-reg-deadline">' . esc_html( (string) $state['deadline_label'] ) . '</p>';
}

/**
 * Registration call to action: a link when open/limited, a plain note otherwise.
 *
 * @param array  $state       Resolved state.
 * @param string $url         Registration URL.
 * @param string $link_class  CSS classes for the link.
 * @param string $label       Link text.
 * @return string
 */
function swat_child_register_action( $state, $url, $link_class, $label = 'REGISTER' ) {
	if ( ! empty( $state['can_register'] ) ) {
		return sprintf(
			'<a%1$s href="%2$s">%3$s</a>',
			'' === $link_class ? '' : ' class="' . esc_attr( $link_class ) . '"',
			esc_url( $url ),
			esc_html( $label )
		);
	}
	if ( 'complete' === $state['status'] ) {
		return '';
	}
	// 1.0.31: sold out / closed events offer a waitlist instead of a dead "closed" label.
	if ( function_exists( 'swat_child_waitlist_applies' ) && swat_child_waitlist_applies( (string) $state['status'] ) ) {
		$name = (string) ( $state['name'] ?? '' );
		return sprintf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( trim( $link_class . ' swat-waitlist-link' ) ),
			esc_url( swat_child_waitlist_url( (string) ( $state['key'] ?? '' ), $name ) ),
			'' === $name ? '' : ' aria-label="' . esc_attr( sprintf( /* translators: %s: event name. */ __( 'Join the waitlist for %s', 'swat-tournament-os-child' ), $name ) ) . '"',
			esc_html__( 'JOIN WAITLIST', 'swat-tournament-os-child' )
		);
	}
	$notes = array(
		'sold_out'  => __( 'REGISTRATION CLOSED', 'swat-tournament-os-child' ),
		'closed'    => __( 'REGISTRATION CLOSED', 'swat-tournament-os-child' ),
		'cancelled' => __( 'EVENT CANCELLED', 'swat-tournament-os-child' ),
	);
	return '<span class="swat-reg-unavailable">' . esc_html( $notes[ $state['status'] ] ?? strtoupper( (string) $state['label'] ) ) . '</span>';
}

/**
 * Event details block for the single tournament page. Mirrors the parent's
 * swat_os_render_registration_details() markup and CSS classes, but only shows
 * register calls to action when registration is actually available.
 *
 * @param array $event Event row.
 * @param array $state Resolved state.
 * @return void
 */
function swat_child_render_registration_details( $event, $state ) {
	$row = function_exists( 'swat_os_registration_event' ) ? swat_os_registration_event( $event ) : array();
	if ( ! $row ) {
		return;
	}

	$divisions   = is_array( $row['Divisions'] ?? null ) ? $row['Divisions'] : array();
	$description = html_entity_decode( wp_strip_all_tags( (string) ( $row['Description'] ?? '' ) ), ENT_QUOTES, 'UTF-8' );
	$format      = '';
	if ( preg_match( '/^(.+?)(?:Rosters|$)/su', trim( $description ), $match ) ) {
		$format = trim( $match[1] );
	}
	$reg_url = function_exists( 'swat_os_registration_url' ) ? swat_os_registration_url( $event ) : 'https://play.swatourneys.com/';
	$can     = ! empty( $state['can_register'] );
	?>
	<section class="swat-entry swat-entry--<?php echo esc_attr( str_replace( '_', '-', $state['status'] ) ); ?>" aria-labelledby="swat-entry-heading">
		<div class="swat-entry__heading">
			<div>
				<p class="swat-kicker"><?php esc_html_e( 'PLAN YOUR TOURNAMENT', 'swat-tournament-os-child' ); ?></p>
				<h2 id="swat-entry-heading"><?php esc_html_e( 'EVENT DETAILS', 'swat-tournament-os-child' ); ?></h2>
			</div>
			<?php
			echo $can // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				? swat_child_register_action( $state, $reg_url, 'swat-btn swat-btn-gold', __( 'REGISTER FOR THIS EVENT', 'swat-tournament-os-child' ) )
				: swat_child_status_badge( $state );
			?>
		</div>
		<?php echo swat_child_deadline_html( $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php if ( $format ) : ?>
			<p class="swat-entry__format"><?php echo esc_html( $format ); ?></p>
		<?php endif; ?>

		<?php if ( 'cancelled' !== $state['status'] && $divisions ) : ?>
			<div class="swat-entry__heading"><h3><?php echo $can ? esc_html__( 'WHO’S READY TO PLAY?', 'swat-tournament-os-child' ) : esc_html__( 'DIVISIONS', 'swat-tournament-os-child' ); ?></h3></div>
			<?php if ( $can ) : ?>
				<p class="swat-entry__note"><?php esc_html_e( 'Choose your division below to check availability and register.', 'swat-tournament-os-child' ); ?></p>
			<?php endif; ?>
			<div class="swat-entry__divisions">
				<?php
				foreach ( $divisions as $division ) :
					if ( ! is_array( $division ) || empty( $division['Age'] ) ) {
						continue;
					}
					$cost = $division['Cost'] ?? null;
					?>
					<article class="swat-entry__division">
						<h4><?php echo esc_html( trim( $division['Age'] . ' ' . ( $division['Subdivison'] ?? '' ) ) ); ?></h4>
						<dl>
							<div>
								<dt><?php esc_html_e( 'Entry fee', 'swat-tournament-os-child' ); ?></dt>
								<dd>
									<?php
									if ( is_numeric( $cost ) ) {
										echo esc_html( '$' . number_format_i18n( (float) $cost, ( (float) $cost === floor( (float) $cost ) ) ? 0 : 2 ) );
									} else {
										esc_html_e( 'Confirm when registering', 'swat-tournament-os-child' );
									}
									?>
								</dd>
							</div>
						</dl>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( $can ) : ?>
				<p><?php echo swat_child_register_action( $state, $reg_url, 'swat-btn swat-btn-gold', __( 'SECURE YOUR TEAM’S SPOT', 'swat-tournament-os-child' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
			<?php endif; ?>
			<p class="swat-entry__note"><?php esc_html_e( 'Confirm division availability and final entry fees when registering.', 'swat-tournament-os-child' ); ?></p>
		<?php endif; ?>

		<details class="swat-entry__venues">
			<summary><?php esc_html_e( 'Fields & directions', 'swat-tournament-os-child' ); ?></summary>
			<?php
			foreach ( (array) ( $row['Venues'] ?? array() ) as $venue ) :
				if ( ! is_array( $venue ) ) {
					continue;
				}
				$address = trim( implode( ', ', array_filter( array( $venue['Street'] ?? '', $venue['City'] ?? '', $venue['State'] ?? '', $venue['Zip'] ?? '' ) ) ) );
				?>
				<p>
					<strong><?php echo esc_html( $venue['Name'] ?? '' ); ?></strong><br>
					<?php echo esc_html( $address ); ?><br>
					<a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( ( $venue['Name'] ?? '' ) . ' ' . $address ) ); ?>"><?php esc_html_e( 'OPEN DIRECTIONS', 'swat-tournament-os-child' ); ?></a>
				</p>
			<?php endforeach; ?>
			<p><?php esc_html_e( 'Check your published schedule for your assigned field.', 'swat-tournament-os-child' ); ?></p>
		</details>
		<nav class="swat-entry__links" aria-label="<?php esc_attr_e( 'Before you register', 'swat-tournament-os-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/admissions/' ) ); ?>"><?php esc_html_e( 'Admission', 'swat-tournament-os-child' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>"><?php esc_html_e( 'Division rules', 'swat-tournament-os-child' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/refund-policy/' ) ); ?>"><?php esc_html_e( 'Refund policy', 'swat-tournament-os-child' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Ask SWAT', 'swat-tournament-os-child' ); ?></a>
		</nav>
	</section>
	<?php
}

/* ---------------------------------------------------------------------------
 * Background warm-up of the parent's SOLD OUT cache.
 *
 * The homepage reads the cache only (no HTTP in the page request). This job
 * calls the parent's own detector every 10 minutes so the cache is rarely cold.
 * ------------------------------------------------------------------------- */

/**
 * Add a ten-minute cron interval.
 *
 * @param array $schedules Schedules.
 * @return array
 */
function swat_child_cron_schedules( $schedules ) {
	$schedules['swat_child_ten_minutes'] = array(
		'interval' => 10 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every ten minutes (SWAT)', 'swat-tournament-os-child' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'swat_child_cron_schedules' );

/**
 * Schedule the warm-up job once.
 *
 * @return void
 */
function swat_child_schedule_warmup() {
	if ( is_customize_preview() ) {
		return; // A preview must not write to the live site's cron table.
	}
	if ( ! wp_next_scheduled( 'swat_child_warm_availability' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'swat_child_ten_minutes', 'swat_child_warm_availability' );
	}
}
add_action( 'init', 'swat_child_schedule_warmup' );

/**
 * Remove the job when this child theme is deactivated.
 *
 * @return void
 */
function swat_child_unschedule_warmup() {
	wp_clear_scheduled_hook( 'swat_child_warm_availability' );
}
add_action( 'switch_theme', 'swat_child_unschedule_warmup' );

/**
 * Warm the parent's SOLD OUT transient for upcoming events.
 *
 * @return void
 */
function swat_child_warm_availability_run() {
	if ( ! function_exists( 'swat_os_event_directory' ) || ! function_exists( 'swat_os_registration_sold_out' ) ) {
		return;
	}

	$today = current_time( 'Y-m-d' );
	$count = 0;
	foreach ( swat_os_event_directory() as $event ) {
		$parts = swat_child_event_parts( $event );
		if ( '' === $parts['end'] || $parts['end'] < $today ) {
			continue;
		}
		swat_os_registration_sold_out( $event );
		++$count;
		if ( $count >= 12 ) {
			break;
		}
	}
}
add_action( 'swat_child_warm_availability', 'swat_child_warm_availability_run' );

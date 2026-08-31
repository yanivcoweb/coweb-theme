<?php
/**
 * Contact form handler.
 *
 * Written rather than plugged in, because the one rule about plugins here is
 * that nothing renders front-end markup we don't control — and every form
 * plugin does exactly that.
 *
 * Flow: POST to admin-post.php, validate, mail, redirect back with a status in
 * the query string. Post/Redirect/Get, so a refresh never resubmits.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const COWEB_CONTACT_ACTION   = 'coweb_contact';
const COWEB_CONTACT_TRANSIENT = 'coweb_contact_state_';

/**
 * Read the submission state stashed before the redirect, if any.
 *
 * Kept in a short-lived transient keyed to the visitor rather than the session,
 * so a failed submission can repopulate the form without the values ever
 * appearing in the URL.
 *
 * @return array{errors:array<string,string>,values:array<string,string>,status:string}
 */
function coweb_contact_state(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag, no action taken on it.
	$status = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : '';

	$empty = array(
		'errors' => array(),
		'values' => array(),
		'status' => in_array( $status, array( 'sent', 'error', 'failed', 'expired' ), true ) ? $status : '',
	);

	$key = coweb_contact_state_key();
	if ( '' === $key ) {
		return $empty;
	}

	$state = get_transient( COWEB_CONTACT_TRANSIENT . $key );

	if ( ! is_array( $state ) ) {
		return $empty;
	}

	delete_transient( COWEB_CONTACT_TRANSIENT . $key );

	return array_merge( $empty, $state );
}

/**
 * A per-visitor key that doesn't require a login or a cookie we set ourselves.
 */
function coweb_contact_state_key(): string {
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	if ( '' === $ip ) {
		return '';
	}

	return md5( $ip . '|' . $agent );
}

/**
 * Validate and send. Runs for logged-out and logged-in visitors alike.
 */
function coweb_handle_contact(): void {
	$redirect = wp_get_referer() ?: home_url( '/' );

	// Nonce first: everything after this trusts the request came from our form.
	if (
		! isset( $_POST['coweb_contact_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['coweb_contact_nonce'] ) ), COWEB_CONTACT_ACTION )
	) {
		wp_safe_redirect( add_query_arg( 'contact', 'expired', $redirect ) );
		exit;
	}

	// Honeypot. A real visitor never sees this field, so anything in it is a bot.
	// Answer with the success redirect so the bot has nothing to learn.
	if ( ! empty( $_POST['coweb_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'contact', 'sent', $redirect ) );
		exit;
	}

	$values = array(
		'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
		'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		'company' => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
		'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
	);

	$errors = array();

	if ( '' === $values['name'] ) {
		$errors['name'] = 'נא למלא שם.';
	}

	if ( '' === $values['email'] || ! is_email( $values['email'] ) ) {
		$errors['email'] = 'נא למלא כתובת אימייל תקינה.';
	}

	if ( '' === $values['message'] ) {
		$errors['message'] = 'נא לכתוב במה אפשר לעזור.';
	}

	$key = coweb_contact_state_key();

	if ( $errors ) {
		if ( '' !== $key ) {
			set_transient(
				COWEB_CONTACT_TRANSIENT . $key,
				array(
					'errors' => $errors,
					'values' => $values,
				),
				5 * MINUTE_IN_SECONDS
			);
		}

		wp_safe_redirect( add_query_arg( 'contact', 'error', $redirect ) . '#contact-form' );
		exit;
	}

	$to = get_field( 'contact_email', 'option' ) ?: get_option( 'admin_email' );

	$body = sprintf(
		"שם: %s\nאימייל: %s\nחברה: %s\n\n%s",
		$values['name'],
		$values['email'],
		$values['company'] ?: '—',
		$values['message']
	);

	// From: our own domain, so SPF/DKIM hold. The visitor goes in Reply-To,
	// which is what "reply" should actually do.
	$sent = wp_mail(
		$to,
		sprintf( 'פנייה חדשה מהאתר — %s', $values['name'] ),
		$body,
		array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', $values['name'], $values['email'] ),
		)
	);

	wp_safe_redirect( add_query_arg( 'contact', $sent ? 'sent' : 'failed', $redirect ) . '#contact-form' );
	exit;
}

add_action( 'admin_post_nopriv_' . COWEB_CONTACT_ACTION, 'coweb_handle_contact' );
add_action( 'admin_post_' . COWEB_CONTACT_ACTION, 'coweb_handle_contact' );

/**
 * Hand the form's state to Twig.
 */
add_filter(
	'timber/twig/functions',
	static function ( array $functions ): array {
		$functions['contact_state'] = array( 'callable' => 'coweb_contact_state' );
		$functions['contact_nonce'] = array(
			'callable' => static fn(): string => wp_create_nonce( COWEB_CONTACT_ACTION ),
		);
		$functions['admin_post_url'] = array(
			'callable' => static fn(): string => esc_url( admin_url( 'admin-post.php' ) ),
		);

		// The page the form sits on, for the _wp_http_referer field. Without it
		// wp_get_referer() falls back to the Referer header alone, and a
		// visitor whose browser withholds that header is redirected to the home
		// page after submitting instead of back to the form and its notice.
		$functions['contact_referer'] = array(
			'callable' => static fn(): string => esc_url( get_permalink() ?: home_url( '/' ) ),
		);

		return $functions;
	}
);

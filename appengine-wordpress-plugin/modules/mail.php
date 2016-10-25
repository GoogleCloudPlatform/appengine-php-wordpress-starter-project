<?php
/**
 * Send emails via the App Engine mail infrastructure
 *
 * Copyright 2013 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package WordPress
 * @subpackage Mail
 */

namespace google\appengine\WordPress\Mail {

	use google\appengine\api\app_identity\AppIdentityService;
	use google\appengine\api\mail\Message;
	use Exception;

	/**
	 * Admin settings for the Mail module
	 *
	 * @package WordPress
	 * @subpackage Mail
	 */
	class Admin {
		/**
		 * Register our action
		 */
		public static function bootstrap(){
			add_action( 'appengine_register_settings', __CLASS__ . '::register_google_settings' );
		}

		/**
		 * Register our admin settings and settings UI
		 *
		 * @wp-action appengine_register_settings
		 */
		public static function register_google_settings() {
			register_setting( 'appengine_settings', 'appengine_email_enable', __CLASS__ . '::enable_validation' );
			register_setting( 'appengine_settings', 'appengine_email', __CLASS__ . '::email_validation' );

			add_settings_section( 'appengine-mail', __( 'Email Settings', 'appengine' ), __CLASS__ . '::section_text', 'appengine' );
			add_settings_field( 'appengine_email_enable', __( 'Use App Engine Email Service', 'appengine' ), __CLASS__ . '::enable_input', 'appengine', 'appengine-mail', [ 'label_for' => 'appengine_email_enable' ] );
			add_settings_field( 'appengine_email', __( 'App Email Address', 'appengine' ), __CLASS__ . '::email_input', 'appengine', 'appengine-mail', [ 'label_for' => 'appengine_email' ] );
		}

		/**
		 * Informational text for the settings section
		 */
		public static function section_text() {
?>
	<p>
		<?php _e( "Emails from WordPress will be sent through the App Engine email infrastructure, including password emails and those sent from plugins. If you're not happy with the default email address, set it here.", 'appengine' ) ?>
	</p>
<?php
		}

		/**
		 * Output the checkbox for the enable checkbox
		 */
		public static function enable_input() {
			$enabled = get_option( 'appengine_email_enable', true );
			echo '<input id="appengine_email_enable" name="appengine_email_enable"
				type="checkbox" ' . checked( $enabled, true, false ) . ' />';
		}

		/**
		 * Validate the enable checkbox input
		 *
		 * @param mixed $input
		 * @return bool
		 */
		public static function enable_validation( $input ) {
			return (bool) $input;
		}

		/**
		 * Output the input field for the email address override
		 */
		public static function email_input() {
			$email = get_option( 'appengine_email', '' );
			echo '<input id="appengine_email" name="appengine_email"
				type="email" value="' . esc_attr( $email ) . '" />';

			$desc = __( 'This address can be the email address of a registered administrator (developer) of your application or an address of the form <code>%1$s</code>.<br />If this is not set, this will default to <code>%2$s</code>', 'appengine' );
			echo '<p class="description">'
				. sprintf(
					$desc,
					get_default_email( '[string]' ),
					get_default_email()
				) . '</code>.</p>';
		}

		/**
		 * Validate the App Engine email address
		 *
		 * @param mixed $input
		 * @return string
		 */
		public static function email_validation( $input ) {
			$email = get_option( 'appengine_email', '' );

			if ( is_email( $input ) || empty($input) ) {
				$email = $input;
			}
			else {
				add_settings_error( 'appengine_email', 'invalid-email', __( 'You have entered an invalid e-mail address.', 'appengine' ) );
			}
			return $email;
		}
	}

	/**
	 * Get the default email address for App Engine
	 *
	 * @param string $user User part of the email address (typically 'wordpress')
	 * @return string Email address with the correct email domain
	 */
	function get_default_email( $user = 'wordpress' ) {
		// Let's build an email address for the app via the app identity api
		$service_account = new AppIdentityService();
		$id = $service_account->getApplicationId();
		if ( empty( $id ) ) {
			$service_account_name = $service_account->getServiceAccountName();
			$service_account_from_name = explode( '@', $service_account_name );
			$id = $service_account_from_name[0];
		}
		return $user . '@' . $id . '.appspotmail.com';
	}

	/**
	 * Send email
	 *
	 * This is based on {@see wp_mail()} which is in turn based on PHP's
	 * built-in mail() function. This is typically called from the overriden
	 * version of {@see wp_mail()} below.
	 *
	 * @param string|array $to          Array or comma-separated list of email addresses to send message.
	 * @param string       $subject     Email subject
	 * @param string       $message     Message contents
	 * @param string|array $headers     Optional. Additional headers.
	 * @param string|array $attachments Optional. Files to attach.
	 *
	 * @return bool Whether the email contents were sent successfully.
	 */
	function send_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {

		// Compact the input, apply the filters, and extract them back out
		extract( apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) ) );

		global $phpmailer;

		if ( ! is_object( $phpmailer ) || ! is_a( $phpmailer, 'Message' ) ) {
			$phpmailer = new Message();
		}
		$cc  = array();
		$bcc = array();

		// Headers
		if ( empty( $headers ) ) {
			$headers = array();
		}
		else {
			if ( ! is_array( $headers ) ) {
				// Explode the headers out, so this function can take both
				// string headers and an array of headers.
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			}
			else {
				$tempheaders = $headers;
			}
			$headers = array();

			// If it's actually got contents
			if ( ! empty( $tempheaders ) ) {
				// Iterate through the raw headers
				foreach ( (array) $tempheaders as $header ) {
					if ( false === strpos( $header, ':' ) ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts    = preg_split( '/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					// Explode them out
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew
					$name    = trim( $name );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a From: header if it's there
						case 'from':
							if ( false !== strpos( $content, '<' ) ) {
								// So... making my life hard again?
								$from_name = substr( $content, 0, strpos( $content, '<' ) - 1 );
								$from_name = str_replace( '"', '', $from_name );
								$from_name = trim( $from_name );

								$from_email = substr( $content, strpos( $content, '<' ) + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );
							}
							else {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( false !== strpos( $content, ';' ) ) {
								list( $type, $charset ) = explode( ';', $content );
								$content_type = trim( $type );
								if ( false !== stripos( $charset, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset ) );
								}
								elseif ( false !== stripos( $charset, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset ) );
									$charset  = '';
								}
							}
							else {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						default:
							// Add it to our grand headers array
							$headers[trim( $name )] = trim( $content );
							break;
					}
				}
			}
		}

		// Empty out the values that may be set
		$phpmailer->clearBcc();
		$phpmailer->clearCc();
		$phpmailer->clearReplyTo();
		$phpmailer->clearTo();

		// From email and name
		// If we don't have a name from the input headers
		if ( ! isset( $from_name ) ) {
			$from_name = 'App Engine';
		}

		/* If we don't have an email from the input headers default to wordpress@$sitename
		* Some hosts will block outgoing mail from this address if it doesn't exist but
		* there's no easy alternative. Defaulting to admin_email might appear to be another
		* option but some hosts may refuse to relay mail from an unknown domain. See
		* http://trac.wordpress.org/ticket/5007.
		*/

		if ( ! isset( $from_email ) ) {
			$from_email = get_option( 'appengine_email', false );
			if ( ! $from_email ) {
				$from_email = get_default_email();
			}
		}

		// Plugin authors can override the potentially troublesome default
		// TODO: Currently, App Engine doesn't support a from name. We should
		//       come back to this and fix it if/when it does
		//$phpmailer->setSender( apply_filters( 'wp_mail_from_name', $from_name ) . " <" . apply_filters( 'wp_mail_from', $from_email ) . ">");
		$phpmailer->setSender( apply_filters( 'wp_mail_from', $from_email ) );

		// Set destination addresses
		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}

		foreach ( (array) $to as $recipient ) {
			try {
				// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
				$recipient_name = '';
				if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
					if ( count( $matches ) == 3 ) {
						$recipient_name = $matches[1];
						$recipient      = $matches[2];
					}
				}
				$phpmailer->addTo( $recipient, $recipient_name );
			} catch ( Exception $e ) {
				syslog( LOG_DEBUG, 'Mail error: ' . $e->getMessage() );
				continue;
			}
		}

		// Add any CC and BCC recipients
		$cc  = array_filter( $cc );
		$bcc = array_filter( $bcc );

		if ( ! empty( $cc ) ) {
			foreach ( (array) $cc as $recipient ) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient      = $matches[2];
						}
					}
					$phpmailer->addCc( $recipient, $recipient_name );
				} catch ( Exception $e ) {
					syslog( LOG_DEBUG, 'Mail error: ' . $e->getMessage() );
					continue;
				}
			}
		}

		if ( ! empty( $bcc ) ) {
			foreach ( (array) $bcc as $recipient ) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient      = $matches[2];
						}
					}
					$phpmailer->addBcc( $recipient, $recipient_name );
				} catch ( Exception $e ) {
					syslog( LOG_DEBUG, 'Mail error: ' . $e->getMessage() );
					continue;
				}
			}
		}

		// Set Content-Type and charset
		// If we don't have a content-type from the input headers
		if ( ! isset( $content_type ) ) {
			$content_type = 'text/plain';
		}

		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		// Set whether it's plaintext, depending on $content_type
		if ( 'text/html' == $content_type ) {
			$phpmailer->setHtmlBody( $message );
		}
		else {
			$phpmailer->setTextBody( $message );
		}

		$phpmailer->setSubject( $subject );
		// If we don't have a charset from the input headers
		if ( !isset( $charset ) ) {
			$charset = get_bloginfo( 'charset' );
		}

		// Set the content-type and charset
		//$phpmailer->charsset = apply_filters( 'wp_mail_charset', $charset );

		// Set custom headers
		if ( !empty( $headers ) ) {
			if ( isset( $headers['MIME-Version'] ) ) {
				unset( $headers['MIME-Version'] );
			}
			$phpmailer->addHeaderArray( $headers );

			if ( false !== stripos( $content_type, 'multipart' ) && ! empty($boundary) ) {
				$phpmailer->addHeaderArray( 'Content-Type', sprintf( "%s;\n\t boundary=\"%s\"", $content_type, $boundary ) );
			}
		}

		if ( !empty( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				try {
					$name = basename( $attachment );
					$data = file_get_contents( $attachment );
					$phpmailer->addAttachment( $name, $data );
				} catch ( Exception $e ) {
					syslog( LOG_DEBUG, 'Mail error: ' . $e->getMessage() );
					continue;
				}
			}
		}

		// Send!
		$phpmailer->send();

		return true;
	}

	Admin::bootstrap();
}

namespace {
	use google\appengine\WordPress;
	use google\appengine\WordPress\Mail;

	if ( get_option( 'appengine_email_enable', true ) && !function_exists( 'wp_mail' ) ) {
		/**
		 * Send mail, similar to PHP's mail
		 *
		 * @uses \google\appengine\WordPress\Mail\send_mail()
		 *
		 * @param string|array $to          Array or comma-separated list of email addresses to send message.
		 * @param string       $subject     Email subject
		 * @param string       $message     Message contents
		 * @param string|array $headers     Optional. Additional headers.
		 * @param string|array $attachments Optional. Files to attach.
		 *
		 * @return bool Whether the email contents were sent successfully.
		 */
		function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
			$GLOBALS[ 'appengine_mail_last_error' ] = null;
			try {
				return Mail\send_mail( $to, $subject, $message, $headers, $attachments );
			} catch ( Exception $e ) {
				$GLOBALS[ 'appengine_mail_last_error' ] = $e;
				syslog( LOG_DEBUG, 'Mail error: ' . $e->getMessage() );
				return false;
			}
		}
	}
}

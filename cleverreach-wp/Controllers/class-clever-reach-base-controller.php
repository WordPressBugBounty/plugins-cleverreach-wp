<?php
/**
 * CleverReach WordPress Integration.
 *
 * @package CleverReach
 */

namespace CleverReach\WordPress\Controllers;

/**
 * Class Clever_Reach_Base_Controller
 *
 * @package CleverReach\WordPress\Controllers
 */
class Clever_Reach_Base_Controller {

	/**
	 * Nonce action used to protect state-changing requests against CSRF.
	 */
	const NONCE_ACTION = 'cr_save_actions';

	/**
	 * Request parameter that carries the nonce.
	 */
	const NONCE_PARAM = '_cr_nonce';

	/**
	 * Is request internal
	 *
	 * @var bool
	 */
	protected $is_internal = true;

	/**
	 * Actions that change state. They are accepted only via POST with a valid nonce.
	 *
	 * @var array
	 */
	protected $state_changing_actions = array();

	/**
	 * Processes request. Reads 'action' parameter and calls action method if provided
	 *
	 * @param string $action Action to be called.
	 */
	public function process( $action = '' ) {
		if ( $this->is_internal ) {
			$this->validate_internal_call();
		}

		if ( empty( $action ) ) {
			$action = $this->get_param( 'action' );
		}

		if ( $action ) {
			if ( $this->is_action_callable( $action ) ) {
				if ( $this->is_internal && in_array( $action, $this->state_changing_actions, true ) ) {
					$this->validate_state_changing_call();
				}

				$this->$action();
			} else {
				$this->die_json( array( 'error' => "Method $action does not exist!" ) );
			}
		}
	}

	/**
	 * Validates that the request comes from a logged-in user with the required capability.
	 * Redirects anonymous users to the login page and returns 403 for users without the capability.
	 */
	protected function validate_internal_call() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		if ( ! current_user_can( $this->get_required_capability() ) ) {
			$this->die_with_status( 403 );
		}
	}

	/**
	 * Returns capability required to access internal controller actions.
	 *
	 * @return string
	 */
	protected function get_required_capability() {
		return 'manage_options';
	}

	/**
	 * Validates that a state-changing request is sent via POST and carries a valid nonce.
	 * Returns 405 for non-POST requests and 403 for a missing or invalid nonce.
	 */
	protected function validate_state_changing_call() {
		if ( ! $this->is_post() ) {
			$this->die_with_status( 405 );
		}

		$nonce = isset( $_REQUEST[ self::NONCE_PARAM ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_PARAM ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->die_with_status( 403 );
		}
	}

	/**
	 * Checks whether action can be dispatched from request. Only public, non-static methods
	 * without required arguments, declared in a concrete controller, are allowed. This prevents
	 * invoking private helpers (e.g. Config::reset_token), constructors and base controller
	 * methods (e.g. process) directly via the action parameter.
	 *
	 * @param string $action Action name.
	 *
	 * @return bool
	 */
	private function is_action_callable( $action ) {
		if ( ! method_exists( $this, $action ) ) {
			return false;
		}

		$method = new \ReflectionMethod( $this, $action );

		return $method->isPublic()
			&& ! $method->isStatic()
			&& ! $method->isConstructor()
			&& 0 === $method->getNumberOfRequiredParameters()
			&& __CLASS__ !== $method->getDeclaringClass()->getName();
	}

	/**
	 * Sets response header content type to json, echos supplied $data as a json string and terminates request
	 *
	 * @param array $data Array to be returned as a json response.
	 */
	protected function die_json( array $data ) {
		wp_send_json( $data );
	}

	/**
	 * Checks whether current request is POST
	 *
	 * @return bool
	 */
	protected function is_post() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Gets request parameter if exists. Otherwise, returns null
	 *
	 * @param string $key Key to be checked in request.
	 *
	 * @return mixed
	 */
	protected function get_param( $key ) {
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : null;
	}

	/**
	 * Gets raw request
	 *
	 * @return string
	 */
	protected function get_raw_input() {
		return file_get_contents( 'php://input' );
	}

	/**
	 * Returns 404 response and terminates request
	 */
	protected function redirect_404() {
		status_header( 404 );
		nocache_headers();

		require get_404_template();

		exit();
	}

	/**
	 * Dies with defined status code
	 *
	 * @param int $status response status code.
	 */
	protected function die_with_status( $status ) {
		status_header( $status );
		die();
	}

	/**
	 * Downloads the file
	 *
	 * @param string $file File path.
	 */
	protected function return_file( $file ) {
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );

		exit();
	}
}

<?php
/**
 * CleverReach WordPress Integration.
 *
 * @package CleverReach
 */

namespace CleverReach\WordPress\Controllers;

use CleverReach\WordPress\Components\InfrastructureServices\Config_Service;
use CleverReach\WordPress\Components\Utility\Schema_Provider;
use CleverReach\WordPress\Components\Utility\Search_Results_Provider;
use CleverReach\WordPress\IntegrationCore\BusinessLogic\Utility\ArticleSearch\Conditions;
use CleverReach\WordPress\IntegrationCore\Infrastructure\Interfaces\Required\Configuration;
use CleverReach\WordPress\IntegrationCore\Infrastructure\ServiceRegister;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Clever_Reach_Article_Search_Controller
 *
 * @package CleverReach\WordPress\Controllers
 */
class Clever_Reach_Article_Search_Controller extends Clever_Reach_Base_Controller {

	/**
	 * Schema provider
	 *
	 * @var Schema_Provider
	 */
	private $schema_provider;

	/**
	 * Search results provider
	 *
	 * @var Search_Results_Provider
	 */
	private $search_results_provider;

	/**
	 * Clever_Reach_Article_Search_Controller constructor
	 */
	public function __construct() {
		$this->is_internal = false;
	}

	/**
	 * Processes request. Every action on this controller is publicly reachable,
	 * so the request must carry the product search endpoint password.
	 *
	 * @param string $action Action to be called.
	 */
	public function process( $action = '' ) {
		$this->validate_password();

		parent::process( $action );
	}

	/**
	 * Gets all searchable items
	 */
	public function cleverreach_items() {
		$this->die_json( $this->get_schema_provider()->get_searchable_items()->toArray() );
	}

	/**
	 * Gets searchable schema for specific type
	 */
	public function cleverreach_schema() {
		try {
			$this->die_json( $this->get_schema_provider()->get_schema( $this->get_param( 'type' ) )->toArray() );
		} catch ( \Exception $exception ) {
			$this->die_json(
				array(
					'status'  => 'error',
					'message' => $exception->getMessage(),
				)
			);
		}
	}

	/**
	 * Gets search item results based on type and filters
	 */
	public function cleverreach_search() {
		try {
			$type   = $this->get_param( 'type' );
			$filter = $this->get_param( 'filter' );
			$id     = $this->get_param( 'id' );
			if ( null !== $id ) {
				$equal_condition = Conditions::EQUALS;
				$filter          = "ID $equal_condition $id";
			}

			$this->die_json( $this->get_search_results_provider()->get_search_results( $type, $filter )->toArray() );
		} catch ( \Exception $exception ) {
			$this->die_json(
				array(
					'status'  => 'error',
					'message' => $exception->getMessage(),
				)
			);
		}
	}

	/**
	 * Public endpoint for article
	 */
	public function run() {
		$action = $this->get_param( 'get' );

		$this->validate_request( $action );

		if ( 'filter' === $action ) {
			$response = $this->get_filters();
		} else {
			$response = $this->search();
		}

		$this->die_json( $response );
	}

	/**
	 * Checks if all parameters are set as required.
	 *
	 * @param string $action Requested action.
	 */
	private function validate_request( $action ) {
		$id    = $this->get_param( 'id' );
		$title = $this->get_param( 'title' );

		if ( null === $action
			|| ( 'search' === $action && empty( $id ) && empty( $title ) )
			|| ! $this->is_post()
			|| ! in_array( $action, array( 'filter', 'search' ), true )
		) {
			status_header( 404 );

			exit();
		}
	}

	/**
	 * Returns search filters, used to search for data.
	 *
	 * @return array
	 */
	private function get_filters() {
		return array(
			array(
				'name'        => __( 'Article ID', 'cleverreach-wp' ),
				'description' => '',
				'required'    => false,
				'query_key'   => 'id',
				'type'        => 'input',
			),
			array(
				'name'        => __( 'Article Title', 'cleverreach-wp' ),
				'description' => '',
				'required'    => false,
				'query_key'   => 'title',
				'type'        => 'input',
			),
		);
	}

	/**
	 * Performs a search using search term provided in the request.
	 *
	 * @return array
	 */
	private function search() {
		global $wpdb;
		$filters = array( "post_type IN ('post', 'page')" );

		$raw_id = $this->get_param('id');
		if ($raw_id !== '' && !ctype_digit($raw_id)) {
			wp_send_json_error(['message' => 'Invalid ID parameter'], 400);
		}

		$id = (int) $raw_id;
		if ($id > 0) {
			$filters[] = $wpdb->prepare('ID = %d', $id);
		}

		$title = $this->get_param( 'title' );
		if ( ! empty( $title ) ) {
			$safe_title = $wpdb->esc_like( sanitize_text_field( $title ) );
			$filters[]  = $wpdb->prepare( 'post_title LIKE %s', '%' . $safe_title . '%' );
		}

		$articles = $this->get_search_results_provider()->get_standard_article_search_results( $filters );

		return array(
			'settings' => array(
				'type'                => 'content',
				'link_editable'       => false,
				'link_text_editable'  => true,
				'image_size_editable' => true,
			),
			'items'    => $this->format_articles( $articles ),
		);
	}

	/**
	 * Retrieves products by their IDs and prepares them in appropriate format for the response.
	 *
	 * @param array $articles Array of articles.
	 *
	 * @return array
	 */
	private function format_articles( $articles ) {
		$results = array();
		foreach ( $articles as $article ) {
			$image     = get_the_post_thumbnail_url( $article['ID'] );
			$results[] = array(
				'title'       => $article['post_title'],
				'description' => wp_strip_all_tags( $article['post_content'] ),
				'content'     => '<!--#html #-->' . $article['post_content'] . '<!--#/html#-->',
				'image'       => false !== $image ? $image : '',
				'url'         => $article['guid'],
			);
		}

		return $results;
	}

	/**
	 * Terminates request with 401 if the password parameter does not match the stored product search password.
	 */
	private function validate_password() {
		/**
		 * Configuration service
		 *
		 * @var Config_Service $config_service
		 */
		$config_service    = ServiceRegister::getService( Configuration::CLASS_NAME );
		$expected_password = (string) $config_service->getProductSearchEndpointPassword();
		$password          = (string) $this->get_param( 'password' );

		if ( '' === $expected_password || ! $this->is_equal_in_constant_time( $expected_password, $password ) ) {
			$this->die_with_status( 401 );
		}
	}

	/**
	 * Compares two strings in constant time to prevent timing attacks.
	 * Replacement for hash_equals(), which is not available before PHP 5.6.
	 *
	 * @param string $known_string String of known length to compare against.
	 * @param string $user_string User-supplied string.
	 *
	 * @return bool
	 */
	private function is_equal_in_constant_time( $known_string, $user_string ) {
		$known_length = strlen( $known_string );
		if ( strlen( $user_string ) !== $known_length ) {
			return false;
		}

		$result = 0;
		for ( $i = 0; $i < $known_length; $i++ ) {
			$result |= ord( $known_string[ $i ] ) ^ ord( $user_string[ $i ] );
		}

		return 0 === $result;
	}

	/**
	 * Gets schema provider
	 *
	 * @return Schema_Provider
	 */
	private function get_schema_provider() {
		if ( null === $this->schema_provider ) {
			$this->schema_provider = new Schema_Provider();
		}

		return $this->schema_provider;
	}

	/**
	 * Gets search result provider
	 *
	 * @return Search_Results_Provider
	 */
	private function get_search_results_provider() {
		if ( null === $this->search_results_provider ) {
			$this->search_results_provider = new Search_Results_Provider();
		}

		return $this->search_results_provider;
	}
}

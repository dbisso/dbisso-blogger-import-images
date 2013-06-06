<?php
namespace DBisso\Plugin\BloggerImportImages;

use Phly\Mustache\Mustache;
use Phly\Mustache\Pragma\ImplicitIterator;
/**
 * Class DBisso\Plugin\BloggerImportImages\Plugin
 */
class Plugin {
	static $_hooker;
	static $mustache;
	static $import_limit = 5;

	function bootstrap( $hooker = null ) {
		if ( $hooker ) {
			if ( !method_exists( $hooker, 'hook' ) )
				throw new \BadMethodCallException( 'Class ' . get_class( $hooker ) . ' has no hook() method.', 1 );

			self::$_hooker = $hooker;
			self::$_hooker->hook( __CLASS__, 'BloggerImportImages' );
		} else {
			throw new \BadMethodCallException( 'Hooking class for ' . __CLASS__ . ' not specified.' , 1 );
		}

		register_activation_hook( __FILE__, array( __CLASS__, 'activation' ) );

		self::$mustache = new Mustache();
		self::$mustache->setTemplatePath( __DIR__ . '/templates/' );
		self::$mustache->getRenderer()->addPragma( new ImplicitIterator );
	}

	function activation() {}

	public function action_admin_menu() {
		add_management_page( 'Import Blogger Images', 'Import Blogger Images', 'administrator', 'dbisso-blogger-import-images', array( __CLASS__, 'admin_page_import_images' ) );
	}

	public function admin_page_import_images() {
		// Do import
		if ( wp_verify_nonce( $_GET['_wpnonce'], 'dbisso-blogger-import-images' ) ) {
			if ( isset( $_POST['blogger_import_image'] ) ) {
				self::import_images( (int) $_POST['blogger_import_image']['import'] );
			}
		}

		$posts = self::get_posts();
		$post_images = array();
		$remote_urls = array();

		foreach ( $posts as $key => $post ) {
			$content = get_post_field( 'post_content', $post, 'raw' );
			$dom     = self::get_content_dom( $content );
			$nodes   = self::get_image_link_nodes( $dom );

			if ( $nodes ) {
				$images = array();

				foreach ( $nodes as $node ) {
					$images[] = $node->getAttribute( 'href' );
				}

				if ( count( $images ) > 0 ) {
					$post_images[] = array(
						'title' => get_the_title( $post->ID ),
						'post_id' => esc_attr( $post->ID ),
						'permalink' => esc_attr( get_permalink( $post->ID ) ),
						'images' => $images,
					);
				}
			}
		}

		(object) $data = array(
			'page_title' => 'Import Blogger Images',
			'nonce' => wp_create_nonce( 'dbisso-blogger-import-images' ),
			'posts' => $post_images,
			'action' => esc_attr( ( wp_nonce_url( $_SERVER['REQUEST_URI'], 'dbisso-blogger-import-images' ) ) ),
			'import_limit' => self::$import_limit,
		);

		echo wp_kses_post( self::$mustache->render( 'admin-import-images', $data ) );
	}

	private function import_images( $post_id ) {
		$message = self::$mustache->render( 'admin-images-imported-message', array( 'permalink' => get_permalink( $post_id ), 'title' => get_the_title( $post_id ) ) );

		$posts = self::process_post_images(
			array( get_post( $post_id ) ),
			array( __CLASS__, 'replace_post_image_node' ),
			array( __CLASS__, 'update_post_content' )
		);

		echo wp_kses_post(
			self::$mustache->render(
				'admin-alert',
				array( 'message' => $message )
			)
		);
	}

	private function replace_post_image_node( $node, $post ) {
		// Required for sideloading
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$url = $node->getAttribute( 'href' );
		$dom = $node->ownerDocument;
		$attachment_id = self::sideload( $url, $post->ID );

		$new_node = $dom->createDocumentFragment();
		$new_node->appendXML( wp_get_attachment_link( $attachment_id, 'medium', true ) );

		$node->parentNode->replaceChild( $new_node, $node );
	}

	private function update_post_content( $dom, $post ) {
		$html = '';
		$body  = $dom->getElementsByTagName( 'body' )->item( 0 );

		foreach ( $body->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}

		$post->post_content = $html;

		wp_update_post( (array) $post );
	}

	private function process_post_images( $posts, $element_callback, $document_callback = false ) {
		foreach ( $posts as $key => $post ) {
			$content = get_post_field( 'post_content', $post, 'raw' );
			$dom     = self::get_content_dom( $content );
			$nodes   = self::get_image_link_nodes( $dom );

			if ( $nodes ) {
				$images = array();
				$count = 1;
				foreach ( $nodes as $node ) {
					if ( $count++ > apply_filters( 'dbisso_blogger_import_images_limit', self::$import_limit ) ) break;
					$element_callback( $node, $post );
				}
			}

			if ( is_callable( $document_callback ) )
				$document_callback( $dom, $post );
		}

		return $posts;
	}

	private function get_content_dom( $content ) {
		if ( empty( $content ) ) return false;

		libxml_use_internal_errors( true ); // Hide HTML DOM errors
		$dom = new \DomDocument( '1.0', 'utf-8' );

		// Convert encoding to preserve entities
		$content = mb_convert_encoding( (string) $content, 'HTML-ENTITIES', 'UTF-8' );

		$dom->loadHTML( $content );

		return $dom;
	}

	private function get_image_link_nodes( $dom ) {
		if ( $dom ) {
			$body  = $dom->getElementsByTagName( 'body' )->item( 0 );
			$xp    = new \DOMXPath( $dom );
			$nodes = $xp->query( "//a[contains(@href,'blogspot.com')]" );

			return $nodes;
		}

		return false;
	}

	private function get_posts() {
		$posts = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type' => 'post',
				'post_status' => 'publish',
			)
		);

		return $posts;
	}

	private function sideload( $file, $post_id, $desc = null ) {
		if ( ! empty( $file ) ) {
			// Download file to temp location
			$tmp = download_url( $file );

			// Set variables for storage
			// fix file filename for query strings
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
			$file_array['name'] = basename( $matches[0] );
			$file_array['tmp_name'] = $tmp;

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				@unlink( $file_array['tmp_name'] );
				$file_array['tmp_name'] = '';
			}

			// do the validation and storage stuff
			$id = media_handle_sideload( $file_array, $post_id, $desc );

			// If error storing permanently, unlink
			if ( is_wp_error( $id ) ) {
				@unlink( $file_array['tmp_name'] );
				return $id;
			}

			return $id;
		}
	}
}
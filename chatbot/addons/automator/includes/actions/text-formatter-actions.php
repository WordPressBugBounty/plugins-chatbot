<?php
/**
 * Text Formatter Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Text_Formatter_Actions
 */
class Text_Formatter_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'text_formatter';
		$this->group = __( 'Text Formatter', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'extract_pattern':
				return $this->extract_pattern( $action_data, $trigger_data );
			case 'find_text':
				return $this->find_text( $action_data, $trigger_data );
			case 'replace_text':
				return $this->replace_text( $action_data, $trigger_data );
			case 'split_text':
				return $this->split_text( $action_data, $trigger_data );
			case 'default_value':
				return $this->default_value( $action_data, $trigger_data );
			case 'truncate':
				return $this->truncate( $action_data, $trigger_data );
			case 'url_encode':
				return $this->url_encode( $action_data, $trigger_data );
			case 'url_decode':
				return $this->url_decode( $action_data, $trigger_data );
			case 'join_lines':
				return $this->join_lines( $action_data, $trigger_data );
			case 'html_to_markdown':
				return $this->html_to_markdown( $action_data, $trigger_data );
			case 'markdown_to_html':
				return $this->markdown_to_html( $action_data, $trigger_data );
			case 'generate_slug':
				return $this->generate_slug( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Extract pattern using Regex
	 */
	private function extract_pattern( $config, $trigger_data ) {
		$values  = isset( $config['config'] ) ? $config['config'] : array();
		$text    = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$pattern = isset( $values['pattern'] ) ? $this->parse_tokens( $values['pattern'], $trigger_data ) : '';

		if ( empty( $pattern ) ) {
			return array( 'success' => false, 'message' => 'Pattern is required.' );
		}

		if ( @preg_match( $pattern, $text, $matches ) ) {
			return array(
				'success' => true,
				'result'  => $matches[0],
				'matches' => $matches,
				'message' => 'Pattern extracted successfully.'
			);
		}

		return array( 'success' => false, 'result' => '', 'message' => 'No match found.' );
	}

	/**
	 * Find text in content
	 */
	private function find_text( $config, $trigger_data ) {
		$values = isset( $config['config'] ) ? $config['config'] : array();
		$text   = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$search = isset( $values['search'] ) ? $this->parse_tokens( $values['search'], $trigger_data ) : '';

		if ( empty( $search ) ) {
			return array( 'success' => false, 'message' => 'Search term is required.' );
		}

		$found = ( strpos( $text, $search ) !== false );

		return array(
			'success' => true,
			'found'   => $found,
			'message' => $found ? 'Text found.' : 'Text not found.'
		);
	}

	/**
	 * Replace text
	 */
	private function replace_text( $config, $trigger_data ) {
		$values  = isset( $config['config'] ) ? $config['config'] : array();
		$text    = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$search  = isset( $values['search'] ) ? $this->parse_tokens( $values['search'], $trigger_data ) : '';
		$replace = isset( $values['replace'] ) ? $this->parse_tokens( $values['replace'], $trigger_data ) : '';

		$result = str_replace( $search, $replace, $text );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Text replaced successfully.'
		);
	}

	/**
	 * Split text
	 */
	private function split_text( $config, $trigger_data ) {
		$values    = isset( $config['config'] ) ? $config['config'] : array();
		$text      = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$delimiter = isset( $values['delimiter'] ) ? $this->parse_tokens( $values['delimiter'], $trigger_data ) : '';
		$index     = isset( $values['segment_index'] ) ? (int) $values['segment_index'] : 0;

		if ( empty( $delimiter ) ) {
			return array( 'success' => false, 'message' => 'Delimiter is required.' );
		}

		$segments = explode( $delimiter, $text );
		
		if ( $index < 0 ) {
			$index = count( $segments ) + $index;
		}

		$result = isset( $segments[ $index ] ) ? $segments[ $index ] : '';

		return array(
			'success'  => true,
			'result'   => $result,
			'segments' => $segments,
			'message'  => 'Text split successfully.'
		);
	}

	/**
	 * Default value if empty
	 */
	private function default_value( $config, $trigger_data ) {
		$values  = isset( $config['config'] ) ? $config['config'] : array();
		$text    = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$default = isset( $values['default'] ) ? $this->parse_tokens( $values['default'], $trigger_data ) : '';

		$result = empty( trim( $text ) ) ? $default : $text;

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Value processed.'
		);
	}

	/**
	 * Truncate text
	 */
	private function truncate( $config, $trigger_data ) {
		$values = isset( $config['config'] ) ? $config['config'] : array();
		$text   = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$length = isset( $values['length'] ) ? (int) $values['length'] : 100;
		$suffix = isset( $values['suffix'] ) ? $this->parse_tokens( $values['suffix'], $trigger_data ) : '';

		if ( mb_strlen( $text ) <= $length ) {
			$result = $text;
		} else {
			$result = mb_substr( $text, 0, $length ) . $suffix;
		}

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Text truncated successfully.'
		);
	}

	/**
	 * URL Encode
	 */
	private function url_encode( $config, $trigger_data ) {
		$values = isset( $config['config'] ) ? $config['config'] : array();
		$text   = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';

		$result = urlencode( $text );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Text encoded successfully.'
		);
	}

	/**
	 * URL Decode
	 */
	private function url_decode( $config, $trigger_data ) {
		$values = isset( $config['config'] ) ? $config['config'] : array();
		$text   = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';

		$result = urldecode( $text );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Text decoded successfully.'
		);
	}

	/**
	 * Join lines
	 */
	private function join_lines( $config, $trigger_data ) {
		$values    = isset( $config['config'] ) ? $config['config'] : array();
		$text      = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$separator = isset( $values['separator'] ) ? $this->parse_tokens( $values['separator'], $trigger_data ) : ', ';

		$result = preg_replace( '/\r?\n|\r/', $separator, $text );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Lines joined successfully.'
		);
	}

	/**
	 * HTML to Markdown (Basic implementation)
	 */
	private function html_to_markdown( $config, $trigger_data ) {
		$values     = isset( $config['config'] ) ? $config['config'] : array();
		$text       = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';
		$save_media = isset( $values['save_media'] ) ? (bool) $values['save_media'] : false;

		if ( $save_media ) {
			$text = $this->handle_media_extraction( $text );
		}

		// Simple regex-based HTML to Markdown
		$replacements = array(
			'/<h[1-6]>(.*?)<\/h[1-6]>/is' => "# $1\n",
			'/<b>(.*?)<\/b>/is'           => "**$1**",
			'/<strong>(.*?)<\/strong>/is' => "**$1**",
			'/<i>(.*?)<\/i>/is'           => "*$1*",
			'/<em>(.*?)<\/em>/is'         => "*$1*",
			'/<a.*?href="(.*?)".*?>(.*?)<\/a>/is' => "[$2]($1)",
			'/<img.*?src="(.*?)".*?alt="(.*?)".*?>/is' => "![$2]($1)",
			'/<img.*?src="(.*?)".*?>/is' => "![]($1)",
			'/<p>(.*?)<\/p>/is'           => "$1\n\n",
			'/<br\s*\/?>/is'              => "\n",
			'/<li>(.*?)<\/li>/is'         => "- $1\n",
			'/<ul>(.*?)<\/ul>/is'         => "$1\n",
			'/<ol>(.*?)<\/ol>/is'         => "$1\n",
		);

		$result = preg_replace( array_keys( $replacements ), array_values( $replacements ), $text );
		$result = wp_strip_all_tags( $result );
		$result = trim( html_entity_decode( $result ) );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'HTML converted to Markdown.'
		);
	}

	/**
	 * Markdown to HTML (Basic implementation)
	 */
	private function markdown_to_html( $config, $trigger_data ) {
		$values = isset( $config['config'] ) ? $config['config'] : array();
		$text   = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';

		// Simple regex-based Markdown to HTML
		$replacements = array(
			'/^# (.*?)$/m'      => '<h1>$1</h1>',
			'/\*\*(.*?)\*\*/'   => '<strong>$1</strong>',
			'/\*(.*?)\*/'       => '<em>$1</em>',
			'/\[(.*?)\]\((.*?)\)/' => '<a href="$2">$1</a>',
			'/!\[(.*?)\]\((.*?)\)/' => '<img src="$2" alt="$1">',
			'/^- (.*?)$/m'      => '<li>$1</li>',
			'/\n\n/'            => '</p><p>',
		);

		$result = preg_replace( array_keys( $replacements ), array_values( $replacements ), $text );
		$result = '<p>' . str_replace( "\n", "<br>", $result ) . '</p>';
		
		// Clean up list items
		$result = preg_replace( '/(<li>.*?<\/li>)+/s', '<ul>$0</ul>', $result );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Markdown converted to HTML.'
		);
	}

	/**
	 * Generate Slug
	 */
	private function generate_slug( $config, $trigger_data ) {
		$values = isset( $config['config'] ) ? $config['config'] : array();
		$text   = isset( $values['text'] ) ? $this->parse_tokens( $values['text'], $trigger_data ) : '';

		$result = sanitize_title( $text );

		return array(
			'success' => true,
			'result'  => $result,
			'message' => 'Slug generated successfully.'
		);
	}

	/**
	 * Handle media extraction and save to media library
	 */
	private function handle_media_extraction( $html ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		preg_match_all( '/<img.*?src="(.*?)".*?>/i', $html, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $image_url ) {
				// Avoid re-downloading local images
				if ( strpos( $image_url, get_site_url() ) !== false ) {
					continue;
				}

				$new_url = media_sideload_image( $image_url, 0, null, 'src' );
				if ( ! is_wp_error( $new_url ) ) {
					$html = str_replace( $image_url, $new_url, $html );
				}
			}
		}

		return $html;
	}
}

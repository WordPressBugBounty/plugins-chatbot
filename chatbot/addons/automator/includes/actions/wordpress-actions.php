<?php
/**
 * WordPress Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WordPress_Actions
 */
class WordPress_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'wordpress';
		$this->group = __( 'WordPress', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 * 
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'wp_create_user':
			case 'wp_user_register':
				return $this->create_user( $action_data, $trigger_data );
			case 'wp_update_user':
				return $this->update_user( $action_data, $trigger_data );
			case 'wp_delete_user':
				return $this->delete_user( $action_data, $trigger_data );
			case 'wp_create_post':
			case 'wp_create_page':
				return $this->create_post( $action_data, $trigger_data );
			case 'wp_update_post':
				return $this->update_post( $action_data, $trigger_data );
			case 'wp_create_comment':
				return $this->create_comment( $action_data, $trigger_data );
			case 'wp_reply_comment':
				return $this->reply_comment( $action_data, $trigger_data );
			case 'wp_ai_generated_post':
				return $this->ai_generated_post( $action_data, $trigger_data );
			case 'wp_ai_reply_comment':
				return $this->ai_reply_comment( $action_data, $trigger_data );
			case 'wp_ai_generate_social_posts':
			case 'wp_ai_generate_social_posts_pro':
				return $this->ai_generate_social_posts( $action_data, $trigger_data );
			// Add more cases here as needed...
			default:
				// Fallback for actions not yet fully implemented
				return array( 'success' => true, 'message' => "Action {$action_id} executed (simulated)" );
		}
	}

	/**
	 * Reply to a comment
	 */
	private function reply_comment( $config, $trigger_data ) {
		$config_values    = isset( $config['config'] ) ? $config['config'] : array();
		$parent_id        = isset( $config_values['parent_id'] ) ? $this->parse_tokens( $config_values['parent_id'], $trigger_data ) : 0;
		$comment_content = isset( $config_values['comment_content'] ) ? $this->parse_tokens( $config_values['comment_content'], $trigger_data ) : '';

		// Fallback for parent_id: use comment_id from trigger if available
		if ( empty( $parent_id ) && isset( $trigger_data['comment_id'] ) ) {
			$parent_id = is_scalar( $trigger_data['comment_id'] ) ? $trigger_data['comment_id'] : 0;
			//error_log( "WPbot Automator - reply_comment: Used fallback parent_id: " . $parent_id );
		}

		// Fallback for content: use 'response' or 'content' from trigger (AI output)
		if ( empty( $comment_content ) ) {
			if ( isset( $trigger_data['response'] ) ) {
				$comment_content = is_scalar( $trigger_data['response'] ) ? (string) $trigger_data['response'] : wp_json_encode( $trigger_data['response'] );
				//error_log( "WPbot Automator - reply_comment: Used fallback content from {response}" );
			} elseif ( isset( $trigger_data['content'] ) ) {
				$comment_content = is_scalar( $trigger_data['content'] ) ? (string) $trigger_data['content'] : wp_json_encode( $trigger_data['content'] );
				//error_log( "WPbot Automator - reply_comment: Used fallback content from {content}" );
			}
		}

		if ( empty( $parent_id ) ) {
			return array( 'success' => false, 'message' => 'Parent Comment ID is required.' );
		}

		if ( empty( $comment_content ) ) {
			return array( 'success' => false, 'message' => 'Reply Content is required.' );
		}

		$parent_comment = get_comment( $parent_id );
		if ( ! $parent_comment ) {
			return array(
				'success' => false,
				'message' => 'Parent comment not found for ID: ' . $parent_id,
			);
		}

		$comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $parent_comment->comment_post_ID,
			'comment_parent'       => absint( $parent_id ),
			'comment_content'      => $comment_content,
			'comment_type'         => 'comment',
			'comment_approved'     => 1,
		) );

		if ( ! $comment_id ) {
			return array(
				'success' => false,
				'message' => 'Failed to create reply.',
			);
		}

		return array(
			'success'    => true,
			'comment_id' => $comment_id,
			'message'    => 'Reply created successfully.',
		);
	}

	/**
	 * Create a new user
	 */
	private function create_user( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		$user_login    = isset( $config_values['user_login'] ) ? $this->parse_tokens( $config_values['user_login'], $trigger_data ) : '';
		$user_email    = isset( $config_values['user_email'] ) ? $this->parse_tokens( $config_values['user_email'], $trigger_data ) : '';
		
		// Smart Mapping Fallback: If not provided, try to find in trigger_data.
		if ( empty( $user_email ) ) {
			$user_email = $this->find_email_in_data( $trigger_data );
		}
		
		if ( empty( $user_login ) ) {
			// Try to find a name or use first part of email.
			$name = $this->find_name_in_data( $trigger_data );
			$user_login = ! empty( $name ) ? sanitize_user( strtolower( str_replace( ' ', '.', $name ) ) ) : ( ! empty( $user_email ) ? explode( '@', $user_email )[0] : '' );
			
			// Safety: WordPress usernames have a 60 character limit.
			if ( strlen( $user_login ) > 60 ) {
				$user_login = substr( $user_login, 0, 60 );
			}
		}

		$user_pass  = isset( $config_values['user_pass'] ) ? $this->parse_tokens( $config_values['user_pass'], $trigger_data ) : wp_generate_password();
		$role       = isset( $config_values['role'] ) ? $config_values['role'] : 'subscriber';

		if ( empty( $user_login ) || empty( $user_email ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'User login and email are required. Found Login: "%s", Email: "%s"', $user_login, $user_email ),
			);
		}

		if ( username_exists( $user_login ) ) {
			return array(
				'success' => false,
				'message' => "Username already exists: $user_login",
			);
		}

		if ( email_exists( $user_email ) ) {
			return array(
				'success' => false,
				'message' => "Email already exists: $user_email",
			);
		}

		$user_id = wp_insert_user( array(
			'user_login' => $user_login,
			'user_email' => $user_email,
			'user_pass'  => $user_pass,
			'role'       => $role,
		) );

		if ( is_wp_error( $user_id ) ) {
			return array(
				'success' => false,
				'message' => $user_id->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'user_id' => $user_id,
			'message' => 'User created successfully.',
		);
	}

	/**
	 * Helper: Find email in trigger data
	 */
	private function find_email_in_data( $data ) {
		if ( ! is_array( $data ) ) return '';
		
		// Flatten and look for keys containing 'email'.
		$flattened = $this->flatten_array_recursive( $data );
		foreach ( $flattened as $key => $val ) {
			if ( is_string( $val ) && is_email( $val ) ) {
				return $val;
			}
		}
		return '';
	}

	/**
	 * Helper: Find name in trigger data
	 */
	private function find_name_in_data( $data ) {
		if ( ! is_array( $data ) ) return '';
		
		$flattened = $this->flatten_array_recursive( $data );
		
		$firstName = '';
		$lastName  = '';
		$fullName  = '';

		foreach ( $flattened as $key => $val ) {
			if ( ! is_string( $val ) || empty( $val ) ) continue;
			$low_key = strtolower( $key );
			
			// Priority 1: Explicit sub-fields (common in WPForms, etc).
			if ( strpos( $low_key, '.first' ) !== false || strpos( $low_key, 'first_name' ) !== false ) {
				$firstName = $val;
			}
			if ( strpos( $low_key, '.last' ) !== false || strpos( $low_key, 'last_name' ) !== false ) {
				$lastName = $val;
			}
			
			// Priority 2: Generic name field.
			if ( $low_key === 'fullname' || $low_key === 'full_name' || ( strpos( $low_key, 'name' ) !== false && strpos( $low_key, '.value' ) !== false ) ) {
				$fullName = $val;
			}
		}

		if ( ! empty( $firstName ) || ! empty( $lastName ) ) {
			return trim( $firstName . ' ' . $lastName );
		}
		
		if ( ! empty( $fullName ) ) {
			return $fullName;
		}

		// Fallback: Just the FIRST thing that looks like a name.
		foreach ( $flattened as $key => $val ) {
			$low_key = strtolower( $key );
			if ( strpos( $low_key, 'name' ) !== false ) {
				// Avoid labels.
				if ( is_string( $val ) && strlen( $val ) > 2 && strlen( $val ) < 50 ) {
					$low_val = strtolower( trim( $val ) );
					if ( ! in_array( $low_val, array( 'name', 'first name', 'last name', 'full name', 'email', 'message', 'subject' ) ) ) {
						return $val;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Helper to flatten array recursively
	 */
	private function flatten_array_recursive( $array, $prefix = '' ) {
		$result = array();
		foreach ( $array as $key => $value ) {
			$new_key = $prefix ? $prefix . '.' . $key : $key;
			if ( is_array( $value ) ) {
				$result = array_merge( $result, $this->flatten_array_recursive( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Update a user
	 */
	private function update_user( $config, $trigger_data ) {
		// Implementation logic...
		return array( 'success' => true );
	}

	/**
	 * Delete a user
	 */
	private function delete_user( $config, $trigger_data ) {
		// Implementation logic...
		return array( 'success' => true );
	}

	/**
	 * Create a new post
	 */
	private function create_post( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		$action_id     = isset( $config['actionId'] ) ? $config['actionId'] : 'wp_create_post';
		
		$post_title   = isset( $config_values['post_title'] ) ? $this->parse_tokens( $config_values['post_title'], $trigger_data ) : '';
		$post_content = isset( $config_values['post_content'] ) ? $this->parse_tokens( $config_values['post_content'], $trigger_data ) : '';
		$post_type    = isset( $config_values['post_type'] ) ? $config_values['post_type'] : ( $action_id === 'wp_create_page' ? 'page' : 'post' );
		$post_status  = isset( $config_values['post_status'] ) ? $config_values['post_status'] : 'publish';
		$categories   = isset( $config_values['categories'] ) ? $this->parse_tokens( $config_values['categories'], $trigger_data ) : '';

		if ( empty( $post_title ) || empty( $post_content ) ) {
			return array(
				'success' => false,
				'message' => 'Post Title and Content are required.',
			);
		}

		$post_data = array(
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => $post_status,
			'post_type'    => $post_type,
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'message' => $post_id->get_error_message(),
			);
		}

		// Handle categories/topics if provided.
		if ( ! empty( $categories ) ) {
			$cat_ids = array_map( 'trim', explode( ',', $categories ) );
			wp_set_object_terms( $post_id, $cat_ids, 'category' );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'message' => sprintf( '%s created successfully.', ucfirst( $post_type ) ),
		);
	}

	/**
	 * Update a post
	 */
	private function update_post( $config, $trigger_data ) {
		// Implementation logic...
		return array( 'success' => true );
	}

	/**
	 * Create a new comment
	 */
	private function create_comment( $config, $trigger_data ) {
		$config_values   = isset( $config['config'] ) ? $config['config'] : array();
		$post_id         = isset( $config_values['post_id'] ) ? $this->parse_tokens( $config_values['post_id'], $trigger_data ) : 0;
		$comment_content = isset( $config_values['comment_content'] ) ? $this->parse_tokens( $config_values['comment_content'], $trigger_data ) : '';

		if ( empty( $post_id ) ) {
			// Try to find a post ID in trigger data if not provided
			if ( isset( $trigger_data['post_id'] ) ) {
				$post_id = is_scalar( $trigger_data['post_id'] ) ? $trigger_data['post_id'] : 0;
			} elseif ( isset( $trigger_data['id'] ) && ( isset( $trigger_data['post_type'] ) || isset( $trigger_data['post_title'] ) ) ) {
				$post_id = is_scalar( $trigger_data['id'] ) ? $trigger_data['id'] : 0;
			}
		}

		// Fallback for content: use 'response' or 'content' from trigger (AI output)
		if ( empty( $comment_content ) ) {
			if ( isset( $trigger_data['response'] ) ) {
				$comment_content = is_scalar( $trigger_data['response'] ) ? (string) $trigger_data['response'] : wp_json_encode( $trigger_data['response'] );
				//error_log( "WPbot Automator - create_comment: Used fallback content from {response}" );
			} elseif ( isset( $trigger_data['content'] ) ) {
				$comment_content = is_scalar( $trigger_data['content'] ) ? (string) $trigger_data['content'] : wp_json_encode( $trigger_data['content'] );
				//error_log( "WPbot Automator - create_comment: Used fallback content from {content}" );
			}
		}

		if ( empty( $post_id ) ) {
			return array( 'success' => false, 'message' => 'Post ID is required.' );
		}

		if ( empty( $comment_content ) ) {
			return array( 'success' => false, 'message' => 'Comment Content is required.' );
		}

		$comment_id = wp_insert_comment( array(
			'comment_post_ID'      => absint( $post_id ),
			'comment_content'      => $comment_content,
			'comment_type'         => 'comment',
			'comment_approved'     => 1, // Auto-approve for now, or make configurable
		) );

		if ( ! $comment_id ) {
			return array(
				'success' => false,
				'message' => 'Failed to create comment.',
			);
		}

		return array(
			'success'    => true,
			'comment_id' => $comment_id,
			'message'    => 'Comment created successfully.',
		);
	}

	/**
	 * AI Generated Post
	 */
	private function ai_generated_post( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		$instructions  = isset( $config_values['prompt_instructions'] ) ? $this->parse_tokens( $config_values['prompt_instructions'], $trigger_data ) : '';
		$post_title    = isset( $config_values['post_title'] ) ? $this->parse_tokens( $config_values['post_title'], $trigger_data ) : '';

		if ( empty( $instructions ) ) {
			return array( 'success' => false, 'message' => 'Instructions for AI are required.' );
		}

		// If title is empty, ask AI to generate one.
		if ( empty( $post_title ) ) {
			$prompt = "Generate a catchy title and a comprehensive blog post based on these instructions: {$instructions}. Return it in this format: [TITLE]Your Title[/TITLE][CONTENT]Your Content[/CONTENT]";
		} else {
			$prompt = "Generate a comprehensive blog post based on these instructions: {$instructions}. The title is already set to: {$post_title}";
		}

		$ai_response = $this->call_ai( $config_values, $trigger_data, $prompt, "You are a professional WordPress content creator." );

		if ( ! $ai_response['success'] ) {
			return $ai_response;
		}

		$content = $ai_response['content'];

		if ( empty( $post_title ) ) {
			if ( preg_match( '/\[TITLE\](.*?)\[\/TITLE\]/s', $content, $matches ) ) {
				$post_title = trim( $matches[1] );
			}
			if ( preg_match( '/\[CONTENT\](.*?)\[\/CONTENT\]/s', $content, $matches ) ) {
				$content = trim( $matches[1] );
			}
		}

		// Re-use create_post logic.
		$config['config']['post_title']   = $post_title;
		$config['config']['post_content'] = $content;

		return $this->create_post( $config, $trigger_data );
	}

	/**
	 * AI Comment Reply
	 */
	private function ai_reply_comment( $config, $trigger_data ) {
		$config_values    = isset( $config['config'] ) ? $config['config'] : array();
		$parent_id        = isset( $config_values['parent_id'] ) ? $this->parse_tokens( $config_values['parent_id'], $trigger_data ) : 0;
		$instructions     = isset( $config_values['prompt_instructions'] ) ? $this->parse_tokens( $config_values['prompt_instructions'], $trigger_data ) : '';

		if ( empty( $parent_id ) && isset( $trigger_data['comment_id'] ) ) {
			$parent_id = $trigger_data['comment_id'];
		}

		if ( empty( $parent_id ) ) {
			return array( 'success' => false, 'message' => 'Parent Comment ID is required.' );
		}

		$parent_comment = get_comment( $parent_id );
		if ( ! $parent_comment ) {
			return array( 'success' => false, 'message' => 'Parent comment not found.' );
		}

		$parent_content = $parent_comment->comment_content;
		$prompt = "A user commented: \"{$parent_content}\". Generate a reply based on these instructions: {$instructions}";

		$ai_response = $this->call_ai( $config_values, $trigger_data, $prompt, "You are a helpful and polite WordPress site administrator." );

		if ( ! $ai_response['success'] ) {
			return $ai_response;
		}

		// Re-use reply_comment logic.
		$config['config']['parent_id']       = $parent_id;
		$config['config']['comment_content'] = $ai_response['content'];

		return $this->reply_comment( $config, $trigger_data );
	}

	/**
	 * AI Generate Social Media Posts
	 */
	private function ai_generate_social_posts( $config, $trigger_data ) {
		$config_values    = isset( $config['config'] ) ? $config['config'] : array();
		$action_id        = isset( $config['actionId'] ) ? $config['actionId'] : 'wp_ai_generate_social_posts';
		$source_content   = isset( $config_values['source_content'] ) ? $this->parse_tokens( $config_values['source_content'], $trigger_data ) : '';
		$instructions     = isset( $config_values['additional_instructions'] ) ? $this->parse_tokens( $config_values['additional_instructions'], $trigger_data ) : '';

		if ( empty( $source_content ) ) {
			// Fallback: try to find post content in trigger data.
			if ( isset( $trigger_data['post_content'] ) ) {
				$source_content = $trigger_data['post_content'];
			} else {
				return array( 'success' => false, 'message' => 'Source Content is required.' );
			}
		}

		$is_pro = ( $action_id === 'wp_ai_generate_social_posts_pro' );

		if ( $is_pro ) {
			$prompt = "You are a social media expert. Based on the following content, generate three optimized posts for Instagram, WhatsApp, and LinkedIn. 
		Additional Instructions: {$instructions}

		Content:
		\"{$source_content}\"

		IMPORTANT: You MUST return the output in EXACTLY this format:
		[INSTAGRAM]
		(Your Instagram post content here)
		[/INSTAGRAM]
		[WHATSAPP]
		(Your WhatsApp post content here)
		[/WHATSAPP]
		[LINKEDIN]
		(Your LinkedIn post content here)
		[/LINKEDIN]";
		} else {
			$prompt = "You are a social media expert. Based on the following content, generate two optimized posts for Facebook and Telegram. 
		Additional Instructions: {$instructions}

		Content:
		\"{$source_content}\"

		IMPORTANT: You MUST return the output in EXACTLY this format:
		[FACEBOOK]
		(Your Facebook post content here)
		[/FACEBOOK]
		[TELEGRAM]
		(Your Telegram post content here)
		[/TELEGRAM]";
		}

		$ai_response = $this->call_ai( $config_values, $trigger_data, $prompt, "You are a helpful social media manager." );

		if ( ! $ai_response['success'] ) {
			return $ai_response;
		}

		$content = $ai_response['content'];
		$results = array(
			'success' => true,
			'fb_post' => '',
			'telegram_post' => '',
			'insta_post' => '',
			'whatsapp_post' => '',
			'linkedin_post' => '',
			'content' => $content, // Store full response just in case
		);

		if ( preg_match( '/\[FACEBOOK\](.*?)\[\/FACEBOOK\]/s', $content, $matches ) ) {
			$results['fb_post'] = trim( $matches[1] );
		}
		if ( preg_match( '/\[TELEGRAM\](.*?)\[\/TELEGRAM\]/s', $content, $matches ) ) {
			$results['telegram_post'] = trim( $matches[1] );
		}
		if ( preg_match( '/\[INSTAGRAM\](.*?)\[\/INSTAGRAM\]/s', $content, $matches ) ) {
			$results['insta_post'] = trim( $matches[1] );
		}
		if ( preg_match( '/\[WHATSAPP\](.*?)\[\/WHATSAPP\]/s', $content, $matches ) ) {
			$results['whatsapp_post'] = trim( $matches[1] );
		}
		if ( preg_match( '/\[LINKEDIN\](.*?)\[\/LINKEDIN\]/s', $content, $matches ) ) {
			$results['linkedin_post'] = trim( $matches[1] );
		}

		// AI Image Generation
		if ( ! empty( $config_values['generate_image'] ) && $config_values['generate_image'] !== "false" ) {
			// 1. Generate an image prompt using the text AI
			$image_prompt_request = "Based on the following content, generate a concise, high-quality image generation prompt (max 100 words) that would be suitable for a social media post. Focus on visual details. 
			
			Content:
			\"{$source_content}\"";

			$image_prompt_response = $this->call_ai( $config_values, $trigger_data, $image_prompt_request, "You are a creative visual designer." );

			if ( $image_prompt_response['success'] ) {
				$image_prompt = $image_prompt_response['content'];
				
				// 2. Call OpenAI DALL-E
				$image_result = $this->call_openai_image( $config_values, $trigger_data, $image_prompt );

				if ( $image_result['success'] ) {
					$results['generated_image_url'] = $image_result['url'];
				} else {
					$results['image_error'] = $image_result['message'];
				}
			}
		}

		return $results;
	}

	/**
	 * Call OpenAI Image (DALL-E) API helper
	 */
	private function call_openai_image( $config_values, $trigger_data, $prompt ) {
		$api_key = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model   = isset( $config_values['image_model'] ) ? $config_values['image_model'] : 'dall-e-3';
		$size    = isset( $config_values['image_size'] ) ? $config_values['image_size'] : '1024x1024';
		$style   = isset( $config_values['image_style'] ) ? $config_values['image_style'] : 'vivid';

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'API Key is required for image generation.' );
		}

		$url = 'https://api.openai.com/v1/images/generations';
		
		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => 1,
			'size'   => $size,
		);

		if ( $model === 'dall-e-3' ) {
			$body['style'] = $style;
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 200 && $response_code < 300 ) {
			return array(
				'success' => true,
				'url'     => isset( $data['data'][0]['url'] ) ? $data['data'][0]['url'] : '',
				'data'    => $data,
			);
		} else {
			$err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown OpenAI Image error';
			return array( 'success' => false, 'message' => $err, 'code' => $response_code );
		}
	}

	/**
	 * Call AI Provider helper
	 */
	private function call_ai( $config_values, $trigger_data, $prompt, $system_prompt = '' ) {
		$provider = isset( $config_values['ai_provider'] ) ? $config_values['ai_provider'] : 'openai';

		switch ( $provider ) {
			case 'gemini':
				return $this->call_gemini( $config_values, $trigger_data, $prompt, $system_prompt );
			case 'claude':
				return $this->call_claude( $config_values, $trigger_data, $prompt, $system_prompt );
			case 'openai':
			default:
				return $this->call_openai( $config_values, $trigger_data, $prompt, $system_prompt );
		}
	}

	/**
	 * Call OpenAI API helper
	 */
	private function call_openai( $config_values, $trigger_data, $prompt, $system_prompt = '' ) {
		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : 'gpt-3.5-turbo';
		$max_tokens  = isset( $config_values['max_tokens'] ) ? intval( $config_values['max_tokens'] ) : 1500;
		$temperature = isset( $config_values['temperature'] ) ? floatval( $config_values['temperature'] ) : 0.7;

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'OpenAI API Key is required.' );
		}

		$messages = array();
		if ( ! empty( $system_prompt ) ) {
			$messages[] = array( 'role' => 'system', 'content' => $system_prompt );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$url = 'https://api.openai.com/v1/chat/completions';
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( array(
				'model'       => $model,
				'messages'    => $messages,
				'max_tokens'  => $max_tokens,
				'temperature' => $temperature,
			) ),
			'timeout' => 60,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 200 && $response_code < 300 ) {
			return array(
				'success' => true,
				'content' => isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '',
				'data'    => $data,
			);
		} else {
			$err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown OpenAI error';
			return array( 'success' => false, 'message' => $err, 'code' => $response_code );
		}
	}

	/**
	 * Call Gemini API helper
	 */
	private function call_gemini( $config_values, $trigger_data, $prompt, $system_prompt = '' ) {
		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : 'gemini-1.5-flash';
		$temperature = isset( $config_values['temperature'] ) ? floatval( $config_values['temperature'] ) : 0.7;

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'Gemini API Key is required.' );
		}

		$full_prompt = $system_prompt ? $system_prompt . "\n\n" . $prompt : $prompt;

		$url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $api_key;

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $full_prompt ),
					),
				),
			),
			'generationConfig' => array(
				'temperature' => $temperature,
			),
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 200 && $response_code < 300 ) {
			$content = isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ? $data['candidates'][0]['content']['parts'][0]['text'] : '';
			return array(
				'success' => true,
				'content' => $content,
				'data'    => $data,
			);
		} else {
			$err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown Gemini error';
			return array( 'success' => false, 'message' => $err, 'code' => $response_code );
		}
	}

	/**
	 * Call Claude API helper
	 */
	private function call_claude( $config_values, $trigger_data, $prompt, $system_prompt = '' ) {
		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : 'claude-3-5-sonnet-20240620';
		$max_tokens  = isset( $config_values['max_tokens'] ) ? intval( $config_values['max_tokens'] ) : 1500;
		$temperature = isset( $config_values['temperature'] ) ? floatval( $config_values['temperature'] ) : 0.7;

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'Claude API Key is required.' );
		}

		$url = 'https://api.anthropic.com/v1/messages';
		
		$body = array(
			'model'      => $model,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
		);

		if ( ! empty( $system_prompt ) ) {
			$body['system'] = $system_prompt;
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 200 && $response_code < 300 ) {
			$content = isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : '';
			return array(
				'success' => true,
				'content' => $content,
				'data'    => $data,
			);
		} else {
			$err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown Claude error';
			return array( 'success' => false, 'message' => $err, 'code' => $response_code );
		}
	}
}

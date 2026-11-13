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
	 * Action IDs with a real implementation. The builder shows only these
	 * (see Registry::get_ui_support()); everything else returns "not available".
	 */
	const SUPPORTED_ACTIONS = array(
		'wp_create_user',
		'wp_create_post',
		'wp_create_page',
		'wp_create_comment',
		'wp_reply_comment',
		'wp_ai_generated_post',
		'wp_ai_reply_comment',
		'wp_ai_generate_social_posts',
		'wp_ai_generate_social_posts_pro',
	);

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
			default:
				return self::not_implemented( $action_id );
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
			'comment_content'      => self::sanitize_comment_content( $comment_content ),
			'comment_type'         => 'comment',
			'comment_approved'     => self::comment_approval( $config_values ),
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
		
		// Fallback: only well-known email keys from the trigger, never the first email found anywhere.
		if ( empty( $user_email ) ) {
			foreach ( array( 'user_email', 'email' ) as $email_key ) {
				if ( isset( $trigger_data[ $email_key ] ) && is_string( $trigger_data[ $email_key ] ) && is_email( $trigger_data[ $email_key ] ) ) {
					$user_email = $trigger_data[ $email_key ];
					break;
				}
			}
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

		$user_pass  = ! empty( $config_values['user_pass'] ) ? $this->parse_tokens( $config_values['user_pass'], $trigger_data ) : wp_generate_password();
		$role       = isset( $config_values['role'] ) ? sanitize_key( $config_values['role'] ) : 'subscriber';

		// Never let an automation create privileged accounts.
		$role_obj = get_role( $role );
		if ( ! $role_obj || $role_obj->has_cap( 'manage_options' ) || $role_obj->has_cap( 'edit_users' ) || $role_obj->has_cap( 'promote_users' ) ) {
			$role = get_option( 'default_role', 'subscriber' );
		}

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

		// Without a password the user chose, send the "set your password" email so they can log in.
		if ( empty( $config_values['user_pass'] ) ) {
			wp_new_user_notification( $user_id, null, 'user' );
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
		return self::not_implemented( 'wp_update_user' );
	}

	/**
	 * Delete a user
	 */
	private function delete_user( $config, $trigger_data ) {
		return self::not_implemented( 'wp_delete_user' );
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
			// Numeric values are term IDs; anything else is a category name/slug.
			$cat_ids = array_map(
				function ( $cat ) {
					$cat = trim( $cat );
					return ctype_digit( $cat ) ? (int) $cat : $cat;
				},
				array_filter( explode( ',', $categories ), 'strlen' )
			);
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
		return self::not_implemented( 'wp_update_post' );
	}

	/**
	 * Result for actions offered in the builder that have no backend yet.
	 * Fails the step so the log shows it instead of a fake success.
	 */
	private static function not_implemented( $action_id ) {
		return array(
			'success' => false,
			'message' => sprintf( 'Action "%s" is not available yet.', $action_id ),
		);
	}

	/**
	 * Sanitize comment content the same way WordPress does for untrusted commenters.
	 */
	private static function sanitize_comment_content( $content ) {
		return wp_kses( (string) $content, 'pre_comment_content' );
	}

	/**
	 * Approval status for comments created by the automation (setting: auto_approve, default on).
	 */
	private static function comment_approval( $config_values ) {
		$auto = isset( $config_values['auto_approve'] ) ? $config_values['auto_approve'] : true;
		return ( false === $auto || 'false' === $auto || '0' === (string) $auto ) ? 0 : 1;
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
			'comment_content'      => self::sanitize_comment_content( $comment_content ),
			'comment_type'         => 'comment',
			'comment_approved'     => self::comment_approval( $config_values ),
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

		$ai_response = $this->call_ai( $config_values, $trigger_data, $prompt, "You are a professional WordPress content creator.", 'post_writer' );

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
				
				// 2. Generate the image and keep it in the Media Library.
				$image_result = $this->call_openai_image( $config_values, $trigger_data, $image_prompt );

				if ( $image_result['success'] ) {
					$saved = self::save_generated_image( $image_result );
					if ( is_wp_error( $saved ) ) {
						$results['image_error'] = 'Image generated but not saved: ' . $saved->get_error_message();
						if ( ! empty( $image_result['url'] ) ) {
							$results['generated_image_url'] = $image_result['url'];
						}
					} else {
						$results['generated_image_id']  = (int) $saved;
						$results['generated_image_url'] = wp_get_attachment_url( $saved );
					}
				} else {
					$results['image_error'] = $image_result['message'];
				}
			}
		}

		return $results;
	}

	/**
	 * Call OpenAI image generation.
	 *
	 * DALL-E 2/3 were shut down on 12 May 2026; saved workflows are moved to gpt-image-2 (gpt-image-1 is gone too).
	 * GPT Image models return base64 data (no URL) and have their own size list.
	 */
	private function call_openai_image( $config_values, $trigger_data, $prompt ) {
		$api_key = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model   = isset( $config_values['image_model'] ) ? (string) $config_values['image_model'] : 'gpt-image-2';
		$size    = isset( $config_values['image_size'] ) ? (string) $config_values['image_size'] : '1024x1024';

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'API Key is required for image generation.' );
		}

		// R10: OpenAI no longer offers gpt-image-1 ("does not exist"); saved workflows with it, or with DALL-E, use gpt-image-2.
		if ( '' === $model || 0 === strpos( $model, 'dall-e' ) || 'gpt-image-1' === $model ) {
			$model = 'gpt-image-2';
		}
		$sizes = array(
			'1024x1792' => '1024x1536',
			'1792x1024' => '1536x1024',
			'512x512'   => '1024x1024',
			'256x256'   => '1024x1024',
		);
		if ( isset( $sizes[ $size ] ) ) {
			$size = $sizes[ $size ];
		}
		if ( ! in_array( $size, array( '1024x1024', '1024x1536', '1536x1024', 'auto' ), true ) ) {
			$size = '1024x1024';
		}

		$response = qcld_wpbot_spend_post(
			'automator',
			'post_image',
			'https://api.openai.com/v1/images/generations',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'  => $model,
						'prompt' => $prompt,
						'n'      => 1,
						'size'   => $size,
					)
				),
				'timeout' => 120,
			),
			array( 'provider' => 'openai' )
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code >= 200 && $response_code < 300 && ( ! empty( $data['data'][0]['b64_json'] ) || ! empty( $data['data'][0]['url'] ) ) ) {
			return array(
				'success' => true,
				'b64'     => isset( $data['data'][0]['b64_json'] ) ? $data['data'][0]['b64_json'] : '',
				'url'     => isset( $data['data'][0]['url'] ) ? $data['data'][0]['url'] : '',
			);
		}

		$err = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown OpenAI Image error';
		return array( 'success' => false, 'message' => $err, 'code' => $response_code );
	}

	/**
	 * Store a generated image (base64 data or a temporary URL) in the Media Library.
	 *
	 * @return int|\WP_Error Attachment ID.
	 */
	private static function save_generated_image( $image_result ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( empty( $image_result['b64'] ) ) {
			return empty( $image_result['url'] )
				? new \WP_Error( 'no_image', 'The API returned no image.' )
				: media_sideload_image( $image_result['url'], 0, null, 'id' );
		}

		$bytes = base64_decode( $image_result['b64'], true );
		if ( false === $bytes ) {
			return new \WP_Error( 'bad_image', 'The API returned invalid image data.' );
		}

		$upload = wp_upload_bits( 'ai-image-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false ) . '.png', null, $bytes );
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_failed', $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'AI generated image',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		return (int) $attachment_id;
	}

	/**
	 * Call AI Provider helper
	 */
	private function call_ai( $config_values, $trigger_data, $prompt, $system_prompt = '', $spend_feature = 'action' ) {
		$provider = isset( $config_values['ai_provider'] ) ? $config_values['ai_provider'] : 'openai';

		switch ( $provider ) {
			case 'gemini':
				return $this->call_gemini( $config_values, $trigger_data, $prompt, $system_prompt, $spend_feature );
			case 'claude':
				return $this->call_claude( $config_values, $trigger_data, $prompt, $system_prompt, $spend_feature );
			case 'openai':
			default:
				return $this->call_openai( $config_values, $trigger_data, $prompt, $system_prompt, $spend_feature );
		}
	}

	/**
	 * Call OpenAI API helper
	 */
	private function call_openai( $config_values, $trigger_data, $prompt, $system_prompt = '', $spend_feature = 'action' ) {
		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? (string) $config_values['model'] : '';
		// The model dropdown mixes providers; a Gemini/Claude model can't be sent to OpenAI.
		if ( '' === $model || preg_match( '/^(gemini-|claude-)/', $model ) ) {
			$model = \WPbot_Automator\Core\OpenAI_Client::DEFAULT_MODEL;
		}
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

		$response = \WPbot_Automator\Core\OpenAI_Client::chat( $api_key, $model, $messages, array(
			'max_tokens'    => $max_tokens,
			'temperature'   => $temperature,
			'spend_feature' => $spend_feature,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$response_code = $response['code'];
		$data          = $response['data'];

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
	private function call_gemini( $config_values, $trigger_data, $prompt, $system_prompt = '', $spend_feature = 'action' ) {
		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : '';
		// The model dropdown mixes providers; ignore a non-Gemini or retired model.
		if ( 0 !== strpos( (string) $model, 'gemini-' ) || preg_match( '/^gemini-(1\.|2\.0)/', $model ) ) {
			$model = 'gemini-3.8-flash';
		}
		$temperature = isset( $config_values['temperature'] ) ? floatval( $config_values['temperature'] ) : 0.7;

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'Gemini API Key is required.' );
		}

		$full_prompt = $system_prompt ? $system_prompt . "\n\n" . $prompt : $prompt;

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';

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
			'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => $api_key ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		);

		$response = qcld_wpbot_spend_wrap(
			'automator',
			$spend_feature,
			static function () use ( $url, $args ) {
				return wp_remote_request( $url, $args );
			},
			array( 'provider' => 'gemini', 'model' => $model )
		);

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
	private function call_claude( $config_values, $trigger_data, $prompt, $system_prompt = '', $spend_feature = 'action' ) {
		$api_key    = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model      = isset( $config_values['model'] ) ? $config_values['model'] : '';
		$max_tokens = isset( $config_values['max_tokens'] ) ? intval( $config_values['max_tokens'] ) : 4096;

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'Claude API Key is required.' );
		}

		// The model dropdown mixes providers; ignore a non-Claude model.
		if ( 0 !== strpos( (string) $model, 'claude-' ) ) {
			$model = \WPbot_Automator\Core\Claude_Client::DEFAULT_MODEL;
		}

		return \WPbot_Automator\Core\Claude_Client::messages(
			$api_key,
			$model,
			array( array( 'role' => 'user', 'content' => $prompt ) ),
			array(
				'system'        => $system_prompt,
				'max_tokens'    => $max_tokens,
				'temperature'   => isset( $config_values['temperature'] ) ? $config_values['temperature'] : '',
				'spend_feature' => $spend_feature,
			)
		);
	}
}

<?php
/**
 * CSV import with column mapping, WordPress users and WooCommerce customers.
 *
 * Two steps: preview() stores the upload in a private folder and guesses the
 * columns; run() imports one batch per call (the screen calls it until done).
 *
 * Consent: imported people become subscribers only when the owner confirms
 * they agreed to receive emails; otherwise they are leads. An import never
 * re-subscribes someone who unsubscribed, bounced or complained.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Audience;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer {

	const BATCH  = 300;
	const FIELDS = array( 'email', 'first_name', 'last_name', 'name', 'phone', 'company', 'website', 'language', 'lists', 'tags', 'ignore' );

	/**
	 * Guess the field of a header (pure; unit tested).
	 *
	 * @param string $header Column header.
	 * @return string Field, "attribute:<key>" or "ignore".
	 */
	public static function guess( $header ) {
		$h   = strtolower( trim( (string) $header ) );
		$map = array(
			'email'      => '/^(e-?mail|email address|e-?mail address|mail)$/',
			'first_name' => '/^(first|first ?name|given ?name|firstname|fname)$/',
			'last_name'  => '/^(last|last ?name|surname|family ?name|lastname|lname)$/',
			'name'       => '/^(name|full ?name|contact|contact ?name)$/',
			'phone'      => '/^(phone|mobile|telephone|tel|phone ?number|whatsapp)$/',
			'company'    => '/^(company|organi[sz]ation|business)$/',
			'website'    => '/^(website|url|web|site)$/',
			'language'   => '/^(language|lang|locale)$/',
			'lists'      => '/^(lists?|groups?|assigned lists)$/',
			'tags'       => '/^(tags?|labels?|assigned tags)$/',
		);
		foreach ( $map as $field => $pattern ) {
			if ( preg_match( $pattern, $h ) ) {
				return $field;
			}
		}
		$key = sanitize_key( str_replace( ' ', '_', $h ) );
		return '' !== $key && ! in_array( $key, array( 'status', 'source', 'id', 'created_at', 'subscription_date' ), true ) ? 'attribute:' . $key : 'ignore';
	}

	/**
	 * Detect the delimiter of a CSV line (pure).
	 *
	 * @param string $line First line.
	 * @return string
	 */
	public static function delimiter( $line ) {
		$best = ',';
		$max  = 0;
		foreach ( array( ',', ';', "\t", '|' ) as $d ) {
			$n = substr_count( (string) $line, $d );
			if ( $n > $max ) {
				$max  = $n;
				$best = $d;
			}
		}
		return $best;
	}

	/**
	 * Turn one CSV row into contact data with a mapping (pure; unit tested).
	 *
	 * @param array $row     Cells.
	 * @param array $mapping Column index => field.
	 * @return array{data:array,lists:string[],tags:string[]}
	 */
	public static function row( array $row, array $mapping ) {
		$data  = array( 'attributes' => array() );
		$lists = array();
		$tags  = array();
		foreach ( $mapping as $index => $field ) {
			$value = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
			if ( '' === $value || 'ignore' === $field ) {
				continue;
			}
			if ( 0 === strpos( (string) $field, 'attribute:' ) ) {
				$data['attributes'][ substr( $field, 10 ) ] = $value;
			} elseif ( 'name' === $field ) {
				$parts              = preg_split( '/\s+/', $value, 2 );
				$data['first_name'] = isset( $data['first_name'] ) ? $data['first_name'] : $parts[0];
				$data['last_name']  = isset( $data['last_name'] ) ? $data['last_name'] : ( isset( $parts[1] ) ? $parts[1] : '' );
			} elseif ( 'lists' === $field || 'tags' === $field ) {
				$names = array_filter( array_map( 'trim', preg_split( '/[,;|]/', $value ) ), 'strlen' );
				if ( 'lists' === $field ) {
					$lists = array_merge( $lists, $names );
				} else {
					$tags = array_merge( $tags, $names );
				}
			} elseif ( in_array( $field, self::FIELDS, true ) ) {
				$data[ $field ] = $value;
			}
		}
		return array(
			'data'  => $data,
			'lists' => $lists,
			'tags'  => $tags,
		);
	}

	/**
	 * Store an uploaded file and preview it.
	 *
	 * @param array $file Upload ($_FILES entry).
	 * @return array|WP_Error token, headers, sample, mapping, rows.
	 */
	public static function preview( array $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'qcld_mk_upload', __( 'The file could not be uploaded.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$dir = self::dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$token = wp_generate_password( 24, false, false );
		$path  = $dir . '/' . $token . '.csv';
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Private folder, CSV only.
			return new WP_Error( 'qcld_mk_upload', __( 'The file could not be saved.', 'chatbot' ), array( 'status' => 500 ) );
		}
		$read = self::read( $path, 0, 6 );
		if ( ! $read['headers'] ) {
			wp_delete_file( $path );
			return new WP_Error( 'qcld_mk_csv', __( 'The file has no rows.', 'chatbot' ), array( 'status' => 400 ) );
		}
		return array(
			'token'   => $token,
			'headers' => $read['headers'],
			'sample'  => $read['rows'],
			'mapping' => array_map( array( __CLASS__, 'guess' ), $read['headers'] ),
			'rows'    => self::count_rows( $path ),
		);
	}

	/**
	 * Import one batch.
	 *
	 * @param string $token   Upload token.
	 * @param array  $mapping Column index => field.
	 * @param array  $options offset, consent (bool: they agreed to emails), list_ids, tag_ids.
	 * @return array|WP_Error offset, done, imported, updated, skipped.
	 */
	public static function run( $token, array $mapping, array $options ) {
		$path = self::path( $token );
		if ( '' === $path ) {
			return new WP_Error( 'qcld_mk_token', __( 'Upload the file again.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( 'email', $mapping, true ) && ! in_array( 'phone', $mapping, true ) ) {
			return new WP_Error( 'qcld_mk_mapping', __( 'Choose which column holds the email address.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$offset = isset( $options['offset'] ) ? max( 0, (int) $options['offset'] ) : 0;
		$read   = self::read( $path, $offset, self::BATCH );
		$out    = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
		);
		$groups = self::groups( $options );
		foreach ( $read['rows'] as $cells ) {
			$parsed = self::row( $cells, $mapping );
			$result = self::save( $parsed, ! empty( $options['consent'] ), $groups, 'import', __( 'Imported from a CSV file', 'chatbot' ) );
			++$out[ $result ];
		}
		$offset       += count( $read['rows'] );
		$done          = count( $read['rows'] ) < self::BATCH;
		$out['offset'] = $offset;
		$out['done']   = $done;
		if ( $done ) {
			wp_delete_file( $path );
		}
		return $out;
	}

	/**
	 * Import WordPress users or WooCommerce customers, one batch per call.
	 *
	 * @param string $source  users or woocommerce.
	 * @param array  $options offset, consent, list_ids, tag_ids.
	 * @return array|WP_Error
	 */
	public static function run_source( $source, array $options ) {
		$offset = isset( $options['offset'] ) ? max( 0, (int) $options['offset'] ) : 0;
		$args   = array(
			'number'  => self::BATCH,
			'offset'  => $offset,
			'orderby' => 'ID',
			'fields'  => array( 'ID', 'user_email', 'display_name' ),
		);
		if ( 'woocommerce' === $source ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return new WP_Error( 'qcld_mk_woo', __( 'WooCommerce is not active.', 'chatbot' ), array( 'status' => 400 ) );
			}
			$args['role__in'] = array( 'customer' );
		}
		$users  = get_users( $args );
		$groups = self::groups( $options );
		$out    = array(
			'imported' => 0,
			'updated'  => 0,
			'skipped'  => 0,
		);
		$label  = 'woocommerce' === $source ? __( 'Imported from WooCommerce customers', 'chatbot' ) : __( 'Imported from WordPress users', 'chatbot' );
		foreach ( $users as $user ) {
			$parsed = array(
				'data'  => array(
					'email'      => $user->user_email,
					'first_name' => (string) get_user_meta( $user->ID, 'first_name', true ),
					'last_name'  => (string) get_user_meta( $user->ID, 'last_name', true ),
					'phone'      => (string) get_user_meta( $user->ID, 'billing_phone', true ),
					'company'    => (string) get_user_meta( $user->ID, 'billing_company', true ),
				),
				'lists' => array(),
				'tags'  => array(),
			);
			if ( '' === $parsed['data']['first_name'] ) {
				$parsed['data']['first_name'] = (string) $user->display_name;
			}
			++$out[ self::save( $parsed, ! empty( $options['consent'] ), $groups, 'woocommerce' === $source ? 'woocommerce' : 'wp_user', $label ) ];
			$contact = Contacts::by_email( (string) $user->user_email );
			if ( $contact ) {
				Identities::link( (int) $contact['id'], 'wp_user', (string) $user->ID );
			}
		}
		$out['offset'] = $offset + count( $users );
		$out['done']   = count( $users ) < self::BATCH;
		return $out;
	}

	/**
	 * Save one parsed row. Returns imported, updated or skipped.
	 *
	 * @param array  $parsed  data, lists, tags.
	 * @param bool   $consent The owner confirms marketing consent.
	 * @param array  $groups  List/tag id => reason.
	 * @param string $source  Source.
	 * @param string $reason  Reason.
	 * @return string
	 */
	private static function save( array $parsed, $consent, array $groups, $source, $reason ) {
		foreach ( $parsed['lists'] as $name ) {
			$groups[ self::group_by_name( 'list', $name ) ] = $reason;
		}
		foreach ( $parsed['tags'] as $name ) {
			$groups[ self::group_by_name( 'tag', $name ) ] = $reason;
		}
		unset( $groups[0] );
		$result = Contacts::capture( $parsed['data'], $consent ? 'marketing' : 'contact', $source, $groups, false, array( 'skip_double_opt_in' => true ) );
		if ( is_wp_error( $result ) ) {
			return 'skipped';
		}
		if ( ! $result['created'] && ! empty( $parsed['data']['attributes'] ) ) {
			Contacts::update( (int) $result['id'], array( 'attributes' => $parsed['data']['attributes'] ) );
		}
		return $result['created'] ? 'imported' : 'updated';
	}

	/**
	 * List/tag ids from the options, with the reason.
	 *
	 * @param array $options list_ids, tag_ids.
	 * @return array
	 */
	private static function groups( array $options ) {
		$groups = array();
		foreach ( array( 'list_ids', 'tag_ids' ) as $key ) {
			foreach ( isset( $options[ $key ] ) && is_array( $options[ $key ] ) ? $options[ $key ] : array() as $id ) {
				$groups[ (int) $id ] = __( 'Imported', 'chatbot' );
			}
		}
		unset( $groups[0] );
		return $groups;
	}

	/**
	 * A list or tag by name, created when missing.
	 *
	 * @param string $type list|tag.
	 * @param string $name Name.
	 * @return int
	 */
	private static function group_by_name( $type, $name ) {
		foreach ( Lists::all( $type, $name ) as $group ) {
			if ( 0 === strcasecmp( $group['name'], $name ) ) {
				return (int) $group['id'];
			}
		}
		$id = Lists::create( $type, $name );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Read rows from a CSV file.
	 *
	 * @param string $path   Path.
	 * @param int    $offset Data rows to skip.
	 * @param int    $limit  Rows to read.
	 * @return array{headers:string[],rows:array[]}
	 */
	private static function read( $path, $offset, $limit ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming a large CSV.
		if ( ! $handle ) {
			return array(
				'headers' => array(),
				'rows'    => array(),
			);
		}
		$first     = (string) fgets( $handle );
		$delimiter = self::delimiter( $first );
		$headers   = str_getcsv( preg_replace( '/^\xEF\xBB\xBF/', '', rtrim( $first, "\r\n" ) ), $delimiter, '"', '\\' );
		$rows      = array();
		$index     = 0;
		$taken     = 0;
		while ( $taken < $limit ) {
			$cells = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
			if ( false === $cells ) {
				break;
			}
			if ( array( null ) === $cells ) {
				continue;
			}
			if ( $index++ < $offset ) {
				continue;
			}
			$rows[] = $cells;
			++$taken;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with fopen.
		return array(
			'headers' => array_map( 'trim', $headers ),
			'rows'    => $rows,
		);
	}

	/**
	 * Data rows in a file.
	 *
	 * @param string $path Path.
	 * @return int
	 */
	private static function count_rows( $path ) {
		$n      = -1;
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Counting lines.
		while ( $handle && false !== fgets( $handle ) ) {
			++$n;
		}
		if ( $handle ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with fopen.
		}
		return max( 0, $n );
	}

	/**
	 * The private upload folder.
	 *
	 * @return string|WP_Error
	 */
	private static function dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'qcld-mk-imports';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'qcld_mk_dir', __( 'The uploads folder is not writable.', 'chatbot' ), array( 'status' => 500 ) );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protect the folder.
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protect the folder.
		}
		return $dir;
	}

	/**
	 * Path of an upload token, or ''.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function path( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
		$dir   = self::dir();
		if ( '' === $token || is_wp_error( $dir ) ) {
			return '';
		}
		$path = $dir . '/' . $token . '.csv';
		return file_exists( $path ) ? $path : '';
	}
}

<?php
/**
 * Basic Elementor widgets (HC1-13, owner decision D3: basic widgets in Free).
 *
 * Loaded only when Elementor registers widgets. Each widget renders the same
 * part as its shortcode and block (Embeds::render()).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Front;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Small widget classes that only exist with Elementor.

/**
 * Shared widget code.
 */
abstract class Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Part id (Embeds::parts()).
	 *
	 * @return string
	 */
	abstract protected function part();

	/**
	 * Controls: key => [ label, type (text, number, select), options ].
	 *
	 * @return array
	 */
	protected function fields() {
		return array();
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'wpbot-help-' . $this->part();
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-help-o';
	}

	/**
	 * Categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'wpbot-help' );
	}

	/**
	 * Keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'help', 'docs', 'knowledge base', 'faq' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$fields = $this->fields();
		if ( ! $fields ) {
			return;
		}
		$this->start_controls_section( 'content', array( 'label' => __( 'Settings', 'chatbot' ) ) );
		$defaults = Embeds::parts()[ $this->part() ];
		foreach ( $fields as $key => $field ) {
			$types = array(
				'text'   => \Elementor\Controls_Manager::TEXT,
				'number' => \Elementor\Controls_Manager::NUMBER,
				'select' => \Elementor\Controls_Manager::SELECT,
			);
			$args  = array(
				'label'   => $field[0],
				'type'    => $types[ $field[1] ],
				'default' => $defaults[ $key ],
			);
			if ( 'select' === $field[1] ) {
				$args['options'] = $field[2];
			}
			$this->add_control( $key, $args );
		}
		$this->end_controls_section();
	}

	/**
	 * Output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = (array) $this->get_settings_for_display();
		$atts     = array_merge( Embeds::parts()[ $this->part() ], array_intersect_key( $settings, Embeds::parts()[ $this->part() ] ) );
		echo Embeds::render( $this->part(), $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when rendered.
	}
}

/**
 * Search.
 */
final class Elementor_Search extends Elementor_Widget {

	/**
	 * Part.
	 *
	 * @return string
	 */
	protected function part() {
		return 'search';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Help search', 'chatbot' );
	}

	/**
	 * Controls.
	 *
	 * @return array
	 */
	protected function fields() {
		return array( 'placeholder' => array( __( 'Placeholder', 'chatbot' ), 'text' ) );
	}
}

/**
 * Topics.
 */
final class Elementor_Topics extends Elementor_Widget {

	/**
	 * Part.
	 *
	 * @return string
	 */
	protected function part() {
		return 'topics';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Help topics', 'chatbot' );
	}

	/**
	 * Controls.
	 *
	 * @return array
	 */
	protected function fields() {
		return array( 'articles' => array( __( 'Articles per topic', 'chatbot' ), 'number' ) );
	}
}

/**
 * Article list.
 */
final class Elementor_Articles extends Elementor_Widget {

	/**
	 * Part.
	 *
	 * @return string
	 */
	protected function part() {
		return 'articles';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Help articles', 'chatbot' );
	}

	/**
	 * Controls.
	 *
	 * @return array
	 */
	protected function fields() {
		return array(
			'title'    => array( __( 'Heading', 'chatbot' ), 'text' ),
			'category' => array( __( 'Category (slug)', 'chatbot' ), 'text' ),
			'limit'    => array( __( 'Number of articles', 'chatbot' ), 'number' ),
			'orderby'  => array(
				__( 'Order', 'chatbot' ),
				'select',
				array(
					'order'   => __( 'Help Center order', 'chatbot' ),
					'recent'  => __( 'Recently updated', 'chatbot' ),
					'title'   => __( 'Title', 'chatbot' ),
					'popular' => __( 'Most read', 'chatbot' ),
				),
			),
		);
	}
}

/**
 * Glossary.
 */
final class Elementor_Glossary extends Elementor_Widget {

	/**
	 * Part.
	 *
	 * @return string
	 */
	protected function part() {
		return 'glossary';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Glossary', 'chatbot' );
	}
}

/**
 * Help launcher.
 */
final class Elementor_Launcher extends Elementor_Widget {

	/**
	 * Part.
	 *
	 * @return string
	 */
	protected function part() {
		return 'launcher';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Help button', 'chatbot' );
	}

	/**
	 * Controls.
	 *
	 * @return array
	 */
	protected function fields() {
		return array( 'label' => array( __( 'Label', 'chatbot' ), 'text' ) );
	}
}

/**
 * The widget classes.
 */
final class Elementor_Widgets {

	/**
	 * Classes to register.
	 *
	 * @return string[]
	 */
	public static function classes() {
		return array( Elementor_Search::class, Elementor_Topics::class, Elementor_Articles::class, Elementor_Glossary::class, Elementor_Launcher::class );
	}
}

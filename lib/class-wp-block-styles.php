<?php
/**
 * Blocks API: WP_Block_Styles class
 *
 * @package Gutenberg
 */

/**
 * Handles block styles enqueueing.
 */
class WP_Block_Styles {

	/**
	 * An array of blocks that have already been styled.
	 *
	 * @access protected
	 *
	 * @var array
	 */
	protected $styled_blocks = array();

	/**
	 * Whether the injection script has been added or not.
	 *
	 * @access protected
	 *
	 * @var bool
	 */
	protected $injection_script_added = false;

	/**
	 * An array of core blocks that can cause layout shifts.
	 * These styles get added inline to avoid shifting layouts issues when the page loads.
	 *
	 * @access protected
	 *
	 * @var array
	 */
	protected $layout_shift_blocks = array(
		'core/columns',
	);

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function __construct() {

		// Add split styles if the theme supports it.
		if ( current_theme_supports( 'split-block-styles' ) ) {
			add_filter( 'render_block', array( $this, 'add_styles' ), 10, 2 );
			return;
		} else {
			// If we got here we need to add all styles for all blocks.
			add_action( 'init', array( $this, 'print_concat_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_concat_styles' ), 9999 );
		}

		// Add editor styles.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_concat_styles' ], 1 );
	}

	/**
	 * Get an array of block styles.
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function get_blocks_styles() {
		$styles      = array();
		$core_blocks = array(
			'core/audio',
			'core/button',
			'core/buttons',
			'core/calendar',
			'core/categories',
			'core/columns',
			'core/cover',
			'core/embed',
			'core/file',
			'core/gallery',
			'core/heading',
			'core/image',
			'core/latest-comments',
			'core/latest-posts',
			'core/list',
			'core/media-text',
			'core/navigation',
			'core/navigation-link',
			'core/paragraph',
			'core/post-author',
			'core/pullquote',
			'core/quote',
			'core/rss',
			'core/search',
			'core/separator',
			'core/site-logo',
			'core/social-links',
			'core/spacer',
			'core/subhead',
			'core/table',
			'core/text-columns',
			'core/video',
		);

		foreach ( $core_blocks as $block ) {
			$filename  = str_replace( 'core/', '', $block );
			$filename .= is_rtl() ? '-rtl' : '';

			if ( file_exists( gutenberg_dir_path() . "packages/block-library/build-style/$filename.css" ) ) {
				$styles[ $block ] = array(
					array(
						'handle' => "core-$filename-block-styles",
						'src'    => gutenberg_url( "packages/block-library/build-style/$filename.css" ),
						'ver'    => filemtime( gutenberg_dir_path() . "packages/block-library/build-style/$filename.css" ),
						'media'  => 'all',
						'method' => in_array( $block, $this->layout_shift_blocks, true ) ? 'inline' : 'inject',
						'path'   => gutenberg_dir_path() . "packages/block-library/build-style/$filename.css",
					),
				);
			}
		}

		/**
		 * Filter collection of stylesheets for all block-types.
		 *
		 * @param array  $styles An array of stylesheets per block-type.
		 */
		return apply_filters( 'block_styles', $styles );
	}

	/**
	 * Add block-styles on the 1st occurence of a block-type.
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 *
	 * @return string
	 */
	function add_styles( $block_content, $block ) {

		// Check if we need to early return.
		if (
			! isset( $block['blockName'] ) || // Sanity check.
			in_array( $block['blockName'], $this->styled_blocks, true ) // Styles for this block-type have already been added.
		) {
			return $block_content;
		}

		$blocks_styles = $this->get_blocks_styles();
		$block_styles  = isset( $blocks_styles[ $block['blockName'] ] ) ? $blocks_styles[ $block['blockName'] ] : array();

		foreach ( $block_styles as $block_style ) {
			$this->add_block_styles( $block_style );
		}
		$this->styled_blocks[] = $block['blockName'];

		return $block_content;
	}

	/**
	 * Adds a block style.
	 *
	 * @access public
	 *
	 * @param array $block_style The block-style we're adding.
	 *
	 * @return void
	 */
	public function add_block_styles( $block_style ) {

		$block_style['method'] = ( isset( $block_style['method'] ) ) ? $block_style['method'] : 'inject';

		switch ( $block_style['method'] ) {
			case 'inline':
				$this->add_block_style_inline( $block_style );
				break;

			case 'inline_link':
				$this->add_block_style_inline_link( $block_style );
				break;

			case 'footer':
				$this->add_block_style_footer( $block_style );
				break;

			case 'inject':
				$this->add_block_style_inject( $block_style );
				break;
		}
	}

	/**
	 * Adds a block style.
	 *
	 * @access public
	 *
	 * @param array $block_style The block-style we're adding.
	 *
	 * @return void
	 */
	public function add_block_style_inline( $block_style ) {
		$id = $block_style['handle'] . '-css';

		echo '<style id="' . esc_attr( $id ) . '">';
		include $block_style['path'];
		echo '</style>';
	}

	/**
	 * Adds a block style.
	 *
	 * @access public
	 *
	 * @param array $block_style The block-style we're adding.
	 *
	 * @return void
	 */
	public function add_block_style_inline_link( $block_style ) {
		$id    = $block_style['handle'] . '-css';
		$src   = $block_style['src'];
		$media = isset( $block_style['media'] ) ? $block_style['media'] : 'all';
		if ( $block_style['ver'] ) {
			$src = add_query_arg( 'ver', $block_style['ver'], $src );
		}

		echo '<link id="' . esc_attr( $id ) . '" rel="stylesheet" href="' . esc_url( $src ) . '" media="' . esc_attr( $media ) . '">';
	}

	/**
	 * Adds a block style.
	 *
	 * @access public
	 *
	 * @param array $block_style The block-style we're adding.
	 *
	 * @return void
	 */
	public function add_block_style_footer( $block_style ) {
		wp_enqueue_style(
			$block_style['handle'],
			$block_style['src'],
			array(),
			$block_style['ver'],
			isset( $block_style['media'] ) ? $block_style['media'] : 'all'
		);
	}

	/**
	 * Adds a block style.
	 *
	 * @access public
	 *
	 * @param array $block_style The block-style we're adding.
	 *
	 * @return void
	 */
	public function add_block_style_inject( $block_style ) {
		if ( ! $this->injection_script_added ) {
			$this->add_injection_script();
			$this->injection_script_added = true;
		}

		$style = wp_parse_args(
			$block_style,
			array(
				'handle' => '',
				'src'    => '',
				'ver'    => false,
			)
		);
		echo "<script>wpEnqueueStyle('{$style['handle']}', '{$style['src']}', [], '{$style['ver']}', '{$style['media']}')</script>";

	}

	/**
	 * Prints the JS script that allows us to enqueue/inject scripts directly.
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function add_injection_script() {
		?>
		<script id="wp-enqueue-style-script">
		function wpEnqueueStyle( handle, src, deps, ver, media ) {

			// Create the element.
			var style = document.createElement( 'link' ),
				isFirst = ! window.wpEnqueueStyleLastInjectedEl,
				injectEl = isFirst
					? document.head
					: document.getElementById( window.wpEnqueueStyleLastInjectedEl ),
				injectPos = isFirst
					? 'afterbegin'
					: 'afterend';

			// Add element props for the stylesheet.
			style.id = handle + '-css';
			style.rel = 'stylesheet';
			style.href = src;
			if ( ver ) {
				style.href += 0 < style.href.indexOf( '?' ) ? '&ver=' + ver : '?ver=' + ver;
			}
			style.media = media ? media : 'all';

			// Set the global var so we know where to add the next style.
			// This helps us preserve priorities and inject styles one after the other instead of reversed.
			window.wpEnqueueStyleLastInjectedEl = handle + '-css';

			// Inject the element.
			injectEl.insertAdjacentElement( injectPos, style );
		}
		</script>
		<?php
	}

	/**
	 * Enqueue concat styles.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function enqueue_concat_styles() {
		wp_enqueue_style(
			'wp-block-library-styles',
			add_query_arg( 'print-core-styles', '1', home_url() ),
			array(),
			filemtime( gutenberg_dir_path() . 'build/block-library/style.css' )
		);
	}

	/**
	 * Print all styles when we get the "?print-core-styles=1" URL.
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function print_concat_styles() {
		if ( ! isset( $_GET['print-core-styles'] ) || '1' !== $_GET['print-core-styles'] ) {
			return;
		}

		header( 'Content-Type: text/css' );

		$blocks_styles = $this->get_blocks_styles();
		foreach ( $blocks_styles as $styles ) {
			foreach ( $styles as $style ) {
				include $style['path'];
			}
		}
		exit();
	}
}
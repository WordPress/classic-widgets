<?php
/**
 * Plugin Name: Classic Widgets
 * Plugin URI:  https://wordpress.org/plugins/classic-widgets/
 * Description: Enables the classic widgets settings screens in Appearance - Widgets and the Customizer. Disables the block editor from managing widgets. Classic Widgets now contains a Settings page for advanced options!
 * Author: WordPress Contributors
 * Author URI:  https://github.com/WordPress/classic-widgets/
 * Version:     0.4.1
 * Requires at least: 5.8
 * Requires PHP: 7.4 or later
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: classic-widgets
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CLASSIC_WIDGETS_VERSION', '0.4.1' );

/** Defaults */
function cw_defaults(): array {
	return array(
		'disable_block_widgets' => 1,
		'sticky_active_panel'   => 0,
		'sticky_offset'         => 32,
	);
}

/** Get options w/ defensive merge */
function cw_get_options(): array {
	$defaults = cw_defaults();
	$saved    = get_option( 'classic_widgets_options', array() );
	return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

/** Core behavior */
add_action( 'plugins_loaded', function () {
	$opts = cw_get_options();
	if ( ! empty( $opts['disable_block_widgets'] ) ) {
		add_filter( 'gutenberg_use_widgets_block_editor', '__return_false', 100 );
		add_filter( 'use_widgets_block_editor', '__return_false', 100 );
	}
});

/** Admin enhancements (sticky panel) */
add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'widgets.php' !== $hook ) return;

	$opts = cw_get_options();
	if ( empty( $opts['sticky_active_panel'] ) ) return;

	$offset = (int) $opts['sticky_offset'];

	// Register blank handles we can safely inline into.
	wp_register_style( 'classic-widgets-admin', false, array(), CLASSIC_WIDGETS_VERSION );
	wp_enqueue_style( 'classic-widgets-admin' );

	wp_register_script( 'classic-widgets-admin', false, array(), CLASSIC_WIDGETS_VERSION, true );
	wp_enqueue_script( 'classic-widgets-admin' );

	$css = <<<CSS
/* Make right column sticky when supported */
.widget-liquid-right { position: sticky; top: {$offset}px; align-self: flex-start; }
@media (min-width:782px){
	#wpbody-content .wrap .widgets-holder-wrap { contain: layout style; }
	#widgets-left, #widgets-right { box-sizing: border-box; }
}
CSS;
	wp_add_inline_style( 'classic-widgets-admin', $css );

	$js = <<<JS
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var panel = document.querySelector('.widget-liquid-right');
		if(!panel){ return; }

		var adminbar = document.getElementById('wpadminbar');
		var top = (adminbar ? adminbar.offsetHeight : 0) + {$offset};

		// If CSS sticky is blocked, use small fixed fallback.
		var stickyOK = (CSS && CSS.supports && CSS.supports('position','sticky'));
		if(!stickyOK){
			var start = panel.getBoundingClientRect().top + window.pageYOffset - top;
			function onScroll(){
				var y = window.pageYOffset || document.documentElement.scrollTop;
				if(y > start){
					panel.style.position = 'fixed';
					panel.style.top = top + 'px';
					panel.style.right = '32px';
					panel.style.maxWidth = '420px';
				}else{
					panel.style.position = 'static';
					panel.style.top = '';
					panel.style.right = '';
					panel.style.maxWidth = '';
				}
			}
			window.addEventListener('scroll', onScroll, {passive:true});
			onScroll();
		}else{
			// Respect admin bar height dynamically
			panel.style.top = top + 'px';
		}
	});
})();
JS;
	wp_add_inline_script( 'classic-widgets-admin', $js );
});

/** SETTINGS — split into two functions to avoid double render */
function cw_register_settings() {
	register_setting(
		'classic_widgets',
		'classic_widgets_options',
		array(
			'type'              => 'array',
			'default'           => cw_defaults(),
			'sanitize_callback' => function( $input ){
				$d = cw_defaults();
				return array(
					'disable_block_widgets' => empty( $input['disable_block_widgets'] ) ? 0 : 1,
					'sticky_active_panel'   => empty( $input['sticky_active_panel'] ) ? 0 : 1,
					'sticky_offset'         => isset( $input['sticky_offset'] ) ? max( 0, (int) $input['sticky_offset'] ) : $d['sticky_offset'],
				);
			},
		)
	);

	add_settings_section(
		'cw_main',
		__( 'Classic Widgets Settings', 'classic-widgets' ),
		function(){ echo '<p>' . esc_html__( 'Control the Classic Widgets behavior and UI helpers.', 'classic-widgets' ) . '</p>'; },
		'classic_widgets'
	);

	add_settings_field(
		'disable_block_widgets',
		__( 'Disable Block Widgets', 'classic-widgets' ),
		function(){
			$o = cw_get_options(); ?>
			<label>
				<input type="checkbox" name="classic_widgets_options[disable_block_widgets]" value="1" <?php checked( $o['disable_block_widgets'], 1 ); ?> />
				<?php esc_html_e( 'Force the classic widgets screen (recommended).', 'classic-widgets' ); ?>
			</label>
		<?php },
		'classic_widgets',
		'cw_main'
	);

	add_settings_field(
		'sticky_active_panel',
		__( 'Sticky Active Widgets Panel', 'classic-widgets' ),
		function(){
			$o = cw_get_options(); ?>
			<label>
				<input type="checkbox" name="classic_widgets_options[sticky_active_panel]" value="1" <?php checked( $o['sticky_active_panel'], 1 ); ?> />
				<?php esc_html_e( 'Keep the right column (Active Widgets) in view while scrolling.', 'classic-widgets' ); ?>
			</label>
			<p><label>
				<?php esc_html_e( 'Top offset (px):', 'classic-widgets' ); ?>
				<input type="number" min="0" step="1" name="classic_widgets_options[sticky_offset]" value="<?php echo esc_attr( (string) (int) $o['sticky_offset'] ); ?>" />
			</label></p>
		<?php },
		'classic_widgets',
		'cw_main'
	);
}
add_action( 'admin_init', 'cw_register_settings' );

function cw_add_options_page() {
	add_options_page(
		__( 'Classic Widgets', 'classic-widgets' ),
		__( 'Classic Widgets', 'classic-widgets' ),
		'manage_options',
		'classic-widgets',
		function(){
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Classic Widgets', 'classic-widgets' ); ?></h1>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'classic_widgets' );
					do_settings_sections( 'classic_widgets' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}
	);
}
add_action( 'admin_menu', 'cw_add_options_page' );

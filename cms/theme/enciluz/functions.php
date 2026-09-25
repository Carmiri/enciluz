<?php
/**
 * Tema ENCILUZ.
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/sections.php';

add_action('after_setup_theme', static function (): void {
	add_theme_support('editor-styles');
	add_editor_style('assets/css/theme.css');
	add_theme_support('custom-logo');
	remove_theme_support('core-block-patterns');
});

add_action('wp_enqueue_scripts', static function (): void {
	$css = get_theme_file_path('assets/css/theme.css');
	wp_enqueue_style('enciluz', get_theme_file_uri('assets/css/theme.css'), [], (string) filemtime($css));
});

add_action('init', static function (): void {
	register_block_pattern_category('enciluz', ['label' => 'Secciones ENCILUZ']);

	register_block_style('core/image', ['name' => 'arco', 'label' => 'Arco']);
	register_block_style('core/group', ['name' => 'tarjeta', 'label' => 'Tarjeta']);
	register_block_style('core/button', ['name' => 'oscuro', 'label' => 'Azul oscuro']);
});

// Sin patrones remotos del directorio de WordPress (menos peticiones externas).
add_filter('should_load_remote_block_patterns', '__return_false');

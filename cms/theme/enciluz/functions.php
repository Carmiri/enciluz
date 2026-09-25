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

// Sin script de emojis: JS innecesario y bloqueado por la CSP.
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
add_filter('emoji_svg_url', '__return_false');

// Meta descripción: usa el «Extracto» de cada página (editable en el panel lateral del editor).
add_action('wp_head', static function (): void {
	$id = is_front_page() ? (int) get_option('page_on_front') : (is_singular() ? get_queried_object_id() : 0);
	$text = $id ? trim((string) get_post_field('post_excerpt', $id)) : '';
	if ('' === $text) {
		$text = (string) get_bloginfo('description');
	}
	printf("<meta name=\"description\" content=\"%s\">\n", esc_attr(wp_trim_words($text, 30, '…')));
}, 1);

add_action('init', static fn() => add_post_type_support('page', 'excerpt'));

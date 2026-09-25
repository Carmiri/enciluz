<?php
/**
 * Endurecimiento de WordPress. Cada bloque está cubierto por cms/tests/security.sh.
 */
defined('ABSPATH') || exit;

// XML-RPC: sin métodos ni pingbacks.
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', '__return_empty_array');
add_filter('pings_open', '__return_false');
add_filter('wp_headers', static function (array $headers): array {
	unset($headers['X-Pingback']);
	return $headers;
});

// Usuarios: la API REST de usuarios solo existe para sesiones iniciadas.
add_filter('rest_endpoints', static function (array $endpoints): array {
	if (is_user_logged_in()) {
		return $endpoints;
	}
	foreach (array_keys($endpoints) as $route) {
		if (str_starts_with($route, '/wp/v2/users')) {
			unset($endpoints[$route]);
		}
	}
	return $endpoints;
});

// Usuarios: sin archivos de autor ni ?author=N.
add_action('template_redirect', static function (): void {
	if (is_author() || isset($_GET['author'])) {
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
	}
}, 1);
add_filter('author_link', static fn() => home_url('/'));
add_filter('wp_sitemaps_add_provider', static fn($provider, $name) => 'users' === $name ? false : $provider, 10, 2);

// Huellas de versión.
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
$enciluz_strip_ver = static function (string $src): string {
	if (str_contains($src, 'ver=' . get_bloginfo('version'))) {
		$src = remove_query_arg('ver', $src);
		$src = add_query_arg('ver', substr(md5(get_bloginfo('version') . wp_salt('nonce')), 0, 8), $src);
	}
	return $src;
};
add_filter('style_loader_src', $enciluz_strip_ver, 9999);
add_filter('script_loader_src', $enciluz_strip_ver, 9999);
add_filter('script_module_loader_src', $enciluz_strip_ver, 9999);

// Subidas: solo imágenes web y PDF.
add_filter('upload_mimes', static fn(): array => [
	'jpg|jpeg|jpe' => 'image/jpeg',
	'png'          => 'image/png',
	'webp'         => 'image/webp',
	'pdf'          => 'application/pdf',
], 9999);

// Comentarios desactivados en todo el sitio (no se usan).
add_filter('comments_open', '__return_false', 9999);
add_action('admin_menu', static fn() => remove_menu_page('edit-comments.php'));

<?php
/**
 * Generadores de marcado de bloques nativos (Gutenberg).
 *
 * Los usan los patrones del tema y el script de siembra, para que el contenido
 * inicial y las secciones prearmadas sean idénticos y 100 % editables.
 * Cada función devuelve el marcado serializado exactamente como lo guarda el editor.
 */
defined('ABSPATH') || exit;

if (!function_exists('enciluz_b')) {

	/** Comentario de bloque con atributos JSON opcionales. */
	function enciluz_b(string $name, array $attrs, string $html): string {
		$json = $attrs ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
		return "<!-- wp:{$name}{$json} -->\n{$html}\n<!-- /wp:{$name} -->\n";
	}

	function enciluz_class(array $classes): string {
		return implode(' ', array_filter($classes));
	}

	function enciluz_p(string $text, string $class = ''): string {
		$attrs = $class ? ['className' => $class] : [];
		$cls   = $class ? ' class="' . esc_attr($class) . '"' : '';
		return enciluz_b('paragraph', $attrs, "<p{$cls}>" . esc_html($text) . '</p>');
	}

	function enciluz_h(string $text, int $level = 2, string $class = ''): string {
		$attrs = [];
		if (2 !== $level) {
			$attrs['level'] = $level;
		}
		if ($class) {
			$attrs['className'] = $class;
		}
		$cls = enciluz_class(['wp-block-heading', $class]);
		return enciluz_b('heading', $attrs, "<h{$level} class=\"{$cls}\">" . esc_html($text) . "</h{$level}>");
	}

	/**
	 * Grupo. $opts: tag (div|section), class, align (wide|full), bg (slug de color),
	 * layout (constrained|grid|flex), min (ancho mínimo de columna en grid), anchor.
	 */
	function enciluz_group(string $inner, array $opts = []): string {
		$tag   = $opts['tag'] ?? 'div';
		$attrs = [];
		if ('div' !== $tag) {
			$attrs['tagName'] = $tag;
		}
		if (!empty($opts['anchor'])) {
			$attrs['anchor'] = $opts['anchor'];
		}
		if (!empty($opts['align'])) {
			$attrs['align'] = $opts['align'];
		}
		if (!empty($opts['class'])) {
			$attrs['className'] = $opts['class'];
		}
		if (!empty($opts['bg'])) {
			$attrs['backgroundColor'] = $opts['bg'];
		}
		$layout = $opts['layout'] ?? 'constrained';
		if ('grid' === $layout) {
			$attrs['layout'] = ['type' => 'grid', 'minimumColumnWidth' => $opts['min'] ?? '16rem'];
		} elseif ('flex' === $layout) {
			$attrs['layout'] = ['type' => 'flex', 'flexWrap' => 'wrap'];
		} else {
			$attrs['layout'] = ['type' => 'constrained'];
		}
		$cls = enciluz_class([
			'wp-block-group',
			!empty($opts['align']) ? 'align' . $opts['align'] : '',
			$opts['class'] ?? '',
			!empty($opts['bg']) ? "has-{$opts['bg']}-background-color has-background" : '',
		]);
		$id = !empty($opts['anchor']) ? ' id="' . esc_attr($opts['anchor']) . '"' : '';
		return enciluz_b('group', $attrs, "<{$tag}{$id} class=\"{$cls}\">\n{$inner}</{$tag}>");
	}

	/** Columnas. $cols = [ [inner, width%|null], ... ] */
	function enciluz_columns(array $cols, string $class = '', string $align = ''): string {
		$attrs = [];
		if ($align) {
			$attrs['align'] = $align;
		}
		if ($class) {
			$attrs['className'] = $class;
		}
		$html = '';
		foreach ($cols as [$inner, $width]) {
			$cattrs = $width ? ['width' => $width] : [];
			$style  = $width ? ' style="flex-basis:' . esc_attr($width) . '"' : '';
			$html  .= enciluz_b('column', $cattrs, "<div class=\"wp-block-column\"{$style}>\n{$inner}</div>");
		}
		$cls = enciluz_class(['wp-block-columns', $align ? "align{$align}" : '', $class]);
		return enciluz_b('columns', $attrs, "<div class=\"{$cls}\">\n{$html}</div>");
	}

	/** Imagen. $id = adjunto de la biblioteca (0 si es un recurso del tema). */
	function enciluz_img(string $url, string $alt, int $id = 0, string $class = ''): string {
		$attrs = [];
		if ($id) {
			$attrs['id'] = $id;
		}
		$attrs['sizeSlug']        = 'large';
		$attrs['linkDestination'] = 'none';
		if ($class) {
			$attrs['className'] = $class;
		}
		$imgcls = $id ? ' class="wp-image-' . $id . '"' : '';
		$cls    = enciluz_class(['wp-block-image', 'size-large', $class]);
		return enciluz_b('image', $attrs, "<figure class=\"{$cls}\"><img src=\"" . esc_url($url) . '" alt="' . esc_attr($alt) . "\"{$imgcls}/></figure>");
	}

	/** Botones. $items = [ [texto, url, estilo ('', 'oscuro', 'outline')], ... ] */
	function enciluz_buttons(array $items): string {
		$html = '';
		foreach ($items as $item) {
			[$text, $url] = $item;
			$style = $item[2] ?? '';
			$attrs = $style ? ['className' => "is-style-{$style}"] : [];
			$cls   = enciluz_class(['wp-block-button', $style ? "is-style-{$style}" : '']);
			$html .= enciluz_b('button', $attrs, "<div class=\"{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url($url) . '">' . esc_html($text) . '</a></div>');
		}
		return enciluz_b('buttons', [], "<div class=\"wp-block-buttons\">\n{$html}</div>");
	}

	function enciluz_list(array $items, string $class = ''): string {
		$html = '';
		foreach ($items as $item) {
			$html .= enciluz_b('list-item', [], '<li>' . esc_html($item) . '</li>');
		}
		$attrs = $class ? ['className' => $class] : [];
		$cls   = enciluz_class(['wp-block-list', $class]);
		return enciluz_b('list', $attrs, "<ul class=\"{$cls}\">\n{$html}</ul>");
	}

	/** Lista de enlaces. $items = [ [texto, url], ... ] */
	function enciluz_link_list(array $items, string $class = ''): string {
		$html = '';
		foreach ($items as [$text, $url]) {
			$html .= enciluz_b('list-item', [], '<li><a href="' . esc_url($url, ['https', 'http', 'mailto', 'tel']) . '">' . esc_html($text) . '</a></li>');
		}
		$attrs = $class ? ['className' => $class] : [];
		$cls   = enciluz_class(['wp-block-list', $class]);
		return enciluz_b('list', $attrs, "<ul class=\"{$cls}\">\n{$html}</ul>");
	}

	/** Párrafo con enlace (para correos, teléfonos, WhatsApp). */
	function enciluz_link_p(string $label, string $text, string $href, string $class = ''): string {
		$attrs = $class ? ['className' => $class] : [];
		$cls   = $class ? ' class="' . esc_attr($class) . '"' : '';
		$lbl   = $label ? '<strong>' . esc_html($label) . '</strong> ' : '';
		return enciluz_b('paragraph', $attrs, "<p{$cls}>{$lbl}<a href=\"" . esc_url($href, ['https', 'http', 'mailto', 'tel']) . '">' . esc_html($text) . '</a></p>');
	}

	function enciluz_theme_asset(string $path): string {
		return get_theme_file_uri("assets/{$path}");
	}
}

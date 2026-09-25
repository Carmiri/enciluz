<?php
/**
 * Secciones del sitio construidas con bloques nativos.
 * Usadas por los patrones (con textos de ejemplo) y por la siembra (con el contenido real).
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/blocks.php';

if (!function_exists('enciluz_section_hero')) {

	/** $d: title, text, image, image_id, alt, buttons[[t,u,s]] */
	function enciluz_section_hero(array $d): string {
		$left = enciluz_h($d['title'], 1, 'enciluz-hero__titulo')
			. enciluz_p($d['text'], 'enciluz-hero__texto')
			. (!empty($d['buttons']) ? enciluz_buttons($d['buttons']) : '');
		$right = enciluz_img($d['image'], $d['alt'] ?? '', (int) ($d['image_id'] ?? 0), 'is-style-arco enciluz-hero__imagen');
		return enciluz_group(
			enciluz_columns([[$left, '55%'], [$right, '45%']], 'are-vertically-centered enciluz-hero__cols', 'wide'),
			['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-hero']
		);
	}

	/** Encabezado de página interna: título + bajada. */
	function enciluz_section_intro(string $title, string $text): string {
		return enciluz_group(
			enciluz_h($title, 1) . enciluz_p($text, 'enciluz-lead'),
			['tag' => 'section', 'align' => 'full', 'bg' => 'celeste', 'class' => 'enciluz-intro']
		);
	}

	/** $stats: [[value, label]] */
	function enciluz_section_stats(array $stats, string $title = 'Nuestro impacto'): string {
		$items = '';
		foreach ($stats as [$value, $label]) {
			$items .= enciluz_group(enciluz_p($value, 'enciluz-cifra__valor') . enciluz_p($label, 'enciluz-cifra__texto'), ['class' => 'enciluz-cifra']);
		}
		return enciluz_group(
			enciluz_h($title, 2, 'screen-reader-text') . enciluz_group($items, ['align' => 'wide', 'layout' => 'grid', 'min' => '12rem', 'class' => 'enciluz-cifras']),
			['tag' => 'section', 'align' => 'full', 'bg' => 'celeste', 'class' => 'enciluz-banda']
		);
	}

	/** Título + texto de apertura de una sección. */
	function enciluz_heading_block(string $title, string $text = ''): string {
		return enciluz_h($title) . ($text ? enciluz_p($text, 'enciluz-lead') : '');
	}

	/** $sectors: [[icon, title, summary]] */
	function enciluz_section_sectors(array $sectors, string $title, string $text = '', string $bg = ''): string {
		$cards = '';
		foreach ($sectors as [$icon, $name, $summary]) {
			$cards .= enciluz_group(
				enciluz_img(enciluz_theme_asset("icons/{$icon}.svg"), '', 0, 'enciluz-icono')
				. enciluz_h($name, 3) . enciluz_p($summary),
				['class' => 'enciluz-sector']
			);
		}
		return enciluz_group(
			enciluz_heading_block($title, $text)
			. enciluz_group($cards, ['align' => 'wide', 'layout' => 'grid', 'min' => '17rem']),
			['tag' => 'section', 'align' => 'full', 'bg' => $bg, 'class' => 'enciluz-seccion']
		);
	}

	/** Una tarjeta de programa. $p: slug, title, summary, image, image_id, states[], highlight, link */
	function enciluz_program_card(array $p): string {
		$inner = enciluz_img($p['image'], $p['alt'] ?? '', (int) ($p['image_id'] ?? 0), 'is-style-arco');
		$inner .= enciluz_h($p['title'], 3);
		if (!empty($p['highlight'])) {
			$inner .= enciluz_p($p['highlight'], 'enciluz-destacado');
		}
		$inner .= enciluz_p($p['summary']);
		if (!empty($p['states'])) {
			$inner .= enciluz_p('Dónde: ' . implode(', ', $p['states']), 'enciluz-estados');
		}
		if (!empty($p['link'])) {
			$inner .= enciluz_link_p('', 'Conoce el programa', $p['link'], 'enciluz-mas');
		}
		return enciluz_group($inner, ['class' => 'is-style-tarjeta enciluz-programa', 'anchor' => $p['slug'] ?? '']);
	}

	function enciluz_section_programs(array $programs, string $title, string $text = '', array $buttons = []): string {
		$cards = '';
		foreach ($programs as $p) {
			$cards .= enciluz_program_card($p);
		}
		return enciluz_group(
			enciluz_heading_block($title, $text)
			. enciluz_group($cards, ['align' => 'wide', 'layout' => 'grid', 'min' => '19rem'])
			. ($buttons ? enciluz_buttons($buttons) : ''),
			['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion']
		);
	}

	/** Programa en detalle (página Qué hacemos): imagen + texto largo. */
	function enciluz_program_detail(array $p, bool $flip = false): string {
		$text = enciluz_h($p['title'], 3);
		if (!empty($p['highlight'])) {
			$text .= enciluz_p($p['highlight'], 'enciluz-destacado');
		}
		foreach ((array) $p['body'] as $para) {
			$text .= enciluz_p($para);
		}
		if (!empty($p['states'])) {
			$text .= enciluz_p('Dónde: ' . implode(', ', $p['states']), 'enciluz-estados');
		}
		$img  = enciluz_img($p['image'], $p['alt'] ?? '', (int) ($p['image_id'] ?? 0), 'is-style-arco');
		$cols = $flip ? [[$text, '58%'], [$img, '42%']] : [[$img, '42%'], [$text, '58%']];
		return enciluz_group(enciluz_columns($cols, 'are-vertically-centered', 'wide'), ['class' => 'enciluz-programa-detalle', 'anchor' => $p['slug'] ?? '']);
	}

	/** $values: [[title, text]] */
	function enciluz_section_values(array $values, string $title, string $text = ''): string {
		$items = '';
		foreach ($values as [$name, $desc]) {
			$items .= enciluz_group(enciluz_h($name, 3) . enciluz_p($desc), ['class' => 'enciluz-valor']);
		}
		return enciluz_group(
			enciluz_heading_block($title, $text) . enciluz_group($items, ['align' => 'wide', 'layout' => 'grid', 'min' => '15rem']),
			['tag' => 'section', 'align' => 'full', 'bg' => 'celeste', 'class' => 'enciluz-seccion']
		);
	}

	/** $team: [[name, role]] */
	function enciluz_section_team(array $team, string $title, string $text = ''): string {
		$items = '';
		foreach ($team as [$name, $role]) {
			$items .= enciluz_group(enciluz_p($name, 'enciluz-persona__nombre') . enciluz_p($role, 'enciluz-persona__cargo'), ['class' => 'enciluz-persona']);
		}
		return enciluz_group(
			enciluz_heading_block($title, $text) . enciluz_group($items, ['align' => 'wide', 'layout' => 'grid', 'min' => '15rem']),
			['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion']
		);
	}

	/** $groups: [ title => [ [name, url], ... ] ] */
	function enciluz_section_allies(array $groups, string $title, string $text = ''): string {
		$cols = [];
		foreach ($groups as $group_title => $allies) {
			$inner = enciluz_h($group_title, 3);
			foreach ($allies as [$name, $url]) {
				$inner .= $url ? enciluz_link_p('', $name, $url, 'enciluz-aliado') : enciluz_p($name, 'enciluz-aliado');
			}
			$cols[] = [$inner, null];
		}
		return enciluz_group(
			enciluz_heading_block($title, $text) . enciluz_columns($cols, '', 'wide'),
			['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion enciluz-aliados']
		);
	}

	/** $groups: [ title => [doc titles] ] */
	function enciluz_section_documents(array $groups, string $title, string $text = ''): string {
		$cols = [];
		foreach ($groups as $group_title => $docs) {
			$cols[] = [enciluz_h($group_title, 3) . enciluz_list($docs, 'enciluz-documentos'), null];
		}
		return enciluz_group(
			enciluz_heading_block($title, $text) . enciluz_columns($cols, '', 'wide'),
			['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion']
		);
	}

	/** $coverage: [[state, municipalities[], ethnicities[]]] */
	function enciluz_section_coverage(array $coverage, string $title, string $text = ''): string {
		$items = '';
		foreach ($coverage as [$state, $munis, $ethnic]) {
			$inner = enciluz_h($state, 3) . enciluz_p(implode(', ', $munis));
			if ($ethnic) {
				$inner .= enciluz_p('Pueblos indígenas: ' . implode(', ', $ethnic), 'enciluz-estados');
			}
			$items .= enciluz_group($inner, ['class' => 'enciluz-estado']);
		}
		return enciluz_group(
			enciluz_heading_block($title, $text) . enciluz_group($items, ['align' => 'wide', 'layout' => 'grid', 'min' => '15rem']),
			['tag' => 'section', 'align' => 'full', 'bg' => 'celeste', 'class' => 'enciluz-seccion', 'anchor' => 'cobertura']
		);
	}

	/** Texto con imagen al lado (historia, PEAS…). $paras: string[] */
	function enciluz_section_text_image(string $title, array $paras, string $image, int $image_id = 0, string $alt = '', string $bg = ''): string {
		$text = enciluz_h($title);
		foreach ($paras as $para) {
			$text .= enciluz_p($para);
		}
		$img = enciluz_img($image, $alt, $image_id, 'is-style-arco');
		return enciluz_group(
			enciluz_columns([[$text, '58%'], [$img, '42%']], 'are-vertically-centered', 'wide'),
			['tag' => 'section', 'align' => 'full', 'bg' => $bg, 'class' => 'enciluz-seccion']
		);
	}

	/** Bloque de texto simple. $paras: string[] */
	function enciluz_section_text(string $title, array $paras, string $bg = '', string $anchor = ''): string {
		$inner = enciluz_h($title);
		foreach ($paras as $para) {
			$inner .= enciluz_p($para);
		}
		return enciluz_group($inner, ['tag' => 'section', 'align' => 'full', 'bg' => $bg, 'class' => 'enciluz-seccion', 'anchor' => $anchor]);
	}

	/** $cards: [[title, text]] en rejilla */
	function enciluz_section_cards(string $title, string $text, array $cards, string $bg = ''): string {
		$items = '';
		foreach ($cards as [$name, $desc]) {
			$items .= enciluz_group(enciluz_h($name, 3) . enciluz_p($desc), ['class' => 'is-style-tarjeta']);
		}
		return enciluz_group(
			enciluz_heading_block($title, $text) . enciluz_group($items, ['align' => 'wide', 'layout' => 'grid', 'min' => '15rem']),
			['tag' => 'section', 'align' => 'full', 'bg' => $bg, 'class' => 'enciluz-seccion']
		);
	}

	function enciluz_section_cta(string $title, string $text, array $buttons): string {
		return enciluz_group(
			enciluz_h($title) . enciluz_p($text, 'enciluz-lead') . enciluz_buttons($buttons),
			['tag' => 'section', 'align' => 'full', 'bg' => 'amarillo', 'class' => 'enciluz-cta']
		);
	}

	/** $d: email, phones[], whatsapp, address, maps */
	function enciluz_section_contact(array $d, string $title = 'Escríbenos o llámanos'): string {
		$inner = enciluz_h($title)
			. enciluz_link_p('Correo:', $d['email'], 'mailto:' . $d['email']);
		foreach ($d['phones'] as $phone) {
			$inner .= enciluz_link_p('Teléfono:', $phone, 'tel:+58' . ltrim(preg_replace('/\D/', '', $phone), '0'));
		}
		$inner .= enciluz_link_p('WhatsApp:', 'Escríbenos por WhatsApp', 'https://wa.me/' . $d['whatsapp'])
			. enciluz_p('Dirección: ' . $d['address'])
			. enciluz_link_p('', 'Ver ubicación en el mapa', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('La Dolorita, Petare, Miranda, Venezuela'));
		return enciluz_group($inner, ['class' => 'is-style-tarjeta enciluz-contacto']);
	}
}

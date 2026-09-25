<?php
/**
 * Plugin Name: ENCILUZ Core
 * Description: Seguridad, cabeceras y funciones propias del sitio de la Fundación Enciende una Luz.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

foreach (['hardening', 'headers', 'contact'] as $module) {
	$file = __DIR__ . "/enciluz/{$module}.php";
	if (is_readable($file)) {
		require_once $file;
	}
}

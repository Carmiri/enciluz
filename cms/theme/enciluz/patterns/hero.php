<?php
/**
 * Title: Portada con imagen en arco
 * Slug: enciluz/hero
 * Categories: enciluz
 */
echo enciluz_section_hero(['title' => 'Escribe aquí un titular breve', 'text' => 'Una o dos frases que expliquen qué hacemos y para quién.', 'image' => enciluz_theme_asset('images/ejemplo.jpg'), 'alt' => '', 'buttons' => [['Conoce más', '/que-hacemos/'], ['Colabora', '/contacto/', 'outline']]]);

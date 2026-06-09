<?php
// includes/branding.php — Logo institucional IPP-UPTAG
// Renderiza el logo oficial. Detecta automáticamente el archivo presente en
// assets/img/ (acepta varios nombres/formatos). Si no hay ninguno, cae con
// elegancia al distintivo de texto actual (sin romper la vista).

if (!function_exists('rutaLogoIPP')) {
    // Devuelve la URL del logo (con versión para evitar caché) o '' si no existe.
    function rutaLogoIPP(): string
    {
        static $url = null;
        if ($url !== null) return $url;

        $candidatos = [
            'assets/img/logoipp.png',
            'assets/img/logo-ipp.svg',
            'assets/img/logo-ipp.png',
            'assets/img/logo.svg',
            'assets/img/logo.png',
        ];
        foreach ($candidatos as $rel) {
            $abs = BASE_PATH . '/' . $rel;
            if (is_file($abs)) {
                $url = url($rel) . '?v=' . filemtime($abs);
                return $url;
            }
        }
        return $url = '';
    }
}

if (!function_exists('logoIPP')) {
    function logoIPP(string $variante = 'nav'): string
    {
        $src = rutaLogoIPP();
        $alt = 'IPP-UPTAG — Instituto de Previsión del Profesorado';

        if ($variante === 'hero') {
            $clase    = 'brand-logo brand-logo--hero';
            $fallback = '<span class="brand-logo__fallback ipp-logo-big" aria-hidden="true"><span>IPP</span></span>';
        } else {
            $clase    = 'brand-logo brand-logo--nav';
            $fallback = '<span class="brand-logo__fallback nav-logo" aria-hidden="true">IPP</span>';
        }

        // Sin archivo de logo → arrancar directamente en modo fallback.
        if ($src === '') {
            return '<span class="' . $clase . ' is-fallback">' . $fallback . '</span>';
        }

        // Con archivo: si la imagen fallara al cargar, se muestra el fallback.
        return '<span class="' . $clase . '">'
             . '<img class="brand-logo__img" src="' . htmlspecialchars($src, ENT_QUOTES) . '" '
             . 'alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" '
             . 'onerror="this.closest(\'.brand-logo\').classList.add(\'is-fallback\')">'
             . $fallback
             . '</span>';
    }
}

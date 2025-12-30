<?php
/**
 * Configuration Tailwind CSS
 * Génère le script de configuration inline
 *
 * Style: Prototype Design System avec couleur ambassade
 */

function getTailwindConfig() {
    return <<<'JS'
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                // Couleurs primaires (ambassade - conservées)
                "primary": {
                    DEFAULT: "#0D5C46",
                    light: "#10816A",
                    dark: "#094536",
                },
                // Couleurs d'accent
                "accent": {
                    orange: "#F77F00",
                    green: "#009A44",
                },
                // Surfaces (style prototype)
                "surface": {
                    light: "#ffffff",
                    dark: "#1a2634",
                },
                // Backgrounds (style prototype)
                "background": {
                    light: "#f6f7f8",
                    dark: "#101822",
                },
                // Alias pour compatibilité
                "bg": {
                    light: "#f6f7f8",
                    dark: "#101822",
                },
                // Texte (style prototype)
                "text": {
                    main: "#111418",
                    primary: "#111418",
                    secondary: "#617289",
                },
                // Bordures
                "border": "#E5E7EB",
                // États
                "success": "#059669",
                "warning": "#D97706",
                "error": "#DC2626",
            },
            fontFamily: {
                "display": ["Playfair Display", "Georgia", "serif"],
                "body": ["DM Sans", "system-ui", "sans-serif"]
            },
            // Border radius agrandis (style prototype)
            borderRadius: {
                "DEFAULT": "1rem",
                "lg": "1.5rem",
                "xl": "2rem",
                "2xl": "2.5rem",
                "3xl": "3rem",
                "full": "9999px",
            },
            boxShadow: {
                'card': '0 1px 3px rgba(0,0,0,0.08)',
                'card-hover': '0 4px 12px rgba(0,0,0,0.1)',
                'button': '0 2px 4px rgba(13,92,70,0.2)',
                'xl': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
                '2xl': '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
            },
        },
    },
}
JS;
}


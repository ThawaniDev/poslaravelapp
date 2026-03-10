import preset from '../../../../vendor/filament/filament/tailwind.config.preset.js'

/** @type {import('tailwindcss').Config} */
export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                // Thawani brand tokens (available as bg-brand, text-brand-dark, etc.)
                brand: {
                    DEFAULT: '#FD8209',
                    light: '#FFE8CC',
                    dark: '#C2530A',
                },
                secondary: {
                    DEFAULT: '#FFBF0D',
                    light: '#FFF3CD',
                    dark: '#B45309',
                },
                warm: {
                    bg: '#F8F7F5',
                    dark: '#23190F',
                },
            },
            fontFamily: {
                sans: ['Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            borderRadius: {
                DEFAULT: '0.5rem',    // 8px — matches Flutter AppRadius.md
                lg: '0.75rem',        // 12px
                xl: '1rem',           // 16px
            },
        },
    },
}

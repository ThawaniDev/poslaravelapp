/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
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
        },
    },
    plugins: [],
};

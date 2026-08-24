import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Carmen Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Carmen Sans', ...defaultTheme.fontFamily.sans],
                body: ['GT Eesti Pro Display', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                nodo: {
                    50:  '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#FFE124',
                    500: '#e6c700',
                    600: '#ca9c00',
                    700: '#a07800',
                    800: '#7a5c00',
                    900: '#5c4500',
                },
                dark: {
                    DEFAULT: '#2E2D2C',
                    light:   '#3d3c3a',
                    nav:     '#2E2D2C',
                },
                cream: {
                    DEFAULT: '#F4F1EA',
                    dark:    '#E8E1D1',
                },
                plan: {
                    flex:    '#FFDD00',
                    pro:     '#D6E265',
                    daypass: '#EF7E88',
                    match:   '#864B95',
                },
            },
            borderRadius: {
                '4xl': '2rem',
                '5xl': '2.5rem',
            },
            animation: {
                'marquee':    'marquee 25s linear infinite',
                'marquee2':   'marquee2 25s linear infinite',
                'float':      'float 6s ease-in-out infinite',
                'pulse-slow': 'pulse 4s ease-in-out infinite',
                'spin-slow':  'spin 12s linear infinite',
            },
            keyframes: {
                marquee:  { '0%': { transform: 'translateX(0%)' },   '100%': { transform: 'translateX(-100%)' } },
                marquee2: { '0%': { transform: 'translateX(100%)' }, '100%': { transform: 'translateX(0%)' } },
                float:    { '0%,100%': { transform: 'translateY(0px)' }, '50%': { transform: 'translateY(-16px)' } },
            },
        },
    },

    plugins: [forms],
};

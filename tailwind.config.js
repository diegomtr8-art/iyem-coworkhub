import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Sistema de diseño «Editorial técnico» de Nódico.
 * Las reglas de uso viven en .claude/skills/nodico-design/SKILL.md
 */
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
                sans:    ['Carmen Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Carmen Sans', ...defaultTheme.fontFamily.sans],
                body:    ['GT Eesti Pro Display', 'Figtree', ...defaultTheme.fontFamily.sans],
                // Etiquetas técnicas: pila del sistema, sin peso de descarga.
                mono:    ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', ...defaultTheme.fontFamily.mono],
            },

            fontSize: {
                'display-xl': ['clamp(2.75rem, 8vw, 6.5rem)',     { lineHeight: '0.9',  letterSpacing: '-0.03em' }],
                'display-lg': ['clamp(2.5rem, 7vw, 5.5rem)',     { lineHeight: '0.9',  letterSpacing: '-0.02em' }],
                'display-md': ['clamp(2rem, 5vw, 3.5rem)',       { lineHeight: '0.95', letterSpacing: '-0.02em' }],
                'display-sm': ['clamp(1.5rem, 3vw, 2.25rem)',    { lineHeight: '1.05', letterSpacing: '-0.01em' }],
                'cuerpo-lg':  ['clamp(1.0625rem, 1.2vw, 1.25rem)', { lineHeight: '1.65' }],
                'cuerpo':     ['1rem',                            { lineHeight: '1.6' }],
                // 0.75rem con tracking amplio para las etiquetas mono numeradas.
                'etiqueta':   ['0.75rem',                         { lineHeight: '1', letterSpacing: '0.18em' }],
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
                // Fondo oscuro de mayor peso que `dark`: footer y hero.
                tinta: '#1A1918',
                dark: {
                    DEFAULT: '#2E2D2C',
                    light:   '#3d3c3a',
                    nav:     '#2E2D2C',
                    600:     '#3D3C3A',
                },
                cream: {
                    DEFAULT: '#F4F1EA',
                    50:      '#FAF8F3',
                    dark:    '#E8E1D1',
                    200:     '#E8E1D1',
                },
                plan: {
                    flex:    '#FFDD00',
                    pro:     '#D6E265',
                    daypass: '#EF7E88',
                    match:   '#864B95',
                },
                // Alias semánticos. OJO con el contraste (ver SKILL.md):
                // `lima` solo sobre oscuro; `morado` solo sobre claro.
                lima:   '#D6E265',
                morado: '#864B95',
                coral:  '#EF7E88',
            },

            borderRadius: {
                '4xl': '2rem',
                '5xl': '2.5rem',
            },

            boxShadow: {
                // Elevación suave: la superficie por defecto del sistema.
                'sombra-sm': '0 1px 2px rgba(46,45,44,.05), 0 4px 12px rgba(46,45,44,.05)',
                'sombra':    '0 2px 4px rgba(46,45,44,.04), 0 14px 34px rgba(46,45,44,.09)',
                'sombra-lg': '0 4px 8px rgba(46,45,44,.05), 0 28px 64px rgba(46,45,44,.14)',
                // Sólida desplazada: se reserva para acentos puntuales, no para todo.
                'dura-sm': '2px 2px 0 #2E2D2C',
                'dura':    '6px 6px 0 #2E2D2C',
                'dura-lg': '10px 10px 0 #2E2D2C',
                // Sombra en color, para tarjetas sobre fondo oscuro o crema.
                // La SKILL las documentaba desde el principio y no existían:
                // cualquier `shadow-dura-nodo` escrito hasta hoy no pintaba nada.
                'dura-nodo':  '6px 6px 0 #FFE124',
                'dura-lima':  '6px 6px 0 #D6E265',
                'dura-crema': '6px 6px 0 #F4F1EA',
            },

            transitionTimingFunction: {
                salida: 'cubic-bezier(.22, 1, .36, 1)',
                suave:  'cubic-bezier(.4, 0, .2, 1)',
            },

            animation: {
                'marquee':    'marquee 32s linear infinite',
                'marquee2':   'marquee2 32s linear infinite',
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

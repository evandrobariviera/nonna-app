import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Syne', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                nonna: {
                    purple: '#6A5ACD',
                    orange: '#FF8C00',
                    bg:     '#0c0c12',
                    s1:     '#111118',
                    s2:     '#17171f',
                    s3:     '#1e1e28',
                },
            },
        },
    },

    plugins: [forms],
};

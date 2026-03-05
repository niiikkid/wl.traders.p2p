import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        "./resources/**/*.js",
        "./node_modules/flowbite/**/*.js",
        'node_modules/flowbite/**/*.{js,jsx,ts,tsx}'

    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            /*blur: {
                xs: '2px',
            },
            borderRadius: {
                'menu': '1rem',
                'table': '1rem',
                'plate': '1rem',
                'alert': '1rem',
                'table-raw': '1rem',
            },*/
            /*colors: {
                gray: {
                    900: 'rgb(48,48,48)',
                    800: 'rgb(39, 39, 39)'
                },
                blue: {
                    600: 'rgb(34,142,93)',
                }
            }*/
        },
    },

    plugins: [forms, require('flowbite/plugin')],
};

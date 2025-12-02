/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './src/**/*.{js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                'aevov-primary': '#0ea5e9',
                'aevov-primary-dark': '#0284c7',
                'aevov-bg-dark': '#1a1a2e',
                'aevov-bg-card': '#16213e',
                'aevov-border': '#2d3748',
                'aevov-text': '#e2e8f0',
                'aevov-text-muted': '#94a3b8',
            },
        },
    },
    plugins: [],
};

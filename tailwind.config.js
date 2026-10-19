module.exports = {
    content: ['./templates/**/*.html.twig', './assets/**/*.js', './translations/messages.*.json'],
    theme: {
        extend: {
            colors: {
                primary: '#e82118', 'primary-d': '#c01711', secondary: '#ffcc07',
                'text-dark': '#203263', 'text-light': '#666', 'border-c': '#e5e7eb',
                'bg-light': '#f9fafb', success: '#10b981',
            },
            fontFamily: {
                display: ['"Bricolage Grotesque"', 'sans-serif'],
                sans: ['Archivo', 'sans-serif'],
            },
            boxShadow: {
                'sm-c': '0 1px 3px rgba(0,0,0,.08)',
                'md-c': '0 4px 12px rgba(0,0,0,.1)',
                'lg-c': '0 10px 30px rgba(0,0,0,.12)',
            },
            borderRadius: { 'sm-c': '8px', 'md-c': '12px', 'lg-c': '16px' },
        },
    },
};

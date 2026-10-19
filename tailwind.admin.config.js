/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./admin/**/*.php', './admin/assets/admin.js'],
  theme: {
    extend: {
      colors: {
        chef: {
          accent: 'var(--accent)',
          accentStrong: 'var(--accent-strong)',
          ink: 'var(--surface-page)',
          surface: 'var(--surface-card)',
          text: 'var(--text-primary)',
          muted: 'var(--text-muted)'
        }
      },
      fontFamily: {
        serif: ['Georgia', 'Cambria', 'Times New Roman', 'serif'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif']
      }
    }
  },
  plugins: []
};

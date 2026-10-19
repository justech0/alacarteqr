/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./hvk/admin/**/*.php', './hvk/admin/assets/admin.js'],
  theme: {
    extend: {
      colors: {
        hvk: {
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

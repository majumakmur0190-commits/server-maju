/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./pc/**/*.html",
    "./pc/**/*.js",
    "./android/**/*.html",
    "./android/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        primary: "#1e3a8a",
        secondary: "#1e40af",
        accent: "#f97316",
        dark: "#0f172a",
      },
    },
  },
  plugins: [],
}
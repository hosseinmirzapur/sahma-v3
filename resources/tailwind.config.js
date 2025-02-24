const colors = require('tailwindcss/colors')
const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  content: [
    './resources/requester/vue/**/*.vue',
    './resources/vue/**/*.vue',
    './resources/vue/app.css',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      letterSpacing: {
        tightest: '-.075em',
        tighter: '-.05em',
        tight: '-.025em',
        normal: '0',
        wide: '.025em',
        wider: '.35em',
        widest: '1em',
      },
      fontFamily: {
        'sans': ['IRANSans', ...defaultTheme.fontFamily.sans],
        'sans-original-digits': ['IRANSans-OriginalDigits', ...defaultTheme.fontFamily.sans],
        'plate': ['Traffic', 'IRANSans', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        pagination: '#EFEFEF',
        // primary: '#2A3875',
        primary: '#ab8146',
        primaryDark: '#1E2855',
        dark: '#00168B',
        secondPrimary: '#1E2855',
        primaryText: '#27272A',
        voice: '#153169',
        pdf: '#C3A63B',
        video: '#3E8A75',
        brandBlue: {
          '50': '#e8f5ff',
          '100': '#bbdbfa',
          '200': '#95bfed',
          '300': '#73a2dd',
          '400': '#5584c9',
          '500': '#3c67af',
          '600': '#22418c',
          '700': '#173973',
          '800': '#0f2d57',
          '900': '#0b234b',
          '950': '#061226',
        },
        brandRed: {
          '50': '#fef3ed',
          '100': '#fbdbcd',
          '200': '#f9c1ac',
          '300': '#f6a38c',
          '400': '#f3826b',
          '500': '#f15e4b',
          '600': '#ee412a',
          '700': '#a62a19',
          '800': '#671a10',
          '900': '#5e1208',
        },
        gray: colors.zinc,
        legacy: {
          cyan: "#00ffd1",
          blue: {
            A: "#bbbedc",
            B: "#8196ea",
            C: "#353dbc",
            D: "#21238f",
            E: "#191a5a",
            ultra: "#0635c9"
          },
        },
      },
      boxShadow: {
        cardUni: '0px 4px 20px 0px rgba(53, 61, 188, 0.10)',
        btnUni: '0px 15px 40px 0px rgba(53, 61, 188, 0.25)',
        dropDownUni : '0 5px 14px 2px rgba(0, 0, 0, 0.2)',
        cardLetter : '0px 10px 15px 0px rgba(0, 0, 0, 0.10);',
      },
    },
  },
  plugins: [require("@tailwindcss/forms")],
}


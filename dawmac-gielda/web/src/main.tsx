import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
// Fonty serwowane z naszego serwera, bez Google Fonts —
// adres IP użytkownika nie trafia do Google (RODO).
import '@fontsource/archivo/latin-ext-700.css'
import '@fontsource/archivo/latin-ext-800.css'
import '@fontsource/archivo/latin-700.css'
import '@fontsource/archivo/latin-800.css'
import '@fontsource/hanken-grotesk/latin-ext.css'
import '@fontsource/hanken-grotesk/latin-ext-500.css'
import '@fontsource/hanken-grotesk/latin-ext-700.css'
import '@fontsource/hanken-grotesk/latin.css'
import '@fontsource/hanken-grotesk/latin-500.css'
import '@fontsource/hanken-grotesk/latin-700.css'
import './index.css'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`).catch(() => {})
}

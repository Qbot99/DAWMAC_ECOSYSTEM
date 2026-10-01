import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
// Fonty DAWMAC (jak na forged.dawmacpolska.pl) serwowane z naszego serwera,
// bez Google Fonts — adres IP użytkownika nie trafia do Google (RODO).
import '@fontsource/archivo/latin-ext-800.css'
import '@fontsource/archivo/latin-ext-800-italic.css'
import '@fontsource/archivo/latin-ext-900-italic.css'
import '@fontsource/archivo/latin-800.css'
import '@fontsource/archivo/latin-800-italic.css'
import '@fontsource/archivo/latin-900-italic.css'
import '@fontsource/hanken-grotesk/latin-ext.css'
import '@fontsource/hanken-grotesk/latin-ext-500.css'
import '@fontsource/hanken-grotesk/latin-ext-700.css'
import '@fontsource/hanken-grotesk/latin.css'
import '@fontsource/hanken-grotesk/latin-500.css'
import '@fontsource/hanken-grotesk/latin-700.css'
import '@fontsource/space-mono/latin-ext-700.css'
import '@fontsource/space-mono/latin-700.css'
import './index.css'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  navigator.serviceWorker.register('/sw.js').catch(() => {})
}

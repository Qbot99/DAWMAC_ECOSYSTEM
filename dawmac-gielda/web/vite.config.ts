import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Lokalnie API chodzi na wbudowanym serwerze PHP:
//   php -S 127.0.0.1:8099 tests/dev-router.php   (z katalogu dawmac-gielda)
// Na produkcji front i API stoją na tej samej domenie, więc proxy jest tylko do dev.
// Na dawmac.pl giełda stoi w podkatalogu: npm run build -- --base=/gielda/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8099',
      '/uploads': 'http://127.0.0.1:8099',
    },
  },
})

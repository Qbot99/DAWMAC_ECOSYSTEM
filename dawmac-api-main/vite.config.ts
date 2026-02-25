import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { viteStaticCopy } from 'vite-plugin-static-copy'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    viteStaticCopy({
      targets: [
        {
          src: 'api/*',    // your folder with PHP files
          dest: 'api'      // this will be `dist/api`
        },
        {
          src:'vendor/*', // your folder with vendor files
          dest: 'vendor'  // this will be `dist/vendor`
        },
        {
          src: 'composer.json', // copy composer.json to the root of dist
          dest: ''              // this will be `dist/composer.json`
        },
        {
          src: 'composer.lock', // copy composer.lock to the root of dist
          dest: ''              // this will be `dist/composer.lock`
        }
      ]
    })
  ],
})

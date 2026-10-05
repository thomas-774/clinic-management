import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: { port: 5173, strictPort: true },
  test: {
    environment: 'jsdom',
    globals: true,
    pool: 'threads', // the default forks pool times out on Windows paths containing spaces
    setupFiles: './src/test/setup.js',
  },
})

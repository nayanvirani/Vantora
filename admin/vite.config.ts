import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Built assets are served from Laravel's public/app/ so the embedded admin
// UI and the API live behind the same Railway service and origin.
export default defineConfig({
  base: '/app/',
  plugins: [react()],
  build: {
    outDir: 'dist',
  },
})

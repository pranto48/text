import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api.php': {
        target: 'http://localhost:2266', // Your PHP backend running on port 2266
        changeOrigin: true,
        rewrite: (path) => path, // No rewrite needed, just pass the path as is
      },
    },
  },
});
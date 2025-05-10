import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      input: 'resources/js/app.tsx',
      ssr: 'resources/js/ssr.tsx',
      refresh: true,
    }),
    react(),
  ],
  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
  },
  ssr: {
    noExternal: ['@inertiajs/server'], // Optional for Inertia SSR
  },
});

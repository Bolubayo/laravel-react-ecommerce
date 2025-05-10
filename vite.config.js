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
        noExternal: ['@inertiajs/server'], // Add this if using Inertia SSR
        outDir: 'bootstrap/ssr', // Separate SSR build output
    },
});

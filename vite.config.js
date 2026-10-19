import { defineConfig } from 'vite';
import vue2 from '@vitejs/plugin-vue2';
import vue3 from '@vitejs/plugin-vue';
import { resolve } from 'path';

const vueVersion = process.env.VUE_VERSION || '2';
const isVue2 = vueVersion === '2';
const distSubdir = isVue2 ? 'v5' : 'v6';
const buildType = process.env.BUILD_TYPE || 'all';

// Custom plugin to resolve version-specific component overrides
const versionResolverPlugin = () => ({
    name: 'version-resolver',
    enforce: 'pre',
    async resolveId(source, importer) {
        if (source.endsWith('.vue') && importer) {
            const versionExt = isVue2 ? '.v5.vue' : '.v6.vue';
            const versionedPath = source.replace(/\.vue$/, versionExt);
            const resolution = await this.resolve(versionedPath, importer, { skipSelf: true });
            if (resolution) return resolution;
        }
        return null;
    }
});

// Configure inputs based on build type
const inputs = {};
if (buildType === 'all' || buildType === 'js') {
    inputs.cp = resolve(__dirname, 'resources/js/cp.js');
}
if (buildType === 'all' || buildType === 'css') {
    inputs['cp-css'] = resolve(__dirname, 'resources/css/cp.css');
}

export default defineConfig({
    plugins: [
        versionResolverPlugin(),
        isVue2 ? vue2() : vue3(),
    ],
    build: {
        outDir: `dist/${distSubdir}`,
        emptyOutDir: buildType === 'all' || buildType === 'js', // Only empty on JS/Full pass
        rollupOptions: {
            input: inputs,
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.css')) {
                        return 'css/cp.css';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
                format: buildType === 'css' ? 'es' : 'iife',
                inlineDynamicImports: buildType === 'js',
                globals: {
                    vue: 'Vue',
                },
            },
            external: buildType === 'css' ? [] : ['vue'],
        },
    },
});

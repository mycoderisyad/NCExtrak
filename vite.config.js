import { defineConfig } from 'vite'

export default defineConfig({
	resolve: {
		alias: {
			path: 'path-browserify',
		},
	},
	build: {
		emptyOutDir: false,
		outDir: 'js',
		rollupOptions: {
			input: 'src/main.ts',
			output: {
				entryFileNames: 'ncextrak-main.js',
				assetFileNames: 'ncextrak-[name][extname]',
				format: 'iife',
				inlineDynamicImports: true,
				name: 'NCExtrak',
			},
		},
		target: 'es2022',
	},
})

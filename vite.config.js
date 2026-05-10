import { defineConfig } from 'vite'

export default defineConfig({
	build: {
		emptyOutDir: false,
		outDir: 'js',
		rollupOptions: {
			input: 'src/main.ts',
			output: {
				entryFileNames: 'ncextrak-main.js',
				chunkFileNames: 'ncextrak-[name].js',
				assetFileNames: 'ncextrak-[name][extname]',
				format: 'es',
			},
		},
		target: 'es2022',
	},
})

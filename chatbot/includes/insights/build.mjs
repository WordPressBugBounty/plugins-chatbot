/**
 * Build script: bundles the tracker (loader, recorder, collector) and the admin app.
 * Output goes to assets/build/ (not committed). Run `npm run build` before testing or zipping.
 */
import * as esbuild from 'esbuild';

const watch = process.argv.includes( '--watch' );
const common = { bundle: true, minify: true, sourcemap: false, legalComments: 'none', logLevel: 'info', target: [ 'es2018' ] };

const builds = [
	{ ...common, entryPoints: [ 'assets/src/tracker/loader.js' ], outfile: 'assets/build/loader.js', format: 'iife' },
	{ ...common, entryPoints: [ 'assets/src/tracker/recorder.js' ], outfile: 'assets/build/recorder.js', format: 'iife' },
	{ ...common, entryPoints: [ 'assets/src/tracker/collector.js' ], outfile: 'assets/build/collector.js', format: 'iife' },
	{
		...common,
		entryPoints: { admin: 'assets/src/admin/app.jsx' },
		outdir: 'assets/build',
		format: 'iife',
		jsxFactory: 'wp.element.createElement',
		jsxFragment: 'wp.element.Fragment',
		loader: { '.js': 'jsx' },
	},
];

for ( const b of builds ) {
	if ( watch ) {
		const ctx = await esbuild.context( b );
		await ctx.watch();
	} else {
		await esbuild.build( b );
	}
}

/**
 * Sync the WPBot Asset Cache core module into WPBot Free.
 *
 *   npm run sync:asset-optimizer -- --core <tag|sha>
 *
 * Exports exactly that git ref from the module's own checkout (default: a
 * sibling folder, override with ASSET_OPTIMIZER_DIR), builds its assets if it
 * has a build step, swaps the placeholder text domain for "chatbot", replaces
 * includes/asset-optimizer/ and writes .sync.json (repo, ref, commit, version,
 * file hashes). The synced folder is never edited here; change the module
 * repo, tag it, and sync again. WPBot Free ships the core only (the Pro
 * module never leaves WPBot Pro).
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const crypto = require( 'crypto' );
const { execFileSync } = require( 'child_process' );

const ROOT = path.resolve( __dirname, '..' );
const TEXT_DOMAIN_FROM = 'qcao-textdomain';
const TEXT_DOMAIN_TO = 'chatbot';

const MODULES = [
	{ arg: 'core', repo: 'qcld/wpbot-asset-optimizer', env: 'ASSET_OPTIMIZER_DIR', sibling: 'wpbot-asset-optimizer', target: 'includes/asset-optimizer', main: 'asset-optimizer.php' },
];

// Never shipped: tooling, tests, docs for developers, dependencies, source maps.
const EXCLUDE = [
	/^\.git/,
	/^\.github\//,
	/^\.claude\//,
	/^CLAUDE\.md$/,
	/^docs\//,
	/^tests\//,
	/^node_modules\//,
	/^vendor\//,
	/^composer\.(json|lock)$/,
	/^package(-lock)?\.json$/,
	/^phpcs\.xml\.dist$/,
	/^phpunit\.xml\.dist$/,
	/^eslint\.config\.c?js$/,
	/^\.editorconfig$/,
	/^\.gitignore$/,
	/^\.phpunit\.result\.cache$/,
	/\.map$/,
];

const parseArgs = () => {
	const args = {};
	const argv = process.argv.slice( 2 );
	for ( let i = 0; i < argv.length; i++ ) {
		if ( argv[ i ].startsWith( '--' ) ) {
			args[ argv[ i ].slice( 2 ) ] = argv[ i + 1 ];
			i++;
		}
	}
	return args;
};

const run = ( cmd, args, opts = {} ) => execFileSync( cmd, args, { stdio: 'pipe', encoding: 'utf8', ...opts } );

const listFiles = ( dir, base = dir ) =>
	fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		return entry.isDirectory() ? listFiles( full, base ) : [ path.relative( base, full ).split( path.sep ).join( '/' ) ];
	} );

const sha256 = ( file ) => crypto.createHash( 'sha256' ).update( fs.readFileSync( file ) ).digest( 'hex' );

const syncModule = ( module, ref ) => {
	const source = process.env[ module.env ] || path.resolve( ROOT, '..', module.sibling );
	if ( ! fs.existsSync( path.join( source, '.git' ) ) ) {
		throw new Error( `${ module.repo }: checkout not found at ${ source } (set ${ module.env }).` );
	}
	const commit = run( 'git', [ '-C', source, 'rev-parse', `${ ref }^{commit}` ] ).trim();

	const work = fs.mkdtempSync( path.join( os.tmpdir(), `sync-${ module.sibling }-` ) );
	const tar = path.join( work, 'export.tar' );
	run( 'git', [ '-C', source, 'archive', '--format=tar', '-o', tar, commit ] );
	const tree = path.join( work, 'tree' );
	fs.mkdirSync( tree );
	run( 'tar', [ '-xf', tar, '-C', tree ] );

	// Build assets when the module has a build step.
	const pkgFile = path.join( tree, 'package.json' );
	if ( fs.existsSync( pkgFile ) && JSON.parse( fs.readFileSync( pkgFile, 'utf8' ) ).scripts?.build && fs.existsSync( path.join( tree, 'assets', 'src' ) ) ) {
		const sharedModules = path.join( source, 'node_modules' );
		if ( fs.existsSync( sharedModules ) ) {
			fs.symlinkSync( sharedModules, path.join( tree, 'node_modules' ), 'dir' );
		} else {
			run( 'npm', [ 'ci', '--no-audit', '--no-fund' ], { cwd: tree, stdio: 'inherit' } );
		}
		run( 'npm', [ 'run', 'build' ], { cwd: tree, stdio: 'inherit' } );
	}

	const target = path.join( ROOT, module.target );
	fs.rmSync( target, { recursive: true, force: true } );
	fs.mkdirSync( target, { recursive: true } );

	const files = {};
	for ( const rel of listFiles( tree ) ) {
		if ( EXCLUDE.some( ( pattern ) => pattern.test( rel ) ) ) {
			continue;
		}
		const from = path.join( tree, rel );
		if ( fs.lstatSync( from ).isSymbolicLink() ) {
			continue;
		}
		const to = path.join( target, rel );
		fs.mkdirSync( path.dirname( to ), { recursive: true } );
		if ( /\.(php|js|jsx)$/.test( rel ) ) {
			fs.writeFileSync( to, fs.readFileSync( from, 'utf8' ).split( TEXT_DOMAIN_FROM ).join( TEXT_DOMAIN_TO ) );
		} else {
			fs.copyFileSync( from, to );
		}
		files[ rel ] = sha256( to );
	}

	const mainFile = fs.readFileSync( path.join( target, module.main ), 'utf8' );
	const version = ( mainFile.match( /register_version\(\s*'([^']+)'/ ) || mainFile.match( /define\(\s*'[A-Z_]+_VERSION',\s*'([^']+)'/ ) || [] )[ 1 ] || '';

	const manifest = { repo: module.repo, ref, commit, version, text_domain: TEXT_DOMAIN_TO, files };
	fs.writeFileSync( path.join( target, '.sync.json' ), JSON.stringify( manifest, null, '\t' ) + '\n' );
	fs.rmSync( work, { recursive: true, force: true } );
	console.log( `${ module.repo } ${ ref } (${ commit.slice( 0, 7 ) }, v${ version }) -> ${ module.target } (${ Object.keys( files ).length } files)` );
};

const main = () => {
	const args = parseArgs();
	const wanted = MODULES.filter( ( module ) => args[ module.arg ] );
	if ( ! wanted.length ) {
		console.error( 'Usage: npm run sync:asset-optimizer -- --core <ref>' );
		process.exit( 1 );
	}
	for ( const module of wanted ) {
		syncModule( module, args[ module.arg ] );
	}
	console.log( 'Done. Run npm run check:asset-optimizer-sync, then commit the synced folder.' );
};

try {
	main();
} catch ( error ) {
	console.error( error.message );
	process.exit( 1 );
}

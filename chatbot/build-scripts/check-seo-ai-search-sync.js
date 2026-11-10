/**
 * Fail when a synced SEO & AI Search folder differs from its .sync.json.
 *
 *   npm run check:seo-ai-search-sync
 *
 * Catches hand edits inside includes/wpbot-core and includes/seo-ai-search:
 * change the module repo and re-sync instead. Runs before npm run build-free.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );

const ROOT = path.resolve( __dirname, '..' );
const TARGETS = [ 'includes/wpbot-core', 'includes/seo-ai-search' ];

const listFiles = ( dir, base = dir ) =>
	fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		return entry.isDirectory() ? listFiles( full, base ) : [ path.relative( base, full ).split( path.sep ).join( '/' ) ];
	} );

const sha256 = ( file ) => crypto.createHash( 'sha256' ).update( fs.readFileSync( file ) ).digest( 'hex' );

let failed = false;
for ( const target of TARGETS ) {
	const dir = path.join( ROOT, target );
	const manifestFile = path.join( dir, '.sync.json' );
	if ( ! fs.existsSync( manifestFile ) ) {
		console.error( `${ target }: missing .sync.json (run npm run sync:seo-ai-search).` );
		failed = true;
		continue;
	}
	const manifest = JSON.parse( fs.readFileSync( manifestFile, 'utf8' ) );
	const expected = manifest.files || {};
	const actual = listFiles( dir ).filter( ( rel ) => rel !== '.sync.json' );
	const problems = [];
	for ( const rel of actual ) {
		if ( ! ( rel in expected ) ) {
			problems.push( `added: ${ rel }` );
		} else if ( expected[ rel ] !== sha256( path.join( dir, rel ) ) ) {
			problems.push( `changed: ${ rel }` );
		}
	}
	for ( const rel of Object.keys( expected ) ) {
		if ( ! actual.includes( rel ) ) {
			problems.push( `missing: ${ rel }` );
		}
	}
	if ( problems.length ) {
		failed = true;
		console.error( `${ target } differs from ${ manifest.repo } ${ manifest.ref }:\n  ${ problems.join( '\n  ' ) }` );
	} else {
		console.log( `${ target }: matches ${ manifest.repo } ${ manifest.ref } (v${ manifest.version }).` );
	}
}
// WPBot Free never contains Pro code (WordPress.org guideline: no locked features).
for ( const proDir of [ 'includes/seo-ai-search-pro', 'includes/ai-writer-pro' ] ) {
	if ( fs.existsSync( path.join( ROOT, proDir ) ) ) {
		failed = true;
		console.error( `${ proDir }: Pro modules must not be in WPBot Free. Delete the folder.` );
	}
}
if ( failed ) {
	console.error( 'Synced folders must not be edited here. Change the module repo, then run npm run sync:seo-ai-search.' );
	process.exit( 1 );
}

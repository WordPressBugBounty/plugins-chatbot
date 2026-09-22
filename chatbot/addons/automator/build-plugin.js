const fs = require('fs-extra');
const path = require('path');
const { execSync } = require('child_process');
const archiver = require('archiver');

async function build() {
    const PLUGIN_NAME = 'wpbot-automator';
    const BUILD_DIR = 'dist';
    const ZIP_FILE = `${PLUGIN_NAME}.zip`;
    const PLUGIN_PATH = path.join(BUILD_DIR, PLUGIN_NAME);

    try {
        console.log(`🚀 Starting build process for ${PLUGIN_NAME}...`);

        // 1. Clean up previous builds
        console.log('🧹 Cleaning up old build files...');
        await fs.remove(BUILD_DIR);
        await fs.remove(ZIP_FILE);

        // 2. Build assets
        console.log('📦 Building assets...');
        execSync('npm run build', { stdio: 'inherit' });

        // 3. Create temporary structure
        console.log('📁 Creating production structure...');
        await fs.ensureDir(PLUGIN_PATH);

        // 4. Copy necessary files
        console.log('📂 Copying files...');
        const filesToCopy = [
            'includes',
            'build',
            'assets',
            'wpbot-automator.php',
            'readme.txt'
        ];

        for (const file of filesToCopy) {
            const src = path.join(__dirname, file);
            if (await fs.pathExists(src)) {
                await fs.copy(src, path.join(PLUGIN_PATH, file));
            }
        }

        // 5. Create Zip
        console.log('🤐 Generating zip file...');
        const output = fs.createWriteStream(ZIP_FILE);
        const archive = archiver('zip', { zlib: { level: 9 } });

        output.on('close', async () => {
            console.log(`✅ Success! Zip file created at: ${path.resolve(ZIP_FILE)}`);
            
            // 6. Final Cleanup
            console.log('🧹 Cleaning up temporary files...');
            await fs.remove(BUILD_DIR);
        });

        archive.on('error', (err) => { throw err; });
        archive.pipe(output);
        archive.directory(PLUGIN_PATH, PLUGIN_NAME);
        await archive.finalize();

    } catch (err) {
        console.error('❌ Error:', err.message);
        process.exit(1);
    }
}

build();
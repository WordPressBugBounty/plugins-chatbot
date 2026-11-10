const path = require('path');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

module.exports = {
    mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
    entry: {
        index: './includes/admin/marketing-src/src/index.js',
    },
    output: {
        path: path.resolve(__dirname, 'includes/admin/marketing-build'),
        filename: '[name].js',
        devtoolNamespace: 'wpbot',
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            '@babel/preset-env', 
                            ['@babel/preset-react', { 
                                pragma: 'wp.element.createElement',
                                pragmaFrag: 'wp.element.Fragment',
                            }]
                        ]
                    }
                }
            },
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader']
            }
        ]
    },
    resolve: {
        extensions: ['.js', '.jsx']
    },
    plugins: [
        new DependencyExtractionWebpackPlugin()
    ]
};

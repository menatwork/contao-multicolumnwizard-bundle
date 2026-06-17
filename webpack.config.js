const path                  = require('path');
const MiniCssExtractPlugin  = require('mini-css-extract-plugin');
const CssMinimizerPlugin    = require('css-minimizer-webpack-plugin');
const RemoveEmptyScripts    = require('webpack-remove-empty-scripts');

module.exports = {
    mode: 'production',

    entry: {
        // JS: source → minified output
        'js/multicolumnwizard_be': path.resolve(__dirname, 'src/Resources/public/js/multicolumnwizard_be_src.js'),
        // CSS: source → minified output (RemoveEmptyScripts drops the empty companion .js)
        'css/multicolumnwizard':   path.resolve(__dirname, 'src/Resources/public/css/multicolumnwizard_src.css'),
    },

    output: {
        path: path.resolve(__dirname, 'src/Resources/public'),
        filename: '[name].js',
        // Prevent webpack from wrapping the output in an IIFE so MooTools globals
        // (var MultiColumnWizard = …) remain accessible on window.
        iife: false,
        clean: false,
    },

    devtool: 'source-map',

    module: {
        rules: [
            {
                test: /\.css$/i,
                use: [MiniCssExtractPlugin.loader, 'css-loader'],
            },
        ],
    },

    plugins: [
        // Remove the empty .js files that webpack would otherwise emit for CSS-only entries.
        new RemoveEmptyScripts(),
        new MiniCssExtractPlugin({
            filename: '[name].css',
        }),
    ],

    optimization: {
        minimizer: [
            '...', // keep the default Terser minifier for JS
            new CssMinimizerPlugin(),
        ],
    },
};

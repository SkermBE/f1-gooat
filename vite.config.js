import { loadEnv } from "vite";
import ViteRestart from 'vite-plugin-full-reload'
import tailwindcss from '@tailwindcss/vite'
import banner from 'vite-plugin-banner'
import ViteFaviconsPlugin from "vite-plugin-favicon2";
import critical from 'rollup-plugin-critical';
import path from "path";
import pkg from "./package.json";


export default ({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), '');

  // Plugins
  let plugins = [
    tailwindcss(),
    ViteRestart(
      ['templates/**/*']
    ),
    banner(
      `/**\n * name: ${pkg.name}\n * version: v${pkg.version}\n * description: ${pkg.description}\n * author: ${pkg.author}\n * homepage: ${pkg.homepage}\n */`
    ),
  ];

  if (env.GENERATE_FAVICON === 'true') {
    plugins = plugins.concat([
      ViteFaviconsPlugin({
        logo: 'src/img/favicon.svg',
        projectRoot: process.cwd(),
        inject: false,
        outputPath: "favicons",
        favicons: {
          appName: pkg.name,
          appDescription: pkg.description,
          developerName: pkg.author,
          developerURL: pkg.homepage,
          start_url: "/",
          background: pkg.background,
          theme_color: pkg.theme_color,
        },
      }),
    ]);
  }

  if (env.CRITICAL_CSS === 'true') {
    plugins = plugins.concat([
      critical({
        criticalUrl: 'http://localhost/',
        criticalBase: './web/dist/criticalcss/',
        criticalPages: [
          { uri: '', template: 'index' }
        ],
        criticalConfig: {
          extract: true,
        },
      }),
    ]);
  }

  return {
    base: command === 'serve' ? '' : '/dist/',
    build: {
      manifest: true,
      outDir: 'web/dist/',
      rollupOptions: {
        input: {
          app: './src/js/app.js',
        }
      },
    },
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "src"),
        "@css": path.resolve(__dirname, "src/css"),
        "@js": path.resolve(__dirname, "src/js"),
      },
    },
    plugins: plugins,
    publicDir: path.resolve(__dirname, "src/public"),
    server: {
      // Respond to all network requests
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      origin: `${process.env.DDEV_PRIMARY_URL.replace(/:\d+$/, "")}:5173`,
      cors: {
        origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
      },
    }
  }
};

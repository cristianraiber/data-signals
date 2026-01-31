# Build Instructions for Data Signals Dashboard

## Prerequisites

- Node.js 14+ and npm
- WordPress 5.8+ installation
- PHP 8.0+

## Quick Start

### 1. Install Dependencies

```bash
cd /path/to/wp-content/plugins/data-signals
npm install
```

This will install:
- `@wordpress/scripts` - Build tooling
- `@wordpress/element` - React wrapper
- `@wordpress/api-fetch` - API client
- `@wordpress/components` - UI components
- `recharts` - Charting library
- `zustand` - State management
- `date-fns` - Date utilities

### 2. Build the Dashboard

**Production Build:**
```bash
npm run build
```

This compiles:
- `assets/src/js/index.jsx` → `assets/build/index.js`
- `assets/src/css/dashboard.css` → `assets/build/index.css`
- Auto-generates `assets/build/index.asset.php` (dependency list)

**Development Mode (Hot Reload):**
```bash
npm run start
```

- Watches for file changes
- Auto-rebuilds on save
- Source maps enabled

### 3. Activate Plugin

1. Ensure the `data-signals` folder is in `wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Activate "Data Signals"
4. Navigate to Data Signals menu item

## File Structure After Build

```
data-signals/
├── assets/
│   ├── src/                    # Source files (development)
│   │   ├── js/
│   │   │   ├── components/     # 6 React components
│   │   │   ├── utils/          # API wrappers, formatters
│   │   │   ├── store/          # Zustand store
│   │   │   └── index.jsx       # Entry point
│   │   └── css/
│   │       └── dashboard.css   # Styles
│   └── build/                  # Compiled output (production)
│       ├── index.js            # Bundled JavaScript
│       ├── index.css           # Bundled CSS
│       └── index.asset.php     # WordPress asset metadata
├── includes/
│   ├── class-admin-dashboard.php   # Enqueue handler
│   └── integrations/
│       └── class-woocommerce.php
├── data-signals.php            # Main plugin file
├── package.json                # npm dependencies
└── webpack.config.js           # Build configuration
```

## Troubleshooting

### Build Errors

**"Cannot find module '@wordpress/scripts'"**
```bash
rm -rf node_modules package-lock.json
npm install
```

**"webpack: command not found"**
- Ensure `@wordpress/scripts` is installed
- Check `node_modules/.bin/wp-scripts` exists

**"Module parse failed"**
- Ensure all `.jsx` files use correct syntax
- Check for missing semicolons or brackets

### Dashboard Not Appearing

**"Build assets missing" warning:**
```bash
npm run build
```

**Empty screen in admin:**
1. Check browser console (F12) for errors
2. Verify REST API: `/wp-json/data-signals/v1/analytics`
3. Check nonce and permissions

**Styles not loading:**
1. Hard refresh: Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)
2. Verify `assets/build/index.css` exists
3. Check WordPress admin → Settings → Permalinks (flush)

### Development Mode Issues

**Hot reload not working:**
- Ensure `npm run start` is running
- Check terminal for webpack-dev-server errors
- Try rebuilding: `npm run build`

**Changes not reflecting:**
1. Stop `npm run start`
2. Delete `assets/build/`
3. Run `npm run build`
4. Restart `npm run start`

## Build Configuration

### package.json Scripts

```json
{
  "scripts": {
    "build": "wp-scripts build assets/src/js/index.jsx --output-path=assets/build",
    "start": "wp-scripts start assets/src/js/index.jsx --output-path=assets/build",
    "lint": "wp-scripts lint-js",
    "format": "wp-scripts format"
  }
}
```

### webpack.config.js

Extends `@wordpress/scripts` default config:
- Entry: `assets/src/js/index.jsx`
- Output: `assets/build/`
- Babel transpilation (ES6+ → ES5)
- CSS extraction
- Minification (production)

## Deployment

### To WordPress Site

1. **Build production assets:**
   ```bash
   npm run build
   ```

2. **Copy plugin folder:**
   ```bash
   rsync -av --exclude 'node_modules' \
     /local/path/data-signals/ \
     user@server:/path/to/wp-content/plugins/data-signals/
   ```

3. **Or create ZIP:**
   ```bash
   zip -r data-signals.zip data-signals/ \
     -x "*/node_modules/*" "*.git/*"
   ```

### Important Files for Production

**Required:**
- `assets/build/` (compiled assets)
- `includes/` (PHP classes)
- `data-signals.php` (main plugin)

**Not Required:**
- `node_modules/` (exclude)
- `assets/src/` (optional, source files)
- `.git/` (exclude)

## Performance

### Bundle Size

Check after build:
```bash
npm run build
ls -lh assets/build/
```

Target sizes:
- `index.js`: < 200KB (minified + gzipped)
- `index.css`: < 50KB

### Optimization Tips

1. **Code splitting:**
   ```javascript
   const LazyComponent = React.lazy(() => import('./Component'));
   ```

2. **Memoization:**
   ```javascript
   const memoizedValue = useMemo(() => compute(), [deps]);
   ```

3. **Production build:**
   - Minification enabled
   - Source maps excluded
   - Dead code elimination

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Build Dashboard
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-node@v2
        with:
          node-version: '16'
      - run: npm install
      - run: npm run build
      - uses: actions/upload-artifact@v2
        with:
          name: build-assets
          path: assets/build/
```

## Support

For build issues:
1. Check Node.js version: `node -v` (14+)
2. Check npm version: `npm -v` (6+)
3. Clear cache: `npm cache clean --force`
4. Review webpack output for specific errors

## License

GPL v2 or later

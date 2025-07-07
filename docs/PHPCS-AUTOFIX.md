# WordPress Coding Standards Auto-Fix

Panduan lengkap untuk menggunakan auto-fix WordPress coding standards di project BlazeCommerce.

## 🚀 Quick Start

### 1. Auto-Fix Semua File
```bash
composer run cs:fix-all
```
atau
```bash
./fix-code.sh all
```

### 2. Auto-Fix File Tertentu
```bash
composer run cs:fix-file app/Extensions/WooDiscountRules.php
```
atau
```bash
./fix-code.sh file app/Extensions/WooDiscountRules.php
```

### 3. Check Coding Standards
```bash
composer run cs:check
```
atau
```bash
./fix-code.sh check
```

## 🔧 Available Commands

| Command | Description |
|---------|-------------|
| `composer run cs:fix-all` | Auto-fix semua file PHP |
| `composer run cs:fix-file [file]` | Auto-fix file tertentu |
| `composer run cs:check` | Check coding standards tanpa fix |
| `./fix-code.sh all` | Script helper untuk fix semua |
| `./fix-code.sh file [path]` | Script helper untuk fix file tertentu |
| `./fix-code.sh check` | Script helper untuk check saja |

## 📝 VS Code Integration

### Auto-Fix on Save
File akan otomatis di-fix ketika disimpan jika sudah menginstall extension:
- `ikappas.phpcs`
- `persodic.vscode-phpcbf`

### Manual Fix in VS Code
1. **Command Palette**: `Ctrl+Shift+P` → "Format Document"
2. **Tasks**: `Ctrl+Shift+P` → "Tasks: Run Task" → "PHP CodeSniffer: Fix Current File"
3. **Right-click**: Context menu → "Format Document"

## 🎯 What Gets Fixed Automatically

### ✅ Auto-Fixable Issues
- **Indentation**: Tabs vs spaces, proper indentation levels
- **Spacing**: Extra spaces, missing spaces around operators
- **Line endings**: Windows vs Unix line endings
- **Brackets**: Spacing around brackets and parentheses
- **Arrays**: Array syntax consistency
- **Semicolons**: Missing semicolons
- **Quotes**: Single vs double quotes consistency
- **Function calls**: Spacing in function calls
- **Comments**: Comment formatting

### ❌ Manual Fix Required
- **Variable naming**: $camelCase vs $snake_case
- **Function naming**: WordPress naming conventions
- **Class naming**: Proper class naming
- **Hook naming**: WordPress hook naming
- **Security issues**: Sanitization, validation, escaping
- **Complex logic**: Code structure improvements

## 🛠️ Configuration

### phpcs.xml
Konfigurasi utama ada di `phpcs.xml` yang menggunakan:
- WordPress coding standards
- Custom exclusions untuk file tertentu
- Performance optimizations

### VS Code Settings
Auto-fix diaktifkan melalui:
- Workspace settings (`.vscode/settings.json`)
- Global settings (`settings.json`)

## 🚨 Pre-commit Hook

Git pre-commit hook otomatis akan menjalankan auto-fix pada file yang di-stage:
```bash
git commit -m "Your commit message"
# Auto-fix akan berjalan otomatis sebelum commit
```

## 📊 Statistics

Hasil auto-fix terakhir:
- **6006 errors** berhasil diperbaiki otomatis
- **109 files** telah diproses
- **Waktu eksekusi**: ~52 detik

## 🔍 Troubleshooting

### Error: "Referenced sniff does not exist"
```bash
# Install missing dependencies
composer install
```

### Error: "No fixable errors were found"
✅ Ini adalah hasil yang baik - berarti file sudah sesuai standards!

### VS Code tidak auto-fix
1. Install extension yang direkomendasikan
2. Restart VS Code
3. Check workspace settings di `.vscode/settings.json`

## 📚 Additional Resources

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [PHP CodeSniffer Documentation](https://github.com/squizlabs/PHP_CodeSniffer)
- [WordPress PHP Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)

## 💡 Tips

1. **Run auto-fix regularly** untuk menghindari accumulation of issues
2. **Use pre-commit hook** untuk memastikan code quality
3. **Fix issues incrementally** daripada menunggu sampai banyak
4. **Check before committing** dengan `composer run cs:check`
5. **Use VS Code tasks** untuk workflow yang lebih smooth

# WooCommerce Product Details Extension

Extension untuk menambahkan checkbox control pada WooCommerce Product Details block yang sudah ada.

## Fitur

- ✅ Menambahkan checkbox "Show Short Description" pada WooCommerce Product Details block
- ✅ Kontrol tersedia di sidebar Inspector Controls
- ✅ Berfungsi di editor dan frontend
- ✅ Tidak mengganggu fungsionalitas block yang sudah ada

## Cara Penggunaan

1. Buka Gutenberg editor
2. Tambahkan WooCommerce Product Details block (dari kategori WooCommerce Product Elements)
3. Pilih block tersebut
4. Di sidebar, buka panel "Display Options"
5. Gunakan checkbox "Show Short Description" untuk menampilkan/menyembunyikan short description

## File yang Terlibat

### PHP Backend
- `app/Extensions/Gutenberg/Blocks/WooCommerceProductDetailsExtension.php`
  - Class utama untuk extension
  - Menangani enqueue assets dan render block

### JavaScript
- `assets/js/woocommerce-product-details-extension.js`
  - Menambahkan attribute `showShortDescription` ke block
  - Menambahkan ToggleControl di Inspector Controls
  - Menambahkan CSS class untuk menyembunyikan short description

### CSS
- `assets/css/woocommerce-product-details-extension.css`
  - Styling untuk menyembunyikan short description
  - Berfungsi di editor dan frontend

## Implementasi Teknis

### WordPress Hooks yang Digunakan
- `blocks.registerBlockType` - Menambahkan attribute baru
- `editor.BlockEdit` - Menambahkan control di editor
- `editor.BlockListBlock` - Menambahkan CSS class di editor
- `render_block` - Memodifikasi output di frontend

### Attribute yang Ditambahkan
```javascript
showShortDescription: {
    type: 'boolean',
    default: true,
}
```

### CSS Classes
- `.hide-short-description` - Class yang ditambahkan ketika short description disembunyikan

## Kompatibilitas

- ✅ WordPress 5.0+
- ✅ WooCommerce 5.0+
- ✅ Gutenberg Block Editor
- ✅ Frontend dan Backend

## Troubleshooting

### Short Description Masih Muncul
1. Pastikan checkbox "Show Short Description" tidak dicentang
2. Clear cache jika menggunakan caching plugin
3. Periksa apakah ada CSS custom yang override styling

### Control Tidak Muncul
1. Pastikan menggunakan WooCommerce Product Details block yang asli
2. Pastikan extension sudah diaktifkan
3. Refresh halaman editor

### Styling Tidak Berfungsi
1. Pastikan CSS file ter-enqueue dengan benar
2. Periksa console browser untuk error
3. Pastikan tidak ada conflict dengan theme CSS

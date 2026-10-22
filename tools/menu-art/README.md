# Generator gambar contoh menu

Ilustrasi menu snack contoh di `database/seeders/images/menus/` dibuat dengan skrip ini
(Python + Pillow, digambar dari bentuk dasar, tanpa aset pihak ketiga).

```powershell
pip install pillow
cd tools\menu-art
python make.py ..\..\database\seeders\images\menus
```

Ukuran keluaran 800×600 (4:3) WebP. Nama file = slug nama menu (mis. `corndog-mozzarella.webp`).

@2026-Alifiandra

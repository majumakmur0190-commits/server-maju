document.addEventListener('DOMContentLoaded', function () {
    const navLinks = document.querySelectorAll('.nav-link');
    const pages = document.querySelectorAll('.page-content');
    const headerTitle = document.querySelector('header h1');

    // === Variabel & Konstanta untuk Transaksi ===
    const API_URL_PENJUALAN = "../api/penjualan.php";
    const API_URL_PELANGGAN = "../api/pelanggan.php";
    const API_URL_BARANG = "../api/barang.php";
    const penjualanForm = document.getElementById("penjualanForm");
    const pelangganSelect = document.getElementById("pelanggan_id");
    const cartItemsContainer = document.getElementById("cart-items-android");
    const productModal = document.getElementById('product-modal');
    const productListContainer = document.getElementById('product-list');
    const productSearchInput = document.getElementById('productSearchInput');
    const subtotalSpan = document.getElementById('subtotal-android');
    const grandTotalSpan = document.getElementById('grandTotal-android');
    const diskonHeaderInput = document.getElementById('diskon_header');
    const homeProductSearchInput = document.getElementById('homeProductSearch');
    const ppnHeaderInput = document.getElementById('ppn_header');
    let allBarang = [];
    // ============================================

    let currentUser = null;

    navLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault();

            const targetId = this.dataset.target;
            const targetPage = document.getElementById(targetId);

            // Hide all pages
            pages.forEach(page => {
                page.classList.add('hidden');
            });

            // Show the target page
            if (targetPage) {
                targetPage.classList.remove('hidden');
            }

            // Update active link style
            navLinks.forEach(nav => {
                nav.classList.remove('text-blue-600');
                nav.classList.add('text-gray-600');
            });
            this.classList.add('text-blue-600');
            this.classList.remove('text-gray-600');
        });
    });

    // Fungsi untuk memeriksa otentikasi
    function checkAuth() {
        const userDataString = localStorage.getItem('maju_user');
        if (!userDataString) {
            window.location.replace('login.html');
            return;
        }
        currentUser = JSON.parse(userDataString);
        loadProfileData();
        loadHomeData();
        // Muat data untuk form penjualan
        loadPelangganOptions();
        loadBarangOptions();
        document.getElementById('user_id').value = currentUser.id; // ✅ Perbaikan: Gunakan currentUser.id
    }

    // Fungsi untuk memuat data profil
    function loadProfileData() {
        if (!currentUser) return;
        document.getElementById('profile-name').textContent = currentUser.username; // Ganti dengan nama jika ada
        document.getElementById('profile-username').textContent = currentUser.username;
        // Update gambar profil dari ui-avatars
        const profilePic = document.getElementById('profile-pic');
        profilePic.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.username)}&background=1e88e5&color=fff&size=128`;
    }

    // Fungsi untuk memuat data dinamis di halaman home
    function loadHomeData() {
        renderHomeProductList(allBarang);
    }

    // Tampilkan daftar produk di halaman home
    function renderHomeProductList(products) {
        const homeProductListContainer = document.getElementById('homeProductList');
        if (!homeProductListContainer) return;

        if (products.length === 0) {
            homeProductListContainer.innerHTML = `<p class="text-center text-gray-500 mt-8">Tidak ada produk ditemukan.</p>`;
            return;
        }

        homeProductListContainer.innerHTML = products.map(b => {
            // console.log(b);
            const stockClass = b.stok <= 0 ? 'text-red-500' : 'text-gray-500';
            return `
                        <div class="bg-white p-3 rounded-lg shadow-sm flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-gray-800">${b.nama_kategori} ${b.nama_barang}</p>
                                <p class="text-sm text-gray-600">${formatRupiah(b.harga_hna)}</p>
                                <p class="text-sm text-gray-600 font-medium">Stok:${formatRupiah(b.stok)}</p>
                            </div>
                        </div>
                    `;
        }).join('');
    }

    // Fungsi untuk logout
    window.logout = function () {
        localStorage.removeItem('maju_user');
        window.location.replace('login.html');
    }

    // ===================================================
    // === FUNGSI-FUNGSI UNTUK HALAMAN TRANSAKSI BARU ===
    // ===================================================

    const formatRupiah = (number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);

    // Muat data pelanggan & barang
    async function loadPelangganOptions() {
        try {
            const res = await fetch(API_URL_PELANGGAN, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await res.json();
            if (result.status === "success" && result.data) {
                pelangganSelect.innerHTML += result.data.map(p => `<option value="${p.pelanggan_id}">${p.nama_pelanggan}</option>`).join('');
            }
        } catch (error) { console.error("Gagal memuat pelanggan:", error); }
    }

    async function loadBarangOptions() {
        try {
            const res = await fetch(API_URL_BARANG, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await res.json();
            if (result.status === "success" && result.data) {
                allBarang = result.data;
                renderProductList(allBarang);
                renderHomeProductList(allBarang); // Render juga untuk halaman home
            }
        } catch (error) { console.error("Gagal memuat barang:", error); }
    }

    // Tampilkan daftar produk di modal
    function renderProductList(products) {
        productListContainer.innerHTML = products.map(b => {
            const isOutOfStock = b.stok <= 0;
            return `
                        <div onclick="${isOutOfStock ? '' : `addProductToCart(${b.barang_id}); closeProductModal();`}" 
                             class="p-3 border rounded-lg flex justify-between items-center ${isOutOfStock ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'hover:bg-blue-50 cursor-pointer'}">
                            <div>
                                <p class="font-semibold">${b.nama_kategori} ${b.nama_barang}</p>
                                <p class="text-sm text-gray-500">${formatRupiah(b.harga_jual)} | Stok: ${b.stok}</p>
                            </div>
                            ${!isOutOfStock ? '<span class="text-blue-500 font-bold text-xl">+</span>' : ''}
                        </div>
                    `;
        }).join('');
    }

    // Pencarian produk
    productSearchInput.addEventListener('input', (e) => {
        const raw = (e.target.value || '').trim().toLowerCase();
        if (!raw) {
            renderProductList(allBarang);
            return;
        }
        const tokens = raw.split(/\s+/);
        const filtered = allBarang.filter(b => {
            const combined = `${b.nama_kategori || ''} ${b.nama_barang || ''}`.toLowerCase();
            return tokens.every(t => combined.includes(t));
        });
        renderProductList(filtered);
    });

    // Pencarian produk di halaman home
    homeProductSearchInput.addEventListener('input', (e) => {
        const raw = (e.target.value || '').trim().toLowerCase();
        if (!raw) {
            renderHomeProductList(allBarang);
            return;
        }
        const tokens = raw.split(/\s+/);
        const filtered = allBarang.filter(b => {
            const combined = `${b.nama_kategori || ''} ${b.nama_barang || ''}`.toLowerCase();
            return tokens.every(t => combined.includes(t));
        });
        renderHomeProductList(filtered);
    });

    // Tambah produk ke keranjang
    window.addProductToCart = function (barangId) {
        const existingItem = cartItemsContainer.querySelector(`[data-barang-id="${barangId}"]`);
        const barang = allBarang.find(b => b.barang_id == barangId);
        if (!barang) return;

        if (existingItem) {
            const jumlahInput = existingItem.querySelector('input[name="jumlah"]');
            let newJumlah = parseInt(jumlahInput.value) + 1;
            if (newJumlah > barang.stok) {
                customAlert(`Stok ${barang.nama_barang} tidak mencukupi.`);
                newJumlah = barang.stok;
            }
            jumlahInput.value = newJumlah;
        } else {
            const itemHtml = `
                        <div class="border rounded-lg p-3" data-barang-id="${barang.barang_id}">
                            <p class="font-semibold">${barang.nama_barang}</p>
                            <div class="flex items-center justify-between mt-2 text-sm">
                                <input type="number" name="jumlah" value="1" min="1" max="${barang.stok}" class="w-16 border rounded px-2 py-1" oninput="calculateGrandTotal()">
                                <span class="text-gray-600">x ${formatRupiah(barang.harga_jual)}</span>
                                <button type="button" onclick="this.closest('[data-barang-id]').remove(); calculateGrandTotal();" class="text-red-500">&times; Hapus</button>
                            </div>
                            <input type="hidden" name="harga_satuan" value="${barang.harga_jual}">
                            <input type="hidden" name="diskon_item" value="${barang.diskon_jual}">
                        </div>
                    `;
            cartItemsContainer.insertAdjacentHTML('beforeend', itemHtml);
        }
        calculateGrandTotal();
    }

    // Hitung total
    window.calculateGrandTotal = function () {
        let subtotal = 0;
        cartItemsContainer.querySelectorAll('[data-barang-id]').forEach(item => {
            const jumlah = parseInt(item.querySelector('input[name="jumlah"]').value) || 0;
            const harga = parseFloat(item.querySelector('input[name="harga_satuan"]').value) || 0;
            subtotal += jumlah * harga;
        });

        const diskonPersen = parseFloat(diskonHeaderInput.value) || 0;
        const ppnPersen = parseFloat(ppnHeaderInput.value) || 0;
        const subtotalAfterDiscount = subtotal * (1 - diskonPersen / 100);
        const total = subtotalAfterDiscount * (1 + ppnPersen / 100);

        subtotalSpan.textContent = formatRupiah(subtotal);
        grandTotalSpan.textContent = formatRupiah(total);
    }
    diskonHeaderInput.addEventListener('input', calculateGrandTotal);
    ppnHeaderInput.addEventListener('input', calculateGrandTotal);

    // Modal
    window.openProductModal = () => productModal.classList.remove('hidden');
    window.closeProductModal = () => productModal.classList.add('hidden');

    // Simpan Transaksi
    penjualanForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const items = [];
        cartItemsContainer.querySelectorAll('[data-barang-id]').forEach(row => {
            items.push({
                barang_id: row.dataset.barangId,
                jumlah: row.querySelector('input[name="jumlah"]').value,
                harga_satuan: row.querySelector('input[name="harga_satuan"]').value,
                diskon: row.querySelector('input[name="diskon_item"]').value
            });
        });

        if (items.length === 0) {
            customAlert("Keranjang tidak boleh kosong.");
            return;
        }

        const dataToSend = {
            user_id: document.getElementById('user_id').value,
            pelanggan_id: pelangganSelect.value,
            diskon: diskonHeaderInput.value,
            ppn: ppnHeaderInput.value,
            items: items
        };

        try {
            const res = await fetch(API_URL_PENJUALAN, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(dataToSend)
            });
            const result = await res.json();
            customAlert(result.message);
            if (result.status === 'success') {
                penjualanForm.reset();
                cartItemsContainer.innerHTML = '';
                calculateGrandTotal();
                loadBarangOptions(); // Muat ulang stok
            }
        } catch (error) {
            customAlert('Gagal menyimpan transaksi.', 'Error');
        }
    });

    // Inisialisasi halaman
    checkAuth();
});

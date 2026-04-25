// c/xampp/htdocs/maju/pc/js/main.js

// ✅ Fungsi Toggle Dropdown (Global)
window.toggleDropdown = function(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId);
    const icon = document.getElementById(iconId);

    if (dropdown) dropdown.classList.toggle('hidden');
    if (icon) icon.classList.toggle('rotate-180');
}

/**
 * memberikan warna pada menu aktif di sidebar 
 * Memuat sidebar, menyorot menu aktif, dan mengaktifkan tombol logout.
 * Fungsi ini harus dipanggil di setiap halaman yang menggunakan sidebar.
 */
function initializeApp() {
    // Sinkronisasi Pengaturan dari Config File ke LocalStorage
    if (window.electronAPI) {
        window.electronAPI.loadConfig().then(config => {
            if (config.serverIp) localStorage.setItem('server_ip', config.serverIp);
            if (config.paginationLimit) localStorage.setItem('pagination_limit', config.paginationLimit);
        });
    }

    // 1. Muat Sidebar
    fetch("sidebar.html")
        .then(r => r.ok ? r.text() : Promise.reject("Sidebar not found"))
        .then(html => {
            const sidebarContainer = document.getElementById("sidebar-container");
            if (!sidebarContainer) return;

            sidebarContainer.innerHTML = html;

            // ✅ Tambahan: Logika untuk menyembunyikan menu user berdasarkan role
            const userDataString = sessionStorage.getItem('loggedInUser');
            if (userDataString) {
                const user = JSON.parse(userDataString);
                if (user.role !== 'admin') {
                    const userMenuLink = document.querySelector('a[data-page="users"]');
                    const laporanMenuLink = document.querySelector('a[data-page="laporan"]');
                    if (laporanMenuLink) {
                        laporanMenuLink.style.display = 'none';
                    }
                    if (userMenuLink) {
                        userMenuLink.style.display = 'none';
                    }
                }
            }

            // 2. Sorot Menu Aktif
            const currentPage = window.location.pathname.split("/").pop();
            const links = document.querySelectorAll("#sidebar-menu a[href]");
            const activeLink = Array.from(links).find(link =>
                link.getAttribute('href') === currentPage ||
                (currentPage === "" && link.getAttribute('href') === "index.html")
            );

            if (activeLink) {
                const submenu = activeLink.closest('ul[role="menu"]');
                const activeMenuItem = submenu
                    ? submenu.closest('li[role="menuitem"]')
                    : activeLink.closest('li[role="menuitem"]');

                if (activeMenuItem) {
                    activeMenuItem.classList.add("menu-active");
                }
            }

            // 3. Aktifkan Tombol Logout
            const logoutButton = document.getElementById('logoutButton');
            if (logoutButton) {
                logoutButton.addEventListener('click', function (event) {
                    event.preventDefault(); // Mencegah navigasi langsung
                    // Gunakan customConfirm untuk dialog logout
                    customConfirm(
                        "Apakah Anda yakin ingin keluar dari sesi ini?",
                        async () => { // onConfirm: jika pengguna klik "Ya"
                            // ✅ Hapus data user dari config.json (Permanen) agar tidak auto-login lagi
                            if (window.electronAPI) {
                                try {
                                    const config = await window.electronAPI.loadConfig();
                                    delete config.userData; // Hapus properti userData
                                    await window.electronAPI.saveConfig(config);
                                } catch (err) {
                                    console.error("Gagal menghapus data user di config.json:", err);
                                }
                            }
                            // Hapus session
                            sessionStorage.removeItem('loggedInUser');
                            window.location.href = 'login.html';
                        },
                        null, // onCancel: tidak ada aksi jika batal
                        "Konfirmasi Logout"
                    );
                });
            }
        }).catch(error => console.error("Failed to initialize sidebar:", error));
}


// ✅ Custom Alert & Confirm Modal
(function () {
    // Buat container global untuk modal
    const modalContainer = document.createElement("div");
    modalContainer.id = "custom-modal-container";
    // Style overlay agar menutupi layar (menggantikan class Tailwind)
    modalContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    `;
    document.body.appendChild(modalContainer);

    // 🔹 Fungsi internal untuk buat modal HTML dengan gaya 7.css
    function createModal({ title, message, buttons, icon }) {
        const buttonsHtml = buttons.map((btn, i) => {
            // Tambahkan class 'default' jika tombol adalah aksi utama
            const isDefault = ['OK', 'Ya', 'Simpan', 'Hapus'].includes(btn.text);
            const btnClass = isDefault ? 'default' : '';
            return `<button data-index="${i}" class="${btnClass}" style="min-width: 70px;">${btn.text}</button>`;
        }).join("");

        modalContainer.innerHTML = `
            <div class="window active" style="min-width: 300px; max-width: 400px; box-shadow: 5px 5px 15px rgba(0,0,0,0.3);">
                <div class="title-bar">
                    <div class="title-bar-text">${title}</div>
                    <div class="title-bar-controls">
                        <button aria-label="Close" class="close-modal-btn"></button>
                    </div>
                </div>
                <div class="window-body">
                    <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px;">
                        <div style="font-size: 32px;">${icon}</div>
                        <p style="margin: 0; align-self: center;">${message}</p>
                    </div>
                    <div class="field-row" style="justify-content: flex-end; gap: 8px;">
                        ${buttonsHtml}
                    </div>
                </div>
            </div>
        `;

        modalContainer.style.display = "flex";

        // Event Listener untuk tombol Close di Title Bar
        const closeBtn = modalContainer.querySelector(".close-modal-btn");
        if (closeBtn) {
            closeBtn.addEventListener("click", () => {
                modalContainer.style.display = "none";
            });
        }

        // Event Listener untuk tombol aksi
        modalContainer.querySelectorAll("button[data-index]").forEach((btn) => {
            btn.addEventListener("click", () => {
                modalContainer.style.display = "none";
                const i = btn.getAttribute("data-index");
                buttons[i].onClick && buttons[i].onClick();
            });
        });
    }

    // 🔔 Alert sederhana
    window.customAlert = function (message, title = "Pemberitahuan", onClose) {
        createModal({
            title,
            message,
            icon: "ℹ️",
            buttons: [
                {
                    text: "OK",
                    onClick: onClose,
                },
            ],
        });
    };

    // ❓ Confirm dengan callback true/false
    window.customConfirm = function (
        message,
        onConfirm,
        onCancel,
        title = "Konfirmasi"
    ) {
        createModal({
            title,
            message,
            icon: "❓",
            buttons: [
                {
                    text: "Ya",
                    onClick: onConfirm,
                },
                {
                    text: "Batal",
                    onClick: onCancel,
                },
            ],
        });
    };
})();

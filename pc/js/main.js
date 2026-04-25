// c/xampp/htdocs/maju/pc/js/main.js

// ✅ Fungsi Toggle Dropdown (Global)
window.toggleDropdown = function(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId);
    const icon = document.getElementById(iconId);

    if (dropdown) dropdown.classList.toggle('hidden');
    if (icon) icon.classList.toggle('rotate-180');
}

/**
 * Memuat sidebar, menyorot menu aktif, dan mengaktifkan tombol logout.
 * Fungsi ini harus dipanggil di setiap halaman yang menggunakan sidebar.
 */
function initializeApp() {
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
            const links = document.querySelectorAll("#sidebar-menu a");
            links.forEach(link => {
                if (link.getAttribute('href') === currentPage || (currentPage === "" && link.getAttribute('href') === "index.html")) {
                    link.classList.add("bg-blue-800", "font-semibold");

                    // ✅ Buka dropdown jika menu aktif ada di dalamnya
                    const parentDropdown = link.closest('div[id$="Dropdown"]');
                    if (parentDropdown) {
                        parentDropdown.classList.remove('hidden');
                        // Rotasi icon pada button pemicu
                        const triggerBtn = parentDropdown.previousElementSibling;
                        if (triggerBtn && triggerBtn.tagName === 'BUTTON') {
                            const icon = triggerBtn.querySelector('svg');
                            if (icon) icon.classList.add('rotate-180');
                        }
                    }
                }
            });

            // 3. Aktifkan Tombol Logout
            const logoutButton = document.getElementById('logoutButton');
            if (logoutButton) {
                logoutButton.addEventListener('click', function (event) {
                    event.preventDefault(); // Mencegah navigasi langsung
                    // Gunakan customConfirm untuk dialog logout
                    customConfirm(
                        "Apakah Anda yakin ingin keluar dari sesi ini?",
                        () => { // onConfirm: jika pengguna klik "Ya"
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
    modalContainer.className = "fixed inset-0 flex items-center justify-center z-[9999] hidden";
    document.body.appendChild(modalContainer);

    // 🔹 Fungsi internal untuk buat modal HTML
    function createModal({ title, message, buttons }) {
        modalContainer.innerHTML = `
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl w-[90%] max-w-sm p-6 animate-fadeIn">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">${title}</h2>
        <p class="text-gray-600 mb-6">${message}</p>
        <div class="flex justify-end gap-3">
          ${buttons
                .map(
                    (btn, i) => `
              <button data-index="${i}" class="${btn.class}">
                ${btn.text}
              </button>`
                )
                .join("")}
        </div>
      </div>
    `;

        modalContainer.classList.remove("hidden");

        // Tutup modal & trigger action tombol
        modalContainer.querySelectorAll("button").forEach((btn) => {
            btn.addEventListener("click", () => {
                modalContainer.classList.add("hidden");
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
            buttons: [
                {
                    text: "OK",
                    class:
                        "bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition",
                    onClick: onClose, // ✅ Jalankan callback saat tombol OK diklik
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
            buttons: [
                {
                    text: "Batal",
                    class:
                        "bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg font-semibold transition",
                    onClick: onCancel,
                },
                {
                    text: "Ya",
                    class:
                        "bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition",
                    onClick: onConfirm,
                },
            ],
        });
    };

    // 🔧 Animasi Tailwind custom
    const style = document.createElement("style");
    style.textContent = `
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }
    .animate-fadeIn { animation: fadeIn 0.2s ease-out; }
  `;
    document.head.appendChild(style);
})();

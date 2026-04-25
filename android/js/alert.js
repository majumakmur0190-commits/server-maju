// ✅ Custom Alert & Confirm Modal untuk Android
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
                        "bg-accent hover:bg-orange-500 text-white px-4 py-2 rounded-lg font-semibold transition",
                    onClick: onClose,
                },
            ],
        });
    };

    // ❓ Confirm dengan callback
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
})();